<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class DatabaseDumpScpTransfer implements ShouldQueue
{
	use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

	public $timeout = 1800;
	public $tries = 3;

	private function remoteDir(): string
	{
		$name = (string) config("app.name", "app");
		$name = preg_replace("/[^A-Za-z0-9._-]+/", "_", $name);

		return rtrim(config("ssh.remote_base_path"), "/") . "/" . $name;
	}

	public function handle(): void
	{
		Log::info("Starting Database Dump SCP Transfer", [
			"key" => config("ssh.private_key_path"),
			"known_hosts" => config("ssh.known_hosts_path"),
			"remote_dir" => $this->remoteDir(),
		]);

		$dumpFile = null;

		try {
			$dbConfig = Config::get("database.connections." . Config::get("database.default"));

			if (($dbConfig["driver"] ?? null) !== "mysql" && ($dbConfig["driver"] ?? null) !== "mariadb") {
				throw new \RuntimeException("Database driver must be mysql or mariadb");
			}

			$timestamp = date("Y-m-d_His");
			$dbName = (string) $dbConfig["database"];
			$dumpFileName = "{$dbName}_{$timestamp}.sql";
			$dumpPath = storage_path("app" . DIRECTORY_SEPARATOR . "temp");

			if (!is_dir($dumpPath)) {
				mkdir($dumpPath, 0755, true);
			}

			$dumpFile = $dumpPath . DIRECTORY_SEPARATOR . $dumpFileName;

			$this->executeMysqldump($dbConfig, $dumpFile);

			if (!file_exists($dumpFile) || filesize($dumpFile) === 0) {
				throw new \RuntimeException("Dump file is empty or does not exist");
			}

			$remoteDir = $this->remoteDir();

			$this->ensureRemoteDirExists($remoteDir);

			$this->transferViaScp($dumpFile, $dumpFileName);

			$this->pruneRemoteBackups($dbName);

			Log::info("Database dump transferred successfully", [
				"file" => $dumpFileName,
				"size" => filesize($dumpFile),
				"remote" => rtrim($remoteDir, "/") . "/" . $dumpFileName,
				"host" => config("ssh.host"),
				"port" => config("ssh.port"),
			]);
		} catch (\Throwable $e) {
			Log::error("Database dump SCP transfer failed", [
				"error" => $e->getMessage(),
				"trace" => $e->getTraceAsString(),
			]);

			throw $e;
		} finally {
			if ($dumpFile !== null && file_exists($dumpFile)) {
				@unlink($dumpFile);
			}
		}
	}

	private function executeMysqldump(array $dbConfig, string $outputFile): void
	{
		$command = [
			$this->findMysqldumpBinary(),
			"--host=" . $dbConfig["host"],
			"--port=" . $dbConfig["port"],
			"--user=" . $dbConfig["username"],
			"--single-transaction",
			"--quick",
			"--lock-tables=false",
			"--routines",
			"--triggers",
			"--events",
			$dbConfig["database"],
		];

		$process = new Process($command);
		$process->setTimeout(1800);
		$process->setEnv([
			"MYSQL_PWD" => (string) ($dbConfig["password"] ?? ""),
		]);

		$process->setInput(null);
		$process->start();

		$handle = fopen($outputFile, "wb");
		if ($handle === false) {
			throw new \RuntimeException("Cannot create dump file: {$outputFile}");
		}

		try {
			while ($process->isRunning()) {
				$output = $process->getIncrementalOutput();
				if ($output !== "") {
					fwrite($handle, $output);
				}

				$err = $process->getIncrementalErrorOutput();
				if ($err !== "") {
					Log::warning("mysqldump stderr chunk", ["stderr" => $err]);
				}

				usleep(100000);
			}

			$remainingOutput = $process->getIncrementalOutput();
			if ($remainingOutput !== "") {
				fwrite($handle, $remainingOutput);
			}

			$remainingErr = $process->getIncrementalErrorOutput();
			if ($remainingErr !== "") {
				Log::warning("mysqldump stderr final", ["stderr" => $remainingErr]);
			}

			fclose($handle);

			if (!$process->isSuccessful()) {
				throw new ProcessFailedException($process);
			}
		} catch (\Throwable $e) {
			fclose($handle);
			if (file_exists($outputFile)) {
				@unlink($outputFile);
			}
			throw $e;
		}
	}

	private function findMysqldumpBinary(): string
	{
		$possiblePaths = [
			"mysqldump",
			"/usr/bin/mysqldump",
			"/usr/local/bin/mysqldump",
			"C:\\Program Files\\MySQL\\MySQL Server 8.0\\bin\\mysqldump.exe",
			"C:\\Program Files\\MySQL\\MySQL Server 5.7\\bin\\mysqldump.exe",
		];

		foreach ($possiblePaths as $path) {
			if ($this->isExecutable($path)) {
				return $path;
			}
		}

		$process = new Process(["which", "mysqldump"]);
		$process->run();
		if ($process->isSuccessful()) {
			$foundPath = trim($process->getOutput());
			if ($foundPath !== "") {
				return $foundPath;
			}
		}

		if (PHP_OS_FAMILY === "Windows") {
			$process = new Process(["where", "mysqldump"]);
			$process->run();
			if ($process->isSuccessful()) {
				$foundPath = trim($process->getOutput());
				if ($foundPath !== "") {
					return explode("\n", $foundPath)[0];
				}
			}
		}

		throw new \RuntimeException("mysqldump binary not found. Please ensure MySQL client tools are installed.");
	}

	private function isExecutable(string $path): bool
	{
		if (PHP_OS_FAMILY === "Windows") {
			return file_exists($path) && is_file($path);
		}

		return str_contains($path, "/") ? file_exists($path) && is_executable($path) : true;
	}

	private function ensureRemoteDirExists(string $remoteDir): void
	{
		$keyPath = config("ssh.private_key_path");
		$knownHostsPath = config("ssh.known_hosts_path");

		if (!file_exists($keyPath)) {
			throw new \RuntimeException("Private key file not found: {$keyPath}");
		}

		if (!file_exists($knownHostsPath)) {
			throw new \RuntimeException("known_hosts file not found: {$knownHostsPath}. Create it with: ssh-keyscan -p " . config("ssh.port") . " " . config("ssh.host") . " >> {$knownHostsPath}");
		}

		$command = [
			"ssh",
			"-p",
			(string) config("ssh.port"),
			"-i",
			$keyPath,
			"-o",
			"BatchMode=yes",
			"-o",
			"IdentitiesOnly=yes",
			"-o",
			"StrictHostKeyChecking=yes",
			"-o",
			"UserKnownHostsFile=" . $knownHostsPath,
			config("ssh.username") . "@" . config("ssh.host"),
			"mkdir -p " . escapeshellarg($remoteDir),
		];

		$process = new Process($command);
		$process->setTimeout(30);
		$process->run();

		if (!$process->isSuccessful()) {
			throw new ProcessFailedException($process);
		}
	}

	private function transferViaScp(string $localFile, string $remoteFileName): void
	{
		$keyPath = config("ssh.private_key_path");
		$knownHostsPath = config("ssh.known_hosts_path");

		if (!file_exists($keyPath)) {
			throw new \RuntimeException("Private key file not found: {$keyPath}");
		}

		if (!file_exists($knownHostsPath)) {
			throw new \RuntimeException("known_hosts file not found: {$knownHostsPath}. Create it with: ssh-keyscan -p " . config("ssh.port") . " " . config("ssh.host") . " >> {$knownHostsPath}");
		}

		$remotePath = rtrim($this->remoteDir(), "/") . "/" . $remoteFileName;

		$command = [
			"scp",
			"-P",
			(string) config("ssh.port"),
			"-i",
			$keyPath,
			"-o",
			"BatchMode=yes",
			"-o",
			"IdentitiesOnly=yes",
			"-o",
			"ConnectTimeout=" . config("ssh.timeout"),
			"-o",
			"StrictHostKeyChecking=yes",
			"-o",
			"UserKnownHostsFile=" . $knownHostsPath,
			$localFile,
			config("ssh.username") . "@" . config("ssh.host") . ":" . $remotePath,
		];

		$process = new Process($command);
		$process->setTimeout(config("ssh.timeout"));
		$process->run();

		if (!$process->isSuccessful()) {
			throw new ProcessFailedException($process);
		}
	}

	private function pruneRemoteBackups(string $dbName): void
	{
		$keyPath = config("ssh.private_key_path");
		$knownHostsPath = config("ssh.known_hosts_path");

		if (!file_exists($keyPath)) {
			throw new \RuntimeException("Private key file not found: {$keyPath}");
		}

		if (!file_exists($knownHostsPath)) {
			throw new \RuntimeException("known_hosts file not found: {$knownHostsPath}. Create it with: ssh-keyscan -p " . config("ssh.port") . " " . config("ssh.host") . " >> {$knownHostsPath}");
		}

		$remoteDir = $this->remoteDir();

		// Sanitizza dbName per usarlo in pattern remoto in modo sicuro
		$safeDb = preg_replace("/[^A-Za-z0-9._-]+/", "_", $dbName);

		$remoteDirArg = escapeshellarg($remoteDir);
		$pattern = $safeDb . "_*.sql";

		// Tieni i 3 piu recenti, elimina gli altri
		$remoteCommand =
			"set -e; " .
			"cd {$remoteDirArg} || exit 0; " .
			"ls -1t -- {$pattern} 2>/dev/null | tail -n +4 | xargs rm -f -- 2>/dev/null || true";

		$command = [
			"ssh",
			"-p",
			(string) config("ssh.port"),
			"-i",
			$keyPath,
			"-o",
			"BatchMode=yes",
			"-o",
			"IdentitiesOnly=yes",
			"-o",
			"StrictHostKeyChecking=yes",
			"-o",
			"UserKnownHostsFile=" . $knownHostsPath,
			config("ssh.username") . "@" . config("ssh.host"),
			$remoteCommand,
		];

		$process = new Process($command);
		$process->setTimeout((int) config("ssh.timeout", 30));
		$process->run();

		if (!$process->isSuccessful()) {
			throw new ProcessFailedException($process);
		}

		Log::info("Remote backup retention applied", [
			"kept" => 3,
			"database" => $dbName,
			"remote_path" => $remoteDir,
		]);
	}

	private function isAbsolutePath(string $path): bool
	{
		if (PHP_OS_FAMILY === "Windows") {
			return (bool) preg_match("/^([A-Z]:\\\\|\\\\\\\\)/i", $path);
		}

		return str_starts_with($path, "/");
	}

	public function failed(\Throwable $exception): void
	{
		Log::error("Database dump SCP transfer job failed permanently", [
			"error" => $exception->getMessage(),
			"trace" => $exception->getTraceAsString(),
		]);
	}
}

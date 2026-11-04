<?php

namespace App\Console\Commands\Database;

use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\ArgvInput;

/**
 * Bring a database's structure in line with the migrations, without touching
 * the rows.
 *
 * The migrations are the description of the schema; this walks the difference
 * between that description and what a database actually has. It exists so a
 * change can be made by editing the create migration itself, instead of adding
 * one more alter file per change and watching the folder fill up.
 *
 * How: read the structure the migrations describe, read what the database has,
 * and emit the statements that close the gap.
 *
 * Structure is brought into line on its own — tables created, columns added,
 * types widened, indexes both added and dropped. An index carries no data, so
 * one the migrations no longer describe is simply stale.
 *
 * Tables and columns are the exception: dropping either destroys rows, and this
 * command cannot tell a dead table from one that belongs to somebody else's
 * work. Those are asked about one at a time, and the answer defaults to no.
 *
 * Not covered: triggers, views, stored procedures, partitions. None are used
 * here; if that changes, this command stops being the whole answer.
 */
class SchemaSync extends Command
{
	protected $signature = 'db:schema-sync
                            {--apply : Execute the statements instead of only printing them}
                            {--prune : Answer yes to every removal without asking — for unattended runs}
                            {--force : Allow --apply in production, where it is otherwise refused}
                            {--snapshot : Rebuild the snapshot of the migrations and exit, comparing nothing}';

	protected $description = "Align the database structure with the migrations, leaving data untouched";

	private const SNAPSHOT = "database/migrations/snapshot/schema.json";

	private const LOCK = "db:schema-sync:";

	private const VIA_MAKE = "SCHEMA_SYNC_VIA_MAKE";

	private string $shadow;

	private bool $shadowCreated = false;

	/**
	 * Removals are never folded into the additive pass: each one is proposed on
	 * its own and has to be accepted. Dropping a table or a column is the one
	 * direction that loses data, and nothing here can know whether that table
	 * is dead or simply belongs to someone else.
	 *
	 * @var list<array{descrizione: string, statements: list<string>, distruttivo: bool}>
	 */
	private array $removals = [];

	/**
	 * Indexes left in place because a foreign key still relies on them.
	 *
	 * @var list<string>
	 */
	private array $indiciSaltati = [];

	public function handle(): int
	{
		if (!$this->invokedProperly()) {
			return self::FAILURE;
		}

		if ($this->option("snapshot")) {
			if (app()->environment("production")) {
				$this->components->error(
					"Refusing to build the shadow database in production. " .
						"Take the snapshot locally (or in CI) and commit it."
				);

				return self::FAILURE;
			}

			return $this->writeSnapshot();
		}

		if (app()->environment("production") && $this->option("apply") && !$this->option("force")) {
			$this->components->error(
				"Refusing to --apply in production without --force. Run without it and review the statements."
			);

			return self::FAILURE;
		}

		$target = DB::connection()->getDatabaseName();

		$this->components->info("Comparing [{$target}] with the migrations.");

		$expected = $this->expectedStructure();

		if ($expected === null) {
			return self::FAILURE;
		}

		$statements = $this->diff($target, $expected);

		if ($statements === [] && $this->removals === []) {
			$this->components->info("Structure already in line: nothing to change.");
			$this->listIndiciSaltati();

			return self::SUCCESS;
		}

		if ($statements === []) {
			$this->components->info("Structure in line. Only removals are left.");
			$this->listIndiciSaltati();

			if ($this->option("apply")) {
				$this->applyRemovals();
				$this->pruneOrphanMigrations();
			} else {
				$this->listRemovals();
			}

			return self::SUCCESS;
		}

		$this->newLine();
		foreach ($statements as $statement) {
			$this->line("  {$statement};");
		}
		$this->newLine();
		$this->listIndiciSaltati();

		if (!$this->option("apply")) {
			$this->listRemovals();
			$this->components->warn(
				sprintf(
					"%d %s to apply. Re-run with --apply to execute.",
					count($statements),
					Str::plural("change", count($statements))
				)
			);

			return self::SUCCESS;
		}

		// Un solo --apply per volta sullo stesso database: due che si
		// sovrappongono vedrebbero lo stesso identico lavoro da fare e lo
		// eseguirebbero due volte. Il lock è del server e sta appeso alla
		// connessione: se il processo muore, viene rilasciato da solo.
		if (!$this->lock($target)) {
			$this->components->error(
				"Another db:schema-sync --apply is already working on [{$target}]. " . "Wait for it to finish and re-run."
			);

			return self::FAILURE;
		}

		try {
			if (!$this->applyStatements($target, $statements)) {
				return self::FAILURE;
			}

			$this->applyRemovals();
			$this->pruneOrphanMigrations();
		} finally {
			$this->unlock($target);
		}

		return self::SUCCESS;
	}

	private function applyStatements(string $target, array $statements): bool
	{
		$totale = count($statements);

		// Foreign key checks off while applying: missing tables come back in
		// whatever order information_schema lists them, so a CREATE can
		// legitimately reference a table that is still a few statements away.
		DB::statement("SET FOREIGN_KEY_CHECKS = 0");

		$fatti = 0;

		try {
			foreach ($statements as $statement) {
				try {
					DB::statement($statement);
				} catch (QueryException $e) {
					$this->reportFailure($statement, $fatti, $totale, $e);

					return false;
				}

				$fatti++;
				$this->line(sprintf("  <fg=gray>[%d/%d]</> %s", $fatti, $totale, $this->summarise($statement)));
			}
		} finally {
			DB::statement("SET FOREIGN_KEY_CHECKS = 1");
		}

		$this->components->info(sprintf("%d %s applied. No data touched.", $totale, Str::plural("change", $totale)));

		return true;
	}

	private function reportFailure(string $statement, int $fatti, int $totale, QueryException $e): void
	{
		$this->newLine();
		$this->components->error(
			sprintf(
				"Statement %d of %d failed. The %d before it stay applied: they are all " .
					"additive, the database is usable.",
				$fatti + 1,
				$totale,
				$fatti
			)
		);

		$this->line("  <fg=gray>Statement:</>");
		foreach (explode("\n", $statement) as $riga) {
			$this->line("    <fg=gray>" . $riga . "</>");
		}

		$this->newLine();
		$this->line("  <fg=gray>MySQL:</>");
		$this->line("    " . trim(explode(" (Connection:", $e->getMessage())[0]));

		$this->newLine();
		$this->components->warn("Fix the cause and re-run: the comparison is redone from scratch and picks up from here.");
	}

	private function summarise(string $statement): string
	{
		$prima = strtok($statement, "\n");

		return mb_strlen($prima) > 100 ? mb_substr($prima, 0, 97) . "..." : $prima;
	}

	private function lock(string $target): bool
	{
		$row = DB::selectOne("SELECT GET_LOCK(?, 0) AS ok", [self::LOCK . $target]);

		return (int) (array_values($this->row($row))[0] ?? 0) === 1;
	}

	private function unlock(string $target): void
	{
		DB::statement("DO RELEASE_LOCK(?)", [self::LOCK . $target]);
	}

	private function invokedProperly(): bool
	{
		if (!$this->input instanceof ArgvInput) {
			return true;
		}

		if (getenv(self::VIA_MAKE) !== false || env(self::VIA_MAKE) !== null) {
			return true;
		}

		$this->components->error("db:schema-sync is run from the Makefile, not by hand.");
		$this->line("  <fg=cyan>make db-schema-snapshot</>  rebuild the snapshot of the migrations (local only, as root)");
		$this->line("  <fg=cyan>make db-schema-diff</>      show what would change, touching nothing");
		$this->line("  <fg=cyan>make db-schema-apply</>     bring the structure in line");
		$this->newLine();
		$this->line("  <fg=gray>The targets set up what the command on its own does not have: the right</>");
		$this->line("  <fg=gray>credentials, the environment guards, and the confirmation on AWS.</>");

		return false;
	}

	private function listIndiciSaltati(): void
	{
		if ($this->indiciSaltati === []) {
			return;
		}

		$this->components->warn(
			sprintf(
				"%d %s left in place: a foreign key still needs %s.",
				count($this->indiciSaltati),
				Str::plural("index", count($this->indiciSaltati)),
				count($this->indiciSaltati) === 1 ? "it" : "them"
			)
		);

		foreach ($this->indiciSaltati as $saltato) {
			$this->line("    {$saltato}");
		}

		$this->line("  Drop the constraint first if the column is really gone.");
		$this->newLine();
	}

	private function listRemovals(): void
	{
		if ($this->removals === []) {
			return;
		}

		$this->newLine();
		$this->components->warn(
			sprintf(
				"%d %s in the database that no migration describes:",
				count($this->removals),
				Str::plural("item", count($this->removals))
			)
		);

		foreach ($this->removals as $removal) {
			$this->line("    " . $removal["descrizione"]);
		}

		$this->line("  <fg=gray>With --apply you are asked about each one.</>");
		$this->newLine();
	}

	private function applyRemovals(): void
	{
		if ($this->removals === []) {
			return;
		}

		$this->newLine();
		$this->components->warn(
			sprintf(
				"%d %s in the database that no migration describes.",
				count($this->removals),
				Str::plural("item", count($this->removals))
			)
		);

		foreach ($this->removals as $removal) {
			$domanda = $removal["distruttivo"]
				? "  {$removal["descrizione"]}\n  Drop it? The data cannot be recovered"
				: "  {$removal["descrizione"]}\n  Drop it? (re-running the migrations recreates it)";

			// --prune says yes to everything: for pipelines, where nobody is
			// there to answer and leaving drift is the worse outcome.
			$procedi = $this->option("prune")
				? true
				: ($this->input->isInteractive()
					? $this->confirm($domanda, false)
					: false);

			if (!$procedi) {
				$this->line("    <fg=gray>left as it is</>");

				continue;
			}

			foreach ($removal["statements"] as $statement) {
				DB::statement($statement);
			}

			$this->line("    <info>dropped</info>");
		}

		$this->newLine();
	}

	private function pruneOrphanMigrations(): void
	{
		$onDisk = collect(glob(database_path("migrations/*.php")))->map(
			static fn(string $path): string => basename($path, ".php")
		);

		$orphans = collect(DB::table("migrations")->pluck("migration"))->reject(
			static fn(string $name): bool => $onDisk->contains($name)
		);

		if ($orphans->isEmpty()) {
			return;
		}

		DB::table("migrations")->whereIn("migration", $orphans)->delete();

		$this->components->info(
			sprintf("%d orphan %s removed from [migrations]:", $orphans->count(), Str::plural("row", $orphans->count()))
		);

		foreach ($orphans as $orphan) {
			$this->line("    {$orphan}");
		}
	}

	private function expectedStructure(): ?array
	{
		$snapshot = $this->readSnapshot();

		if ($snapshot === null) {
			$this->components->error(
				"Missing (or unreadable) " .
					self::SNAPSHOT .
					".\n" .
					"Generate it where the MySQL is yours — locally or in CI — with: make db-schema-snapshot"
			);

			return null;
		}

		$migrations = $this->migrationFingerprints();

		if (($snapshot["migrations"] ?? null) !== $migrations) {
			$this->staleWarning($snapshot, $migrations);

			if ($this->option("apply")) {
				$this->components->error(
					"Refusing to apply a snapshot that does not match the migrations. " .
						"Regenerate it where the MySQL is yours, commit it, and re-run."
				);

				return null;
			}
		}

		$this->line(
			sprintf(
				"  <fg=gray>Expected structure from %s (%d %s).</>",
				self::SNAPSHOT,
				count($snapshot["tables"]),
				Str::plural("table", count($snapshot["tables"]))
			)
		);

		return $snapshot;
	}

	private function readSnapshot(): ?array
	{
		$file = base_path(self::SNAPSHOT);

		if (!is_file($file)) {
			return null;
		}

		$snapshot = json_decode((string) file_get_contents($file), true);

		return is_array($snapshot) && is_array($snapshot["tables"] ?? null) ? $snapshot : null;
	}

	private function staleWarning(array $snapshot, array $suDisco): void
	{
		$nelFile = is_array($snapshot["migrations"] ?? null) ? $snapshot["migrations"] : [];

		$aggiunte = array_diff_key($suDisco, $nelFile);
		$rimosse = array_diff_key($nelFile, $suDisco);
		$modificate = array_filter(
			array_intersect_key($suDisco, $nelFile),
			static fn(string $hash, string $nome): bool => $nelFile[$nome] !== $hash,
			ARRAY_FILTER_USE_BOTH
		);

		$this->components->warn(
			sprintf(
				"The snapshot does not match the migrations on disk (%d added, %d removed, %d changed): " .
					"what follows is a comparison against an old description. " .
					"Regenerate it with make db-schema-snapshot, and commit it.",
				count($aggiunte),
				count($rimosse),
				count($modificate)
			)
		);
	}

	private function migrationFingerprints(): array
	{
		$fingerprints = [];

		foreach (glob(database_path("migrations/*.php")) ?: [] as $path) {
			$fingerprints[basename($path, ".php")] = (string) hash_file("sha256", $path);
		}

		ksort($fingerprints);

		return $fingerprints;
	}

	private function writeSnapshot(): int
	{
		$this->components->info("Snapshotting the migrations.");

		try {
			$expected = $this->buildExpectedFromShadow();
		} catch (QueryException $e) {
			$this->components->error(
				"Cannot build the shadow here: this user has no CREATE DATABASE.\n" .
					"The snapshot has to be taken where the MySQL is yours — locally or in CI — and committed. " .
					"The comparison, that one runs anywhere.\n\n" .
					$e->getMessage()
			);

			return self::FAILURE;
		}

		$file = base_path(self::SNAPSHOT);

		if (!is_dir(dirname($file))) {
			mkdir(dirname($file), 0755, true);
		}

		file_put_contents(
			$file,
			json_encode($expected, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n"
		);

		$this->components->info(
			sprintf(
				"%d %s written to %s — commit it.",
				count($expected["tables"]),
				Str::plural("table", count($expected["tables"])),
				self::SNAPSHOT
			)
		);

		return self::SUCCESS;
	}

	private function buildExpectedFromShadow(): array
	{
		$this->shadow = DB::connection()->getDatabaseName() . "_schema_shadow";

		try {
			$this->buildShadow();

			return $this->readStructure($this->shadow);
		} finally {
			// Solo se l'abbiamo creato noi: quando il CREATE fallisce per
			// permessi, un DROP che fallisce a sua volta coprirebbe l'errore
			// vero — ed è proprio quello che expectedStructure() deve leggere.
			if ($this->shadowCreated) {
				DB::statement("DROP DATABASE IF EXISTS `{$this->shadow}`");
			}
		}
	}

	private function readStructure(string $database): array
	{
		$tables = [];

		foreach ($this->tables($database) as $table) {
			$create = $this->row(DB::selectOne("SHOW CREATE TABLE `{$database}`.`{$table}`"));

			$tables[$table] = [
				"create" => $create["create table"],
				"columns" => $this->columns($database, $table),
				"indexes" => $this->indexes($database, $table),
			];
		}

		return [
			"migrations" => $this->migrationFingerprints(),
			"tables" => $tables,
		];
	}

	private function buildShadow(): void
	{
		DB::statement("DROP DATABASE IF EXISTS `{$this->shadow}`");
		DB::statement("CREATE DATABASE `{$this->shadow}`");

		$this->shadowCreated = true;

		Config::set(
			"database.connections.schema_shadow",
			array_merge(config("database.connections." . config("database.default")), ["database" => $this->shadow])
		);

		$migrator = app("migrator");
		$originale = DB::getDefaultConnection();

		try {
			$migrator->setConnection("schema_shadow");
			$migrator->getRepository()->createRepository();
			$migrator->run([database_path("migrations")], ["pretend" => false]);
		} finally {
			$migrator->setConnection($originale);
			DB::setDefaultConnection($originale);
		}
	}

	private function diff(string $target, array $expected): array
	{
		$statements = [];

		$tablesExpected = array_keys($expected["tables"]);
		$tablesTarget = $this->tables($target);

		foreach (array_diff($tablesExpected, $tablesTarget) as $table) {
			$statements[] = str_replace(
				"CREATE TABLE `{$table}`",
				"CREATE TABLE `{$target}`.`{$table}`",
				$expected["tables"][$table]["create"]
			);
		}

		foreach (array_diff($tablesTarget, $tablesExpected) as $table) {
			$righe =
				(int) (array_values($this->row(DB::selectOne("SELECT COUNT(*) AS n FROM `{$target}`.`{$table}`")))[0] ?? 0);

			$this->removals[] = [
				"descrizione" => sprintf(
					"Table [%s] — no migration describes it, %d %s",
					$table,
					$righe,
					Str::plural("row", $righe)
				),
				"statements" => ["DROP TABLE `{$target}`.`{$table}`"],
				"distruttivo" => true,
			];
		}

		foreach (array_intersect($tablesExpected, $tablesTarget) as $table) {
			$statements = [
				...$statements,
				...$this->diffColumns($target, $table, $expected["tables"][$table]["columns"]),
				...$this->diffIndexes($target, $table, $expected["tables"][$table]["indexes"]),
			];
		}

		return $statements;
	}

	private function diffColumns(string $target, string $table, array $wanted): array
	{
		$have = $this->columns($target, $table);
		$statements = [];

		foreach ($wanted as $name => $spec) {
			if (!isset($have[$name])) {
				$statements[] =
					"ALTER TABLE `{$target}`.`{$table}` ADD COLUMN " .
					$this->columnSql($name, $spec) .
					($spec["after"] !== null ? " AFTER `{$spec["after"]}`" : "");

				continue;
			}

			if ($this->columnSql($name, $spec) !== $this->columnSql($name, $have[$name])) {
				$statements[] = "ALTER TABLE `{$target}`.`{$table}` MODIFY " . $this->columnSql($name, $spec);
			}
		}

		foreach (array_diff(array_keys($have), array_keys($wanted)) as $extra) {
			$valorizzate =
				(int) (array_values(
					$this->row(DB::selectOne("SELECT COUNT(`{$extra}`) AS n FROM `{$target}`.`{$table}`"))
				)[0] ?? 0);

			$this->removals[] = [
				"descrizione" => sprintf(
					"Column [%s.%s] — no migration describes it, %d %s with a value",
					$table,
					$extra,
					$valorizzate,
					Str::plural("row", $valorizzate)
				),
				// The constraint cannot outlive the column it points at, and
				// MySQL refuses to drop the column while it stands. Unlike the
				// index case in diffIndexes(), here the removal has been asked
				// for explicitly, so the constraint goes with it.
				"statements" => [
					...array_map(
						fn(string $vincolo): string => "ALTER TABLE `{$target}`.`{$table}` DROP FOREIGN KEY `{$vincolo}`",
						$this->foreignKeysDiColonna($target, $table, $extra)
					),
					"ALTER TABLE `{$target}`.`{$table}` DROP COLUMN `{$extra}`",
				],
				"distruttivo" => true,
			];
		}

		return $statements;
	}

	private function diffIndexes(string $target, string $table, array $wanted): array
	{
		$have = $this->indexes($target, $table);
		$statements = [];

		foreach ($wanted as $name => $spec) {
			if ($name === "PRIMARY" || isset($have[$name])) {
				continue;
			}

			$cols = implode(
				", ",
				array_map(fn(string $col): string => str_starts_with($col, "(") ? $col : "`{$col}`", $spec["columns"])
			);
			$statements[] =
				"ALTER TABLE `{$target}`.`{$table}` ADD " . ($spec["unique"] ? "UNIQUE " : "") . "INDEX `{$name}` ({$cols})";
		}

		$vincolati = $this->indiciDiForeignKey($target, $table);

		foreach (array_diff(array_keys($have), array_keys($wanted)) as $extra) {
			if ($extra === "PRIMARY") {
				continue;
			}

			// MySQL refuses to drop an index a foreign key relies on, and
			// FOREIGN_KEY_CHECKS = 0 does not lift that — it only stops rows
			// being validated. Dropping the constraint instead is a decision
			// about the data model, not an alignment of the structure, so the
			// index is left where it is and reported.
			// Unless an index the migrations keep starts on the same column: added
			// above, before this drop runs, it takes the constraint over.
			$primaColonna = $have[$extra]["columns"][0];
			$sostituito = array_filter(
				$wanted,
				fn(array $spec, string $nome): bool => $nome !== "PRIMARY" && $spec["columns"][0] === $primaColonna,
				ARRAY_FILTER_USE_BOTH
			);

			if (isset($vincolati[$extra]) && $sostituito === []) {
				$this->indiciSaltati[] = "{$table}.{$extra} → vincolo {$vincolati[$extra]}";

				continue;
			}

			$statements[] = "ALTER TABLE `{$target}`.`{$table}` DROP INDEX `{$extra}`";
		}

		return $statements;
	}

	/**
	 * The foreign key constraints defined on one column.
	 *
	 * @return list<string>
	 */
	private function foreignKeysDiColonna(string $database, string $table, string $column): array
	{
		return array_map(
			fn(object $row): string => (string) $this->row($row)["constraint_name"],
			DB::select(
				'SELECT constraint_name
                 FROM information_schema.key_column_usage
                 WHERE table_schema = ? AND table_name = ? AND column_name = ?
                   AND referenced_table_name IS NOT NULL',
				[$database, $table, $column]
			)
		);
	}

	/**
	 * Index name → name of the foreign key constraint that needs it.
	 *
	 * @return array<string, string>
	 */
	private function indiciDiForeignKey(string $database, string $table): array
	{
		$rows = DB::select(
			'SELECT constraint_name, column_name
             FROM information_schema.key_column_usage
             WHERE table_schema = ? AND table_name = ? AND referenced_table_name IS NOT NULL',
			[$database, $table]
		);

		$vincolati = [];

		foreach ($rows as $row) {
			$row = $this->row($row);
			// The backing index carries the constraint's own name unless it was
			// created separately, in which case it is the one on that column.
			$vincolati[$row["constraint_name"]] = $row["constraint_name"];

			foreach ($this->indexes($database, $table) as $nome => $spec) {
				if (($spec["columns"][0] ?? null) === $row["column_name"]) {
					$vincolati[$nome] = $row["constraint_name"];
				}
			}
		}

		return $vincolati;
	}

	private function row(object $row): array
	{
		return array_change_key_case((array) $row);
	}

	/** @return list<string> */
	private function tables(string $database): array
	{
		return array_map(
			fn($row): string => (string) array_values($this->row($row))[0],
			DB::select(
				'SELECT table_name FROM information_schema.tables
                 WHERE table_schema = ? AND table_type = ? ORDER BY table_name',
				[$database, "BASE TABLE"]
			)
		);
	}

	private function columns(string $database, string $table): array
	{
		$rows = DB::select(
			'SELECT column_name, column_type, is_nullable, column_default, extra
             FROM information_schema.columns
             WHERE table_schema = ? AND table_name = ? ORDER BY ordinal_position',
			[$database, $table]
		);

		$columns = [];
		$previous = null;

		foreach ($rows as $row) {
			$row = $this->row($row);
			$columns[$row["column_name"]] = [
				"type" => $row["column_type"],
				"nullable" => $row["is_nullable"] === "YES",
				"default" => $row["column_default"],
				"extra" => $row["extra"],
				"after" => $previous,
			];
			$previous = $row["column_name"];
		}

		return $columns;
	}

	private function indexes(string $database, string $table): array
	{
		$rows = DB::select(
			'SELECT index_name, column_name, expression, non_unique
             FROM information_schema.statistics
             WHERE table_schema = ? AND table_name = ?
             ORDER BY index_name, seq_in_index',
			[$database, $table]
		);

		$indexes = [];

		foreach ($rows as $row) {
			$row = $this->row($row);
			// A functional key part has no column, only its expression: kept
			// wrapped in parentheses, the way ADD INDEX takes it.
			$indexes[$row["index_name"]]["columns"][] = $row["column_name"] ?? "(" . $row["expression"] . ")";
			$indexes[$row["index_name"]]["unique"] = (int) $row["non_unique"] === 0;
		}

		return $indexes;
	}

	private function columnSql(string $name, array $spec): string
	{
		$sql = "`{$name}` {$spec["type"]}" . ($spec["nullable"] ? " NULL" : " NOT NULL");

		if ($spec["default"] !== null) {
			$literal =
				in_array(strtoupper($spec["default"]), ["CURRENT_TIMESTAMP", "NULL"], true) || is_numeric($spec["default"])
					? $spec["default"]
					: "'" . addslashes($spec["default"]) . "'";

			$sql .= " DEFAULT {$literal}";
		}

		if ($spec["extra"] !== "") {
			$sql .= " " . $spec["extra"];
		}

		return $sql;
	}
}

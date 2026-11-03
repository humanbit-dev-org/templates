<?php

namespace App\Support\Integrations;

use RuntimeException;

/**
 * Normalizes mTLS certificate and key material from AWS secrets into PEM files
 * readable by OpenSSL/cURL.
 */
class PemMaterialNormalizer
{
    /**
     * Materiale normalizzato → file temporaneo già scritto in questo processo.
     *
     * @var array<string, string>
     */
    private static array $tempPaths = [];

    /**
     * Return a filesystem path to PEM material OpenSSL/cURL can read.
     *
     * @throws RuntimeException if the file is missing, empty, or cannot be normalized
     */
    public function resolveReadablePath(string $path, string $label): string
    {
        if (blank($path) || ! is_readable($path) || filesize($path) === 0) {
            throw new RuntimeException(
                "mTLS {$label} file is missing or unreadable: [{$path}]"
            );
        }

        $content = file_get_contents($path);

        if (is_string($content)) {
            $content = preg_replace('/^\xEF\xBB\xBF/', '', $content) ?? $content;
        }

        if ($content === false || trim($content) === '') {
            throw new RuntimeException(
                "mTLS {$label} file is missing or unreadable: [{$path}]"
            );
        }

        if ($this->isProperlyFormattedPem($content)) {
            return $path;
        }

        return $this->writeTempPem($this->normalize($content, $label), $label);
    }

    /**
     * Scrive il PEM normalizzato in un file temporaneo leggibile da OpenSSL/cURL.
     *
     * Il nome lo sceglie tempnam(), non un hash del contenuto. Un path prevedibile
     * in una directory condivisa e scrivibile da tutti è un file temporaneo insicuro
     * (CWE-377): chiunque può pre-crearlo e ritrovarsi la chiave privata dentro un
     * file di sua proprietà, e il chmod dopo non recupera niente perché l'owner è
     * già l'altro. È anche il motivo per cui il path fisso si piantava: il primo
     * processo che lo scriveva a 0600 lo rendeva inaccessibile per sempre a chi
     * girava con un uid diverso (test come root dopo un run come utente = EACCES).
     *
     * La cache statica conserva l'unica cosa buona del path deterministico: lo
     * stesso materiale, nello stesso processo, viene scritto una volta sola.
     *
     * @throws RuntimeException if the temporary file cannot be created
     */
    private function writeTempPem(string $normalized, string $label): string
    {
        $cacheKey = $label.':'.hash('sha256', $normalized);
        $cached = self::$tempPaths[$cacheKey] ?? null;

        if ($cached !== null && is_readable($cached)) {
            return $cached;
        }

        $tempPath = tempnam(sys_get_temp_dir(), "mtls-{$label}-");

        if ($tempPath === false) {
            throw new RuntimeException(
                "mTLS {$label} material could not be written to a temporary file"
            );
        }

        // Materiale di chiave: non deve sopravvivere al processo che l'ha scritto,
        // altrimenti ogni run ne lascia indietro uno. Registrata una volta sola,
        // alla prima scrittura.
        if (self::$tempPaths === []) {
            register_shutdown_function(static function (): void {
                foreach (self::$tempPaths as $path) {
                    @unlink($path);
                }

                self::$tempPaths = [];
            });
        }

        // tempnam() crea già a 0600: il chmod lo rende esplicito e copre le
        // piattaforme dove non è garantito. Prima della scrittura, così la chiave
        // non transita mai per un file più aperto di così.
        chmod($tempPath, 0600);
        file_put_contents($tempPath, $normalized);

        return self::$tempPaths[$cacheKey] = $tempPath;
    }

    /**
     * Whether the content is already a valid multiline PEM block.
     */
    public function isProperlyFormattedPem(string $content): bool
    {
        $content = trim($content);

        if ($content === '') {
            return false;
        }

        $lines = preg_split('/\r\n|\r|\n/', $content) ?: [];

        if (count($lines) < 3) {
            return false;
        }

        if (! preg_match('/^-----BEGIN .+-----$/', $lines[0])) {
            return false;
        }

        $lastIndex = count($lines) - 1;

        if (! preg_match('/^-----END .+-----$/', $lines[$lastIndex])) {
            return false;
        }

        $bodyLines = array_values(array_filter(
            array_slice($lines, 1, -1),
            static fn (string $line): bool => $line !== '',
        ));

        if ($bodyLines === []) {
            return false;
        }

        foreach ($bodyLines as $index => $line) {
            if (! preg_match('/^[A-Za-z0-9+\/]+=*$/', $line)) {
                return false;
            }

            $isLastBodyLine = $index === count($bodyLines) - 1;

            if (! $isLastBodyLine && strlen($line) !== 64) {
                return false;
            }
        }

        return true;
    }

    /**
     * Build a valid multiline PEM string from raw secret content.
     *
     * @throws RuntimeException if the material cannot be normalized
     */
    public function normalize(string $content, string $label): string
    {
        $content = trim($content);

        if (preg_match('/-----BEGIN\s+([^-\r\n]+?)\s*-----\s*(.+?)\s*-----END\s+\1\s*-----/s', $content, $matches)) {
            return $this->formatPemBlock(trim($matches[1]), preg_replace('/\s+/', '', $matches[2]));
        }

        $compact = preg_replace('/\s+/', '', $content) ?? '';

        if ($label === 'cert' && $this->isBase64Material($compact)) {
            return $this->formatPemBlock('CERTIFICATE', $compact);
        }

        if ($label === 'key' && $this->isBase64Material($compact)) {
            $der = base64_decode($compact, true);

            return $this->formatPemBlock(
                is_string($der) ? $this->privateKeyPemMarker($der) : 'PRIVATE KEY',
                $compact,
            );
        }

        if ($label === 'key') {
            throw new RuntimeException(
                "mTLS key material is invalid or missing PEM markers: [{$label}]"
            );
        }

        throw new RuntimeException(
            "mTLS {$label} material could not be normalized"
        );
    }

    /**
     * @return non-empty-string
     */
    private function formatPemBlock(string $marker, string $body): string
    {
        $wrappedBody = rtrim(chunk_split($body, 64, "\n"));

        return "-----BEGIN {$marker}-----\n{$wrappedBody}\n-----END {$marker}-----\n";
    }

    private function isBase64Material(string $value): bool
    {
        return $value !== '' && preg_match('/^[A-Za-z0-9+\/]+=*$/', $value) === 1;
    }

    /**
     * Choose the PEM marker for raw PKCS#8 or PKCS#1 private key DER.
     */
    private function privateKeyPemMarker(string $der): string
    {
        if (str_contains($der, "\x30\x0d\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01")) {
            return 'PRIVATE KEY';
        }

        if (str_starts_with($der, "\x30")) {
            return 'RSA PRIVATE KEY';
        }

        return 'PRIVATE KEY';
    }
}

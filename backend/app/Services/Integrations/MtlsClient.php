<?php

namespace App\Services\Integrations;

use App\Support\Integrations\PemMaterialNormalizer;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Outbound HTTP client with mTLS client certificate authentication.
 * Composable with OAuth Bearer token and form-encoded requests.
 */
class MtlsClient
{
    private ?string $bearerToken = null;

    private bool $asForm = false;

    public function __construct(
        private readonly string $baseUrl,
        private readonly PemMaterialNormalizer $pemNormalizer = new PemMaterialNormalizer,
    ) {}

    /**
     * Attach a Bearer token to subsequent requests.
     */
    public function withToken(string $token): self
    {
        $clone = clone $this;
        $clone->bearerToken = $token;

        return $clone;
    }

    /**
     * Send requests as application/x-www-form-urlencoded.
     */
    public function asForm(): self
    {
        $clone = clone $this;
        $clone->asForm = true;

        return $clone;
    }

    /**
     * Send a POST request to the given path using mTLS.
     */
    public function post(string $path, array $payload): Response
    {
        return $this->client()->post($this->url($path), $payload);
    }

    /**
     * Send a GET request to the given path using mTLS.
     */
    public function get(string $path): Response
    {
        return $this->client()->get($this->url($path));
    }

    /**
     * Build the mTLS authenticated HTTP client.
     *
     * @throws RuntimeException if cert or key file is missing or unreadable
     */
    private function client(): PendingRequest
    {
        $certPath = $this->pemNormalizer->resolveReadablePath(
            (string) config('services.mtls_tenant.cert'),
            'cert',
        );
        $keyPath = $this->pemNormalizer->resolveReadablePath(
            (string) config('services.mtls_tenant.key'),
            'key',
        );

        // Guzzle's own TLS request options, not raw "curl" ones: Guzzle 8 rejects
        // CURLOPT_SSLCERT/SSLKEY/SSLVERSION passed through "curl" with an
        // InvalidArgumentException, before any request leaves. crypto_method sets
        // the minimum version: TLS 1.2 or later, as CURL_SSLVERSION_TLSv1_2 did.
        $request = Http::withOptions([
            'cert' => $certPath,
            'ssl_key' => $keyPath,
            'crypto_method' => STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT,
        ]);

        if ($this->asForm) {
            $request = $request->asForm();
        } else {
            $request = $request->acceptJson();
        }

        if ($this->bearerToken !== null) {
            $request = $request->withToken($this->bearerToken);
        }

        return $request;
    }

    /**
     * Build the full URL for the given API path.
     */
    private function url(string $path): string
    {
        return rtrim($this->baseUrl, '/').'/'.ltrim($path, '/');
    }
}

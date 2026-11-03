<?php

namespace App\Logging;

/**
 * The EC2 instance id, read from the instance metadata service (IMDSv2).
 *
 * Read when the configuration is built: in production config:cache runs on
 * every container start, so each instance asks once and keeps its own id.
 * Null off EC2 or when the metadata service does not answer within a second.
 */
final class Ec2InstanceId
{
    private const BASE = 'http://169.254.169.254/latest';

    public static function resolve(): ?string
    {
        $token = self::request('PUT', '/api/token', ['X-aws-ec2-metadata-token-ttl-seconds: 60']);

        if ($token === null) {
            return null;
        }

        $id = self::request('GET', '/meta-data/instance-id', ['X-aws-ec2-metadata-token: '.$token]);

        return $id !== null && preg_match('/^i-[0-9a-f]+$/', $id) === 1 ? $id : null;
    }

    /**
     * @param  list<string>  $headers
     */
    private static function request(string $method, string $path, array $headers): ?string
    {
        $context = stream_context_create(['http' => [
            'method' => $method,
            'header' => implode("\r\n", $headers),
            'timeout' => 1,
            'ignore_errors' => false,
        ]]);

        $body = @file_get_contents(self::BASE.$path, false, $context);

        return is_string($body) && $body !== '' ? trim($body) : null;
    }
}

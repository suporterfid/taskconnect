<?php

namespace App\Application\Execution;

final class RequestSnapshotRedactor
{
    private const SENSITIVE_HEADERS = [
        'authorization',
        'x-api-key',
        'cookie',
        'proxy-authorization',
    ];

    /**
     * @param  array<string, string|list<string>>  $headers
     * @return array<string, string>
     */
    public function redactHeaders(array $headers): array
    {
        $redacted = [];

        foreach ($headers as $name => $value) {
            $lower = strtolower((string) $name);

            if (in_array($lower, self::SENSITIVE_HEADERS, true)) {
                $redacted[$name] = '[REDACTED]';

                continue;
            }

            $redacted[$name] = is_array($value) ? implode(', ', $value) : (string) $value;
        }

        return $redacted;
    }

    public function redactUrl(string $url): string
    {
        $parts = parse_url($url);

        if ($parts === false) {
            return $url;
        }

        if (isset($parts['user'], $parts['pass'])) {
            $parts['user'] = '[REDACTED]';
            $parts['pass'] = '[REDACTED]';
        }

        return $this->buildUrl($parts);
    }

    public function redactBody(?string $body): ?string
    {
        return $body;
    }

    /**
     * @param  array<string, mixed>  $parts
     */
    private function buildUrl(array $parts): string
    {
        $scheme = isset($parts['scheme']) ? $parts['scheme'].'://' : '';
        $user = $parts['user'] ?? '';
        $pass = isset($parts['pass']) ? ':'.$parts['pass'] : '';
        $auth = ($user !== '' || $pass !== '') ? $user.$pass.'@' : '';
        $host = $parts['host'] ?? '';
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';
        $path = $parts['path'] ?? '';
        $query = isset($parts['query']) ? '?'.$parts['query'] : '';
        $fragment = isset($parts['fragment']) ? '#'.$parts['fragment'] : '';

        return $scheme.$auth.$host.$port.$path.$query.$fragment;
    }
}

<?php

namespace App\Domain\Execution\Outbound;

/**
 * Minimal robots.txt path check (User-agent groups + Disallow/Allow).
 */
final class RobotsTxtParser
{
    public function isPathAllowed(string $robotsBody, string $userAgent, string $path): bool
    {
        $path = $path === '' ? '/' : $path;
        $ua = strtolower(trim($userAgent));
        $groups = $this->parseGroups($robotsBody);

        $rules = $this->selectRules($groups, $ua);
        if ($rules === []) {
            return true;
        }

        $bestAllow = null;
        $bestDisallow = null;
        foreach ($rules as $rule) {
            $prefix = $rule['path'];
            if ($prefix === '') {
                // Empty Disallow means allow all for that rule.
                if (! $rule['allow']) {
                    continue;
                }
            }
            if ($prefix !== '' && ! $this->pathMatches($path, $prefix)) {
                continue;
            }
            if ($prefix === '' && $rule['allow']) {
                continue;
            }

            $len = strlen($prefix);
            if ($rule['allow']) {
                if ($bestAllow === null || $len > $bestAllow) {
                    $bestAllow = $len;
                }
            } elseif ($bestDisallow === null || $len > $bestDisallow) {
                $bestDisallow = $len;
            }
        }

        if ($bestDisallow === null) {
            return true;
        }

        if ($bestAllow !== null && $bestAllow >= $bestDisallow) {
            return true;
        }

        return false;
    }

    /**
     * @param  list<array{agents: list<string>, rules: list<array{allow: bool, path: string}>}>  $groups
     * @return list<array{allow: bool, path: string}>
     */
    private function selectRules(array $groups, string $ua): array
    {
        $wildcard = [];
        foreach ($groups as $group) {
            foreach ($group['agents'] as $agent) {
                if ($agent !== '*' && $ua !== '' && str_starts_with($ua, $agent)) {
                    return $group['rules'];
                }
                if ($agent === '*') {
                    $wildcard = $group['rules'];
                }
            }
        }

        return $wildcard;
    }

    /**
     * @return list<array{agents: list<string>, rules: list<array{allow: bool, path: string}>}>
     */
    private function parseGroups(string $body): array
    {
        $groups = [];
        $agents = [];
        $rules = [];
        $seenRule = false;

        foreach (preg_split('/\R/', $body) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (! str_contains($line, ':')) {
                continue;
            }

            [$key, $value] = array_map('trim', explode(':', $line, 2));
            $keyLower = strtolower($key);

            if ($keyLower === 'user-agent') {
                if ($seenRule && $agents !== []) {
                    $groups[] = ['agents' => $agents, 'rules' => $rules];
                    $agents = [];
                    $rules = [];
                    $seenRule = false;
                }
                $agents[] = strtolower($value);

                continue;
            }

            if ($keyLower === 'disallow' || $keyLower === 'allow') {
                if ($agents === []) {
                    continue;
                }
                $rules[] = [
                    'allow' => $keyLower === 'allow',
                    'path' => $value,
                ];
                $seenRule = true;
            }
        }

        if ($agents !== []) {
            $groups[] = ['agents' => $agents, 'rules' => $rules];
        }

        return $groups;
    }

    private function pathMatches(string $path, string $prefix): bool
    {
        if ($prefix === '/') {
            return true;
        }

        return str_starts_with($path, $prefix);
    }
}

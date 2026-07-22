<?php

namespace Tests;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as FrameworkVerifyCsrfToken;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        $this->forceSqliteTestingDatabase();

        parent::setUp();

        $this->app->detectEnvironment(fn () => 'testing');

        $this->withoutMiddleware(FrameworkVerifyCsrfToken::class);

        $this->withCredentials();

        $this->withHeaders([
            'Origin' => 'http://localhost',
            'Referer' => 'http://localhost',
        ]);
    }

    /**
     * Auto-attach Idempotency-Key for enqueue routes so legacy tests stay valid
     * after R2 made the header required. Explicit headers still win.
     *
     * @param  array<string, string>  $headers
     * @return array<string, mixed>
     */
    public function postJson($uri, array $data = [], array $headers = [], $options = 0)
    {
        if ($this->uriRequiresIdempotencyKey((string) $uri) && ! $this->headersContainIdempotencyKey($headers)) {
            $headers['Idempotency-Key'] = 'test-auto-'.uniqid('', true);
        }

        return parent::postJson($uri, $data, $headers, $options);
    }

    private function uriRequiresIdempotencyKey(string $uri): bool
    {
        $path = parse_url($uri, PHP_URL_PATH) ?? $uri;

        return (bool) preg_match(
            '#/(?:tasks(?:/[^/]+/run-now)?|pipelines/[^/]+/instances)$#',
            $path,
        );
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function headersContainIdempotencyKey(array $headers): bool
    {
        foreach ($headers as $name => $value) {
            if (strcasecmp((string) $name, 'Idempotency-Key') === 0 && is_string($value) && trim($value) !== '') {
                return true;
            }
        }

        return false;
    }

    private function forceSqliteTestingDatabase(): void
    {
        foreach (['DB_CONNECTION', 'DB_DATABASE', 'DB_URL', 'DB_HOST', 'DB_PORT', 'DB_USERNAME', 'DB_PASSWORD'] as $key) {
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);
        }

        putenv('DB_CONNECTION=sqlite');
        putenv('DB_DATABASE=:memory:');
        $_ENV['DB_CONNECTION'] = 'sqlite';
        $_ENV['DB_DATABASE'] = ':memory:';
        $_SERVER['DB_CONNECTION'] = 'sqlite';
        $_SERVER['DB_DATABASE'] = ':memory:';
    }
}

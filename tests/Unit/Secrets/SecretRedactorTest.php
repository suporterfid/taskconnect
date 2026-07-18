<?php

namespace Tests\Unit\Secrets;

use App\Domain\Secrets\SecretRedactor;
use PHPUnit\Framework\TestCase;

class SecretRedactorTest extends TestCase
{
    private SecretRedactor $redactor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->redactor = new SecretRedactor();
    }

    public function test_redacts_authorization_bearer_header(): void
    {
        $redacted = $this->redactor->redactHeaders([
            'Authorization' => 'Bearer super-secret-token',
            'Content-Type' => 'application/json',
        ]);

        $this->assertSame('Bearer [REDACTED]', $redacted['Authorization']);
        $this->assertSame('application/json', $redacted['Content-Type']);
    }

    public function test_redacts_authorization_basic_header(): void
    {
        $redacted = $this->redactor->redactHeaders([
            'Authorization' => 'Basic dXNlcjpwYXNzd29yZA==',
        ]);

        $this->assertSame('Basic [REDACTED]', $redacted['Authorization']);
    }

    public function test_redacts_nested_structured_values_and_query_params(): void
    {
        $redacted = $this->redactor->redact([
            'headers' => [
                'Authorization' => 'Bearer abc123',
            ],
            'query' => [
                'api_key' => 'secret-value',
                'page' => '1',
            ],
            'custom_secret_field' => 'hidden',
        ], ['custom_secret_field']);

        $this->assertSame('Bearer [REDACTED]', $redacted['headers']['Authorization']);
        $this->assertSame('[REDACTED]', $redacted['query']['api_key']);
        $this->assertSame('1', $redacted['query']['page']);
        $this->assertSame('[REDACTED]', $redacted['custom_secret_field']);
    }

    public function test_redacts_secrets_in_urls(): void
    {
        $url = $this->redactor->redactUrl('https://example.com/hook?api_key=secret123&page=2', ['api_key']);

        $this->assertStringContainsString('api_key=%5BREDACTED%5D', $url);
        $this->assertStringContainsString('page=2', $url);
        $this->assertStringNotContainsString('secret123', $url);
    }
}

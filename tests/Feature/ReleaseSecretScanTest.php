<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use Tests\TestCase;

class ReleaseSecretScanTest extends TestCase
{
    public function test_validate_release_fails_when_env_present(): void
    {
        $tree = $this->makeFakeReleaseTree();
        file_put_contents($tree.'/app/.env', "APP_KEY=base64:real-secret-value-here\n");

        [$code, $output] = $this->runValidate($tree);

        $this->assertNotSame(0, $code, $output);
        $this->assertStringContainsString('.env', $output);
    }

    public function test_validate_release_fails_on_private_key_pem(): void
    {
        $tree = $this->makeFakeReleaseTree();
        file_put_contents($tree.'/app/leak.pem', "-----BEGIN PRIVATE KEY-----\nMIIEvgIBADANBg\n-----END PRIVATE KEY-----\n");

        [$code, $output] = $this->runValidate($tree);

        $this->assertNotSame(0, $code, $output);
        $this->assertStringContainsString('private key', strtolower($output));
    }

    public function test_validate_release_passes_clean_tree(): void
    {
        $tree = $this->makeFakeReleaseTree();

        [$code, $output] = $this->runValidate($tree);

        $this->assertSame(0, $code, $output);
        $this->assertStringContainsString('secret-scan=pass', $output);
    }

    /**
     * @return array{0: int, 1: string}
     */
    private function runValidate(string $tree): array
    {
        $script = base_path('scripts/validate-release.sh');
        $cmd = 'bash '.escapeshellarg($script).' '.escapeshellarg($tree).' 2>&1';
        $output = [];
        $code = 0;
        exec($cmd, $output, $code);

        return [$code, implode("\n", $output)];
    }

    private function makeFakeReleaseTree(): string
    {
        $root = sys_get_temp_dir().'/tc-release-scan-'.uniqid('', true);
        $app = $root.'/app';
        mkdir($app.'/public/build', 0777, true);
        mkdir($app.'/vendor', 0777, true);
        file_put_contents($app.'/artisan', "#!/usr/bin/env php\n<?php\n");
        file_put_contents($app.'/public/build/manifest.json', '{}');

        return $root;
    }
}

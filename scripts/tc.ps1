#requires -Version 5.1
Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$RootDir = Split-Path -Parent $PSScriptRoot
Set-Location $RootDir

$Compose = @('docker', 'compose')
$ComposeFiles = @('-f', 'compose.yaml')

if ($env:TC_CI -eq '1' -or $env:CI -eq 'true' -or $env:GITHUB_ACTIONS -eq 'true') {
    $ComposeFiles += @('-f', 'compose.ci.yaml')
}

function Invoke-ComposeCore {
    param(
        [Parameter(ValueFromRemainingArguments = $true)]
        [string[]]$Args
    )
    & $Compose @ComposeFiles @Args
    return $LASTEXITCODE
}

function Invoke-Compose {
    param(
        [Parameter(ValueFromRemainingArguments = $true)]
        [string[]]$Args
    )
    $exitCode = Invoke-ComposeCore @Args
    if ($exitCode -ne 0) {
        throw "docker compose failed with exit code $exitCode"
    }
}

function Write-PackagistWarning {
    if ($env:COMPOSER_PACKAGIST_URL) {
        Write-Warning "COMPOSER_PACKAGIST_URL is set ($($env:COMPOSER_PACKAGIST_URL)). Custom Packagist mirrors can cause stale or incomplete installs."
    }
}

function Get-ComposerEnvArgs {
    $args = @()
    if ($env:COMPOSER_PACKAGIST_URL) {
        $args += @('-e', "COMPOSER_PACKAGIST_URL=$($env:COMPOSER_PACKAGIST_URL)")
    }
    return $args
}

function Invoke-ComposerWithRetry {
    param(
        [Parameter(ValueFromRemainingArguments = $true)]
        [string[]]$ComposerArgs
    )

    Write-PackagistWarning
    $envArgs = Get-ComposerEnvArgs
    $maxAttempts = 3
    $attempt = 1
    $delaySeconds = 5

    while ($attempt -le $maxAttempts) {
        $exitCode = Invoke-ComposeCore @('run', '--rm') + $envArgs + @('app', 'composer') + $ComposerArgs
        if ($exitCode -eq 0) {
            return
        }

        if ($attempt -ge $maxAttempts) {
            throw "Composer failed after $maxAttempts attempts."
        }

        Write-Warning "Composer attempt $attempt failed; retrying in ${delaySeconds}s..."
        Start-Sleep -Seconds $delaySeconds
        $delaySeconds *= 2
        $attempt++
    }
}

function Show-Usage {
    @'
TaskConnect Docker toolchain

Usage:
  .\scripts\tc.ps1 <verb> [args...]

Verbs:
  up           Start core services (app, mysql, mailpit, receiver)
  down         Stop and remove containers
  bootstrap    Install dependencies, prepare env, migrate database
  composer     Run composer via app container
  artisan      Run artisan via app container
  npm          Run npm via node container (dev profile)
  test         Run PHPUnit/Pest test suite
  e2e          Run end-to-end test suite
  release      Build production release zip into dist/
  deploy       Build release and publish over FTP(S)+SSH (deploy.config.json)
  shell        Open shell in app container
  help         Show this help
'@ | Write-Output
}

function Invoke-Up {
    Invoke-Compose @('up', '-d', '--build', 'mysql', 'mailpit', 'receiver', 'app')
}

function Invoke-Down {
    param([string[]]$Args)
    Invoke-Compose @('down') + $Args
}

function Invoke-Bootstrap {
    if (-not (Test-Path '.env')) {
        Copy-Item '.env.example' '.env'
        Write-Output 'Created .env from .env.example'
    }

    Invoke-Compose @('up', '-d', '--build', 'mysql', 'mailpit', 'receiver')
    Invoke-Compose @('up', '-d', '--wait', 'mysql')

    Invoke-ComposerWithRetry @('install')

    $hasArtisan = (Invoke-ComposeCore @('run', '--rm', 'app', 'test', '-f', 'artisan')) -eq 0
    if ($hasArtisan) {
        Invoke-Compose @('run', '--rm', 'app', 'php', 'artisan', 'key:generate', '--force')
        Invoke-Compose @('run', '--rm', 'app', 'php', 'artisan', 'migrate', '--force')
    }
    else {
        Write-Output 'Laravel not scaffolded yet; skipping artisan bootstrap steps.'
    }

    if (Test-Path 'package.json') {
        $npmExit = Invoke-ComposeCore @('--profile', 'dev', 'run', '--rm', 'node', 'npm', 'ci')
        if ($npmExit -ne 0) {
            Invoke-Compose @('--profile', 'dev', 'run', '--rm', 'node', 'npm', 'install')
        }
    }

    Invoke-Compose @('up', '-d', '--build', 'app')
    Write-Output 'Bootstrap complete.'
}

function Invoke-Composer {
    param([string[]]$Args)

    if ($Args.Count -gt 0 -and $Args[0] -eq 'install') {
        $installArgs = if ($Args.Count -gt 1) { $Args[1..($Args.Count - 1)] } else { @() }
        Invoke-ComposerWithRetry @('install') + $installArgs
        return
    }

    Write-PackagistWarning
    $envArgs = Get-ComposerEnvArgs
    Invoke-Compose @('run', '--rm') + $envArgs + @('app', 'composer') + $Args
}

function Invoke-Artisan {
    param([string[]]$Args)
    Invoke-Compose @('run', '--rm', 'app', 'php', 'artisan') + $Args
}

function Invoke-Npm {
    param([string[]]$Args)
    Invoke-Compose @('--profile', 'dev', 'run', '--rm', '--service-ports', 'node', 'npm') + $Args
}

function Invoke-Test {
    param([string[]]$Args)

    if ((Invoke-ComposeCore @('run', '--rm', 'app', 'test', '-f', 'artisan')) -eq 0) {
        Invoke-Compose @('run', '--rm', 'app', 'php', 'artisan', 'test') + $Args
        return
    }

    if ((Invoke-ComposeCore @('run', '--rm', 'app', 'test', '-f', 'vendor/bin/pest')) -eq 0) {
        Invoke-Compose @('run', '--rm', 'app', 'vendor/bin/pest') + $Args
        return
    }

    if ((Invoke-ComposeCore @('run', '--rm', 'app', 'test', '-f', 'vendor/bin/phpunit')) -eq 0) {
        Invoke-Compose @('run', '--rm', 'app', 'vendor/bin/phpunit') + $Args
        return
    }

    throw 'No test runner found. Scaffold Laravel or install dev dependencies first.'
}

function Invoke-E2E {
    param([string[]]$Args)

    if (-not (Test-Path 'package.json')) {
        throw 'No package.json found.'
    }

    $scripts = & $Compose @ComposeFiles @('--profile', 'dev', 'run', '--rm', 'node', 'npm', 'run') 2>$null
    if ($scripts -match '(?m)^  e2e$') {
        Invoke-Compose @('--profile', 'dev', 'run', '--rm', '--service-ports', 'node', 'npm', 'run', 'e2e', '--') + $Args
        return
    }

    throw 'No e2e script defined in package.json.'
}

function Invoke-Release {
    New-Item -ItemType Directory -Force -Path 'dist' | Out-Null
    docker build -f docker/release/Dockerfile --target export --output "type=local,dest=./dist" .
    if ($LASTEXITCODE -ne 0) {
        throw "Release build failed with exit code $LASTEXITCODE"
    }
    Write-Output 'Release artifact written to dist/'
}

function Invoke-Deploy {
    param([string[]]$Args)

    $config = if ($Args.Count -gt 0) { $Args[0] } else { 'deploy.config.json' }

    if (-not (Test-Path $config)) {
        Write-Error "Deploy config '$config' not found. Copy deploy.config.example.json to deploy.config.json and fill in your credentials."
        exit 1
    }

    Write-Output 'Building production release tree (dist/app)...'
    if (Test-Path 'dist/app') {
        # Exported vendor files can be read-only; clear the attribute before removal.
        Get-ChildItem 'dist/app' -Recurse -Force | ForEach-Object { $_.Attributes = 'Normal' }
        Remove-Item -Recurse -Force 'dist/app'
    }
    New-Item -ItemType Directory -Force -Path 'dist' | Out-Null
    docker build -f docker/release/Dockerfile --target export --output "type=local,dest=./dist" .
    if ($LASTEXITCODE -ne 0) { throw "Release build failed with exit code $LASTEXITCODE" }

    Write-Output 'Building deploy image...'
    docker build -f docker/deploy/Dockerfile -t taskconnect-deploy .
    if ($LASTEXITCODE -ne 0) { throw "Deploy image build failed with exit code $LASTEXITCODE" }

    Write-Output 'Publishing to remote host...'
    $remote = "tr -d '\r' < scripts/deploy.sh > /tmp/deploy.sh && bash /tmp/deploy.sh '$config'"
    docker run --rm -v "${RootDir}:/work" -w /work taskconnect-deploy -c $remote
    if ($LASTEXITCODE -ne 0) { throw "Deployment failed with exit code $LASTEXITCODE" }
}

function Invoke-Shell {
    Invoke-Compose @('run', '--rm', 'app', 'bash')
}

$Verb = if ($args.Count -gt 0) { $args[0] } else { 'help' }
$VerbArgs = if ($args.Count -gt 1) { $args[1..($args.Count - 1)] } else { @() }

switch ($Verb) {
    'up' { Invoke-Up }
    'down' { Invoke-Down -Args $VerbArgs }
    'bootstrap' { Invoke-Bootstrap }
    'composer' { Invoke-Composer -Args $VerbArgs }
    'artisan' { Invoke-Artisan -Args $VerbArgs }
    'npm' { Invoke-Npm -Args $VerbArgs }
    'test' { Invoke-Test -Args $VerbArgs }
    'e2e' { Invoke-E2E -Args $VerbArgs }
    'release' { Invoke-Release }
    'deploy' { Invoke-Deploy -Args $VerbArgs }
    'shell' { Invoke-Shell }
    { $_ -in @('help', '-h', '--help') } { Show-Usage }
    default {
        Write-Error "Unknown verb: $Verb"
        Show-Usage
        exit 1
    }
}

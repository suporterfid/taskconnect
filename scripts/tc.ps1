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
        [string[]]$CoreArgs
    )
    & $Compose[0] $Compose[1] @ComposeFiles @CoreArgs | Out-Host
    return $LASTEXITCODE
}

function Invoke-Compose {
    param(
        [Parameter(ValueFromRemainingArguments = $true)]
        [string[]]$ComposeArgs
    )
    $exitCode = Invoke-ComposeCore -CoreArgs $ComposeArgs
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
        $exitCode = Invoke-ComposeCore -CoreArgs (@('run', '--rm') + $envArgs + @('app', 'composer') + $ComposerArgs)
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
    Invoke-Compose -ComposeArgs @('up', '-d', '--build', 'mysql', 'mailpit', 'receiver', 'app')
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

    Invoke-ComposerWithRetry -ComposerArgs @('install')

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
    param([string[]]$ComposerArgs)

    if ($ComposerArgs.Count -gt 0 -and $ComposerArgs[0] -eq 'install') {
        $installArgs = if ($ComposerArgs.Count -gt 1) { $ComposerArgs[1..($ComposerArgs.Count - 1)] } else { @() }
        Invoke-ComposerWithRetry -ComposerArgs (@('install') + $installArgs)
        return
    }

    Write-PackagistWarning
    $envArgs = Get-ComposerEnvArgs
    Invoke-Compose -ComposeArgs (@('run', '--rm') + $envArgs + @('app', 'composer') + $ComposerArgs)
}

function Invoke-Artisan {
    param([string[]]$ArtisanArgs)
    Invoke-Compose -ComposeArgs (@('run', '--rm', 'app', 'php', 'artisan') + $ArtisanArgs)
}

function Invoke-Npm {
    param([string[]]$NpmArgs)
    $composeArgs = @('--profile', 'dev', 'run', '--rm', '--build', '--service-ports', 'node', 'npm') + $NpmArgs
    Invoke-Compose -ComposeArgs $composeArgs
}

function Invoke-Test {
    param([string[]]$TestArgs)

    if ((Invoke-ComposeCore -CoreArgs @('run', '--rm', 'app', 'test', '-f', 'artisan')) -eq 0) {
        Invoke-Compose -ComposeArgs (@('run', '--rm', 'app', 'php', 'artisan', 'test') + $TestArgs)
        return
    }

    if ((Invoke-ComposeCore -CoreArgs @('run', '--rm', 'app', 'test', '-f', 'vendor/bin/pest')) -eq 0) {
        Invoke-Compose -ComposeArgs (@('run', '--rm', 'app', 'vendor/bin/pest') + $TestArgs)
        return
    }

    if ((Invoke-ComposeCore -CoreArgs @('run', '--rm', 'app', 'test', '-f', 'vendor/bin/phpunit')) -eq 0) {
        Invoke-Compose -ComposeArgs (@('run', '--rm', 'app', 'vendor/bin/phpunit') + $TestArgs)
        return
    }

    throw 'No test runner found. Scaffold Laravel or install dev dependencies first.'
}

function Invoke-E2E {
    param([string[]]$E2EArgs)

    if (-not (Test-Path 'package.json')) {
        throw 'No package.json found.'
    }

    $pkg = Get-Content -Raw 'package.json'
    if ($pkg -notmatch '"e2e"\s*:') {
        throw 'No e2e script defined in package.json.'
    }

    $envArgs = @(
        '-e', "E2E_EMAIL=$env:E2E_EMAIL",
        '-e', "E2E_PASSWORD=$env:E2E_PASSWORD",
        '-e', "PLAYWRIGHT_BASE_URL=$(if ($env:PLAYWRIGHT_BASE_URL) { $env:PLAYWRIGHT_BASE_URL } else { 'http://app' })"
    )
    Invoke-Compose -ComposeArgs (@('--profile', 'dev', 'run', '--rm', '--build', '--service-ports') + $envArgs + @('node', 'npm', '--prefix', 'frontend', 'run', 'e2e', '--') + $E2EArgs)
}

function Clear-ReleaseOutput {
    $distPath = [IO.Path]::GetFullPath((Join-Path $RootDir 'dist'))
    $rootPath = [IO.Path]::GetFullPath($RootDir)
    if ([IO.Path]::GetDirectoryName($distPath) -ne $rootPath -or [IO.Path]::GetFileName($distPath) -ne 'dist') {
        throw "Refusing to clean unexpected release directory: $distPath"
    }

    if (Test-Path -LiteralPath $distPath) {
        $distItem = Get-Item -LiteralPath $distPath -Force -ErrorAction Stop
        if (($distItem.Attributes -band [IO.FileAttributes]::ReparsePoint) -ne 0) {
            throw "Refusing to clean reparse-point release directory: $distPath"
        }
    } else {
        New-Item -ItemType Directory -Path $distPath -ErrorAction Stop | Out-Null
    }

    foreach ($targetName in @('app', 'taskconnect-release.zip', 'taskconnect-release.zip.sha256')) {
        $targetPath = [IO.Path]::GetFullPath((Join-Path $distPath $targetName))
        if ([IO.Path]::GetDirectoryName($targetPath) -ne $distPath -or -not (Test-Path -LiteralPath $targetPath)) {
            continue
        }

        $targetItem = Get-Item -LiteralPath $targetPath -Force -ErrorAction Stop
        if (($targetItem.Attributes -band [IO.FileAttributes]::ReparsePoint) -ne 0) {
            throw "Refusing to remove release reparse point: $targetPath"
        }
        if ($targetItem.PSIsContainer) {
            $nestedReparsePoints = @(Get-ChildItem -LiteralPath $targetPath -Recurse -Force -ErrorAction Stop |
                Where-Object { ($_.Attributes -band [IO.FileAttributes]::ReparsePoint) -ne 0 })
            if ($nestedReparsePoints.Count -gt 0) {
                throw "Refusing to remove release tree containing reparse points: $targetPath"
            }
            Get-ChildItem -LiteralPath $targetPath -Recurse -Force -ErrorAction Stop |
                ForEach-Object { $_.Attributes = 'Normal' }
        }
        $targetItem.Attributes = 'Normal'
        Remove-Item -LiteralPath $targetPath -Recurse -Force -ErrorAction Stop
    }
}

function Invoke-Release {
    Clear-ReleaseOutput
    docker build -f docker/release/Dockerfile --target export --output "type=local,dest=./dist" .
    if ($LASTEXITCODE -ne 0) {
        throw "Release build failed with exit code $LASTEXITCODE"
    }
    Write-Output 'Release artifact written to dist/'
    Invoke-Compose -ComposeArgs @('run', '--rm', '--no-deps', 'app', 'bash', 'scripts/validate-release.sh', 'dist')
}

function Invoke-Deploy {
    param([string[]]$Args)

    $config = if ($Args.Count -gt 0) { $Args[0] } else { 'deploy.config.json' }

    if (-not (Test-Path $config)) {
        Write-Error "Deploy config '$config' not found. Copy deploy.config.example.json to deploy.config.json and fill in your credentials."
        exit 1
    }

    Write-Output 'Building production release tree (dist/app)...'
    Clear-ReleaseOutput
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
    'composer' { Invoke-Composer -ComposerArgs $VerbArgs }
    'artisan' { Invoke-Artisan -ArtisanArgs $VerbArgs }
    'npm' { Invoke-Npm -NpmArgs $VerbArgs }
    'test' { Invoke-Test -TestArgs $VerbArgs }
    'e2e' { Invoke-E2E -E2EArgs $VerbArgs }
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

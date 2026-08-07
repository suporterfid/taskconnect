import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'

import { describe, expect, it } from 'vitest'

const repositoryRoot = resolve(import.meta.dirname, '../..')

describe('Docker-only browser toolchain', () => {
  it('pins a Playwright image matching the package runtime', () => {
    const dockerfile = readFileSync(resolve(repositoryRoot, 'docker/node/Dockerfile'), 'utf8')
    const releaseDockerfile = readFileSync(resolve(repositoryRoot, 'docker/release/Dockerfile'), 'utf8')
    const releaseValidator = readFileSync(resolve(repositoryRoot, 'scripts/validate-release.sh'), 'utf8')
    const wrapper = readFileSync(resolve(repositoryRoot, 'scripts/tc.ps1'), 'utf8')
    const packageJson = JSON.parse(
      readFileSync(resolve(repositoryRoot, 'frontend/package.json'), 'utf8'),
    ) as { devDependencies: Record<string, string> }

    expect(packageJson.devDependencies['@playwright/test']).toBe('^1.61.1')
    expect(dockerfile).toMatch(
      /^FROM mcr\.microsoft\.com\/playwright:v1\.61\.1-noble$/m,
    )
    expect(wrapper).toContain("@('--profile', 'dev', 'run', '--rm', '--build', '--service-ports', 'node', 'npm')")
    expect(wrapper).toContain("Invoke-Compose -ComposeArgs (@('--profile', 'dev', 'run', '--rm', '--build', '--service-ports')")
    expect(wrapper).toContain("@('node', 'npm', '--prefix', 'frontend', 'run', 'e2e', '--')")
    expect(wrapper).toContain('param([string[]]$E2EArgs)')
    expect(wrapper).toContain("'e2e' { Invoke-E2E -E2EArgs $VerbArgs }")
    expect(wrapper).toContain("Invoke-Compose -ComposeArgs @('up', '-d', '--build', 'mysql', 'mailpit', 'receiver', 'app')")
    expect(wrapper).toContain("Invoke-Compose -ComposeArgs @('run', '--rm', '--no-deps', 'app', 'bash', 'scripts/validate-release.sh', 'dist')")
    expect(releaseDockerfile).toContain('sha256sum taskconnect-release.zip > taskconnect-release.zip.sha256')
    expect(releaseValidator).toContain('$APP/public/build/.vite/manifest.json')
  })

  it('keeps browser evidence outside version control', () => {
    const gitignore = readFileSync(resolve(repositoryRoot, '.gitignore'), 'utf8')
    expect(gitignore).toMatch(/^\/output\/playwright\/$/m)
  })

  it('allows the Docker browser origin to override session and Sanctum hosts', () => {
    const compose = readFileSync(resolve(repositoryRoot, 'compose.yaml'), 'utf8')
    expect(compose).toContain('SESSION_DOMAIN: ${SESSION_DOMAIN:-localhost}')
    expect(compose).toContain('SANCTUM_STATEFUL_DOMAINS: ${SANCTUM_STATEFUL_DOMAINS:-localhost,localhost:8080,localhost:5173,127.0.0.1,127.0.0.1:8080,127.0.0.1:5173}')
  })

  it('treats the internal Docker HTTP origin as secure for browser crypto APIs', () => {
    const config = readFileSync(resolve(repositoryRoot, 'frontend/playwright.config.ts'), 'utf8')
    expect(config).toContain('--unsafely-treat-insecure-origin-as-secure=http://app')
  })

  it('marks API fixture requests as stateful Sanctum traffic', () => {
    const seedHelper = readFileSync(resolve(repositoryRoot, 'frontend/e2e/helpers/seed.ts'), 'utf8')
    expect(seedHelper).toContain("Origin: new URL(baseURL).origin")
    expect(seedHelper).toContain("Referer: `${new URL(baseURL).origin}/`")
  })
})

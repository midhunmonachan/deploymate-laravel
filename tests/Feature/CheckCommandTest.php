<?php

use Illuminate\Filesystem\Filesystem;

it('fails on invalid schema', function (): void {
    (new Filesystem())->put($this->deployYamlPath(), "version: 2\n");

    $this->artisan('deploymate:check')->assertExitCode(1);
});

it('passes on valid manifest', function (): void {
    (new Filesystem())->put($this->deployYamlPath(), implode("\n", [
        'version: 1',
        'instances:',
        '  - domain: "example.com"',
        '    env: production',
        'defaults:',
        '  enabled: [https]',
        '',
    ]));

    $this->artisan('deploymate:check')->assertExitCode(0);
});

it('does not require enabled order', function (): void {
    (new Filesystem())->put($this->deployYamlPath(), implode("\n", [
        'version: 1',
        'instances:',
        '  - domain: "example.com"',
        '    env: production',
        'defaults:',
        '  enabled: [redis, https]',
        '',
    ]));

    $this->artisan('deploymate:check')->assertExitCode(0);
});

it('fails if YAML contains duplicate keys', function (): void {
    (new Filesystem())->put($this->deployYamlPath(), implode("\n", [
        'version: 1',
        'version: 1',
        'instances:',
        '  - domain: "example.com"',
        '    env: production',
        'defaults:',
        '  enabled: [https]',
        '',
    ]));

    $this->artisan('deploymate:check')
        ->expectsOutputToContain("{$this->deployYamlPath()}:2: duplicate key 'version' at top level.")
        ->assertExitCode(1);
});

it('fails on extra top-level keys', function (): void {
    (new Filesystem())->put($this->deployYamlPath(), implode("\n", [
        'version: 1',
        'instances:',
        '  - domain: "example.com"',
        '    env: production',
        'defaults:',
        '  enabled: [https]',
        'extra: true',
        '',
    ]));

    $this->artisan('deploymate:check')
        ->expectsOutputToContain('keys must be exactly {version, instances, defaults}')
        ->assertExitCode(1);
});

it('fails on empty instances', function (): void {
    (new Filesystem())->put($this->deployYamlPath(), implode("\n", [
        'version: 1',
        'instances: []',
        'defaults:',
        '  enabled: [https]',
        '',
    ]));

    $this->artisan('deploymate:check')
        ->expectsOutputToContain('instances must be a non-empty list')
        ->assertExitCode(1);
});

it('fails on duplicate domains', function (): void {
    (new Filesystem())->put($this->deployYamlPath(), implode("\n", [
        'version: 1',
        'instances:',
        '  - domain: "example.com"',
        '    env: production',
        '  - domain: "EXAMPLE.COM"',
        '    env: staging',
        'defaults:',
        '  enabled: [https]',
        '',
    ]));

    $this->artisan('deploymate:check')
        ->expectsOutputToContain('domain must be unique')
        ->assertExitCode(1);
});

it('fails on multiple production env instances', function (): void {
    (new Filesystem())->put($this->deployYamlPath(), implode("\n", [
        'version: 1',
        'instances:',
        '  - domain: "a.example.com"',
        '    env: production',
        '  - domain: "b.example.com"',
        '    env: production',
        'defaults:',
        '  enabled: [https]',
        '',
    ]));

    $this->artisan('deploymate:check')
        ->expectsOutputToContain('only one instance may have env=production')
        ->assertExitCode(1);
});

it('fails on invalid hostname', function (): void {
    (new Filesystem())->put($this->deployYamlPath(), implode("\n", [
        'version: 1',
        'instances:',
        '  - domain: "https://example.com"',
        '    env: production',
        'defaults:',
        '  enabled: [https]',
        '',
    ]));

    $this->artisan('deploymate:check')
        ->expectsOutputToContain('domain must be a valid hostname')
        ->assertExitCode(1);
});

it('fails on invalid enabled entry', function (): void {
    (new Filesystem())->put($this->deployYamlPath(), implode("\n", [
        'version: 1',
        'instances:',
        '  - domain: "example.com"',
        '    env: production',
        'defaults:',
        '  enabled: [https, unknown]',
        '',
    ]));

    $this->artisan('deploymate:check')
        ->expectsOutputToContain('defaults.enabled[1] is invalid (unknown)')
        ->assertExitCode(1);
});

it('fails on duplicate enabled entries', function (): void {
    (new Filesystem())->put($this->deployYamlPath(), implode("\n", [
        'version: 1',
        'instances:',
        '  - domain: "example.com"',
        '    env: production',
        'defaults:',
        '  enabled: [https, https]',
        '',
    ]));

    $this->artisan('deploymate:check')
        ->expectsOutputToContain('defaults.enabled must not contain duplicates')
        ->assertExitCode(1);
});

it('fails if both mysql and postgresql are enabled', function (): void {
    (new Filesystem())->put($this->deployYamlPath(), implode("\n", [
        'version: 1',
        'instances:',
        '  - domain: "example.com"',
        '    env: production',
        'defaults:',
        '  enabled: [https, mysql, postgresql]',
        '',
    ]));

    $this->artisan('deploymate:check')
        ->expectsOutputToContain('must not contain both mysql and postgresql')
        ->assertExitCode(1);
});

it('supports validating a custom path', function (): void {
    $path = $this->app->basePath('config/deploy.yaml');
    (new Filesystem())->ensureDirectoryExists(dirname($path));
    (new Filesystem())->put($path, implode("\n", [
        'version: 1',
        'instances:',
        '  - domain: "example.com"',
        '    env: production',
        'defaults:',
        '  enabled: [https]',
        '',
    ]));

    $this->artisan('deploymate:check', ['--path' => 'config/deploy.yaml'])->assertExitCode(0);
});

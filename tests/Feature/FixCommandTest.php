<?php

use Illuminate\Filesystem\Filesystem;

it('fixes common issues and rewrites deploy.yaml', function (): void {
    $original = implode("\n", [
        'version: 1',
        'instances:',
        '  - domain: "Example.COM"',
        '    env: production',
        '    extra: true',
        '  - domain: "example.com"',
        '    env: staging',
        'defaults:',
        '  enabled: [redis, https, redis, unknown, mysql, postgresql]',
        '  foo: bar',
        'extra: true',
        '',
    ]);

    (new Filesystem())->put($this->deployYamlPath(), $original);

    $this->artisan('deploymate:fix')->assertExitCode(0);

    $expected = implode("\n", [
        'version: 1',
        'instances:',
        '  - domain: "example.com"',
        '    env: production',
        'defaults:',
        '  enabled: [https, mysql, redis]',
        '',
    ]);

    expect(file_get_contents($this->deployYamlPath()))->toBe($expected);
});

it('supports dry-run without writing', function (): void {
    $original = implode("\n", [
        'version: 1',
        'instances:',
        '  - domain: "Example.COM"',
        '    env: production',
        'defaults:',
        '  enabled: [redis, https]',
        '',
    ]);

    (new Filesystem())->put($this->deployYamlPath(), $original);

    $this->artisan('deploymate:fix', ['--dry-run' => true])
        ->expectsOutputToContain('enabled: [https, redis]')
        ->assertExitCode(0);

    expect(file_get_contents($this->deployYamlPath()))->toBe($original);
});

it('fails when multiple production instances exist', function (): void {
    $original = implode("\n", [
        'version: 1',
        'instances:',
        '  - domain: "a.example.com"',
        '    env: production',
        '  - domain: "b.example.com"',
        '    env: production',
        'defaults:',
        '  enabled: [https]',
        '',
    ]);

    (new Filesystem())->put($this->deployYamlPath(), $original);

    $this->artisan('deploymate:fix')
        ->expectsOutputToContain('only one instance may have env=production')
        ->assertExitCode(1);

    expect(file_get_contents($this->deployYamlPath()))->toBe($original);
});

it('supports fixing a custom path', function (): void {
    $path = $this->app->basePath('config/deploy.yaml');
    (new Filesystem())->ensureDirectoryExists(dirname($path));

    (new Filesystem())->put($path, implode("\n", [
        'version: 1',
        'instances:',
        '  - domain: "Example.COM"',
        '    env: production',
        'defaults:',
        '  enabled: [redis, https]',
        '',
    ]));

    $this->artisan('deploymate:fix', ['--path' => 'config/deploy.yaml'])->assertExitCode(0);

    $expected = implode("\n", [
        'version: 1',
        'instances:',
        '  - domain: "example.com"',
        '    env: production',
        'defaults:',
        '  enabled: [https, redis]',
        '',
    ]);

    expect(file_get_contents($path))->toBe($expected);
});


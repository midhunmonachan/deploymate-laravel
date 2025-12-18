<?php

use Illuminate\Filesystem\Filesystem;

it('writes expected YAML', function (): void {
    $this->artisan('deploymate:init')
        ->expectsQuestion('First domain (hostname only)', 'example.com')
        ->expectsChoice('Environment', 'production', ['production', 'staging', 'test', 'custom'])
        ->expectsConfirmation('Add another domain?', 'yes')
        ->expectsQuestion('Domain (hostname only)', 'staging.example.com')
        ->expectsChoice('Environment', 'staging', ['staging', 'test', 'custom'])
        ->expectsConfirmation('Add another domain?', 'no')
        ->expectsChoice('Database', 'postgresql', ['sqlite', 'postgresql', 'mysql'])
        ->expectsChoice('Cache', 'redis', ['database', 'redis', 'file'])
        ->expectsChoice('Session', 'database', ['database', 'redis', 'file', 'cookie'])
        ->expectsChoice('Queue connection', 'redis', ['database', 'redis', 'sync'])
        ->expectsChoice('HTTP runtime', 'fpm', ['fpm', 'octane'])
        ->expectsChoice('Run queue worker process on server', 'on', ['off', 'on'])
        ->expectsChoice('Run scheduler process on server', 'off', ['off', 'on'])
        ->expectsChoice('HTTPS automation', 'on', ['on', 'off'])
        ->assertExitCode(0);

    $expected = implode("\n", [
        'version: 1',
        'instances:',
        '  - domain: "example.com"',
        '    env: production',
        '  - domain: "staging.example.com"',
        '    env: staging',
        'defaults:',
        '  enabled: [https, postgresql, redis, queue]',
        '',
    ]);

    expect(file_get_contents($this->deployYamlPath()))->toBe($expected);
});

it('aborts if deploy.yaml exists and abort is selected', function (): void {
    (new Filesystem())->put($this->deployYamlPath(), "old: true\n");

    $this->artisan('deploymate:init')
        ->expectsChoice(
            'deploy.yaml already exists. What do you want to do?',
            'abort',
            [
                'abort' => 'Abort',
                'overwrite' => 'Overwrite',
                'backup' => 'Backup + Overwrite (creates deploy.yaml.bak)',
            ],
        )
        ->assertExitCode(0);

    expect(file_get_contents($this->deployYamlPath()))->toBe("old: true\n");
    expect(file_exists($this->deployYamlBakPath()))->toBeFalse();
});

it('overwrites deploy.yaml if overwrite is selected', function (): void {
    (new Filesystem())->put($this->deployYamlPath(), "old: true\n");

    $this->artisan('deploymate:init')
        ->expectsChoice(
            'deploy.yaml already exists. What do you want to do?',
            'overwrite',
            [
                'abort' => 'Abort',
                'overwrite' => 'Overwrite',
                'backup' => 'Backup + Overwrite (creates deploy.yaml.bak)',
            ],
        )
        ->expectsQuestion('First domain (hostname only)', 'example.com')
        ->expectsChoice('Environment', 'production', ['production', 'staging', 'test', 'custom'])
        ->expectsConfirmation('Add another domain?', 'no')
        ->expectsChoice('Database', 'sqlite', ['sqlite', 'postgresql', 'mysql'])
        ->expectsChoice('Cache', 'database', ['database', 'redis', 'file'])
        ->expectsChoice('Session', 'database', ['database', 'redis', 'file', 'cookie'])
        ->expectsChoice('Queue connection', 'database', ['database', 'redis', 'sync'])
        ->expectsChoice('HTTP runtime', 'fpm', ['fpm', 'octane'])
        ->expectsChoice('Run queue worker process on server', 'off', ['off', 'on'])
        ->expectsChoice('Run scheduler process on server', 'off', ['off', 'on'])
        ->expectsChoice('HTTPS automation', 'on', ['on', 'off'])
        ->assertExitCode(0);

    $expected = implode("\n", [
        'version: 1',
        'instances:',
        '  - domain: "example.com"',
        '    env: production',
        'defaults:',
        '  enabled: [https]',
        '',
    ]);

    expect(file_get_contents($this->deployYamlPath()))->toBe($expected);
    expect(file_exists($this->deployYamlBakPath()))->toBeFalse();
});

it('backs up and overwrites deploy.yaml if backup is selected', function (): void {
    (new Filesystem())->put($this->deployYamlPath(), "old: true\n");

    $this->artisan('deploymate:init')
        ->expectsChoice(
            'deploy.yaml already exists. What do you want to do?',
            'backup',
            [
                'abort' => 'Abort',
                'overwrite' => 'Overwrite',
                'backup' => 'Backup + Overwrite (creates deploy.yaml.bak)',
            ],
        )
        ->expectsQuestion('First domain (hostname only)', 'example.com')
        ->expectsChoice('Environment', 'production', ['production', 'staging', 'test', 'custom'])
        ->expectsConfirmation('Add another domain?', 'no')
        ->expectsChoice('Database', 'sqlite', ['sqlite', 'postgresql', 'mysql'])
        ->expectsChoice('Cache', 'database', ['database', 'redis', 'file'])
        ->expectsChoice('Session', 'database', ['database', 'redis', 'file', 'cookie'])
        ->expectsChoice('Queue connection', 'database', ['database', 'redis', 'sync'])
        ->expectsChoice('HTTP runtime', 'fpm', ['fpm', 'octane'])
        ->expectsChoice('Run queue worker process on server', 'off', ['off', 'on'])
        ->expectsChoice('Run scheduler process on server', 'off', ['off', 'on'])
        ->expectsChoice('HTTPS automation', 'on', ['on', 'off'])
        ->assertExitCode(0);

    $expected = implode("\n", [
        'version: 1',
        'instances:',
        '  - domain: "example.com"',
        '    env: production',
        'defaults:',
        '  enabled: [https]',
        '',
    ]);

    expect(file_get_contents($this->deployYamlBakPath()))->toBe("old: true\n");
    expect(file_get_contents($this->deployYamlPath()))->toBe($expected);
});

it('supports writing to a custom path', function (): void {
    $path = $this->app->basePath('config/deploy.yaml');
    (new Filesystem())->ensureDirectoryExists(dirname($path));
    (new Filesystem())->delete($path);

    $this->artisan('deploymate:init', ['--path' => 'config/deploy.yaml'])
        ->expectsQuestion('First domain (hostname only)', 'example.com')
        ->expectsChoice('Environment', 'production', ['production', 'staging', 'test', 'custom'])
        ->expectsConfirmation('Add another domain?', 'no')
        ->expectsChoice('Database', 'sqlite', ['sqlite', 'postgresql', 'mysql'])
        ->expectsChoice('Cache', 'database', ['database', 'redis', 'file'])
        ->expectsChoice('Session', 'database', ['database', 'redis', 'file', 'cookie'])
        ->expectsChoice('Queue connection', 'database', ['database', 'redis', 'sync'])
        ->expectsChoice('HTTP runtime', 'fpm', ['fpm', 'octane'])
        ->expectsChoice('Run queue worker process on server', 'off', ['off', 'on'])
        ->expectsChoice('Run scheduler process on server', 'off', ['off', 'on'])
        ->expectsChoice('HTTPS automation', 'on', ['on', 'off'])
        ->assertExitCode(0);

    $expected = implode("\n", [
        'version: 1',
        'instances:',
        '  - domain: "example.com"',
        '    env: production',
        'defaults:',
        '  enabled: [https]',
        '',
    ]);

    expect(file_get_contents($path))->toBe($expected);
});

it('fails fast in non-interactive mode', function (): void {
    $this->artisan('deploymate:init', ['--no-interaction' => true])
        ->expectsOutputToContain('requires an interactive terminal')
        ->assertExitCode(1);
});

it('prevents selecting production for a second instance', function (): void {
    $this->artisan('deploymate:init')
        ->expectsQuestion('First domain (hostname only)', 'example.com')
        ->expectsChoice('Environment', 'production', ['production', 'staging', 'test', 'custom'], strict: true)
        ->expectsConfirmation('Add another domain?', 'yes')
        ->expectsQuestion('Domain (hostname only)', 'staging.example.com')
        ->expectsChoice('Environment', 'staging', ['staging', 'test', 'custom'], strict: true)
        ->expectsConfirmation('Add another domain?', 'no')
        ->expectsChoice('Database', 'sqlite', ['sqlite', 'postgresql', 'mysql'])
        ->expectsChoice('Cache', 'database', ['database', 'redis', 'file'])
        ->expectsChoice('Session', 'database', ['database', 'redis', 'file', 'cookie'])
        ->expectsChoice('Queue connection', 'database', ['database', 'redis', 'sync'])
        ->expectsChoice('HTTP runtime', 'fpm', ['fpm', 'octane'])
        ->expectsChoice('Run queue worker process on server', 'off', ['off', 'on'])
        ->expectsChoice('Run scheduler process on server', 'off', ['off', 'on'])
        ->expectsChoice('HTTPS automation', 'on', ['on', 'off'])
        ->assertExitCode(0);
});

it('fails when custom env is invalid', function (): void {
    $this->artisan('deploymate:init')
        ->expectsQuestion('First domain (hostname only)', 'example.com')
        ->expectsChoice('Environment', 'custom', ['production', 'staging', 'test', 'custom'])
        ->expectsQuestion('Custom environment name', 'Invalid Env')
        ->assertExitCode(1);
});

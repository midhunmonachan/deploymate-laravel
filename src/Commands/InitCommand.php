<?php

namespace Midhunmonachan\DeploymateLaravel\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Midhunmonachan\DeploymateLaravel\Commands\Concerns\ResolvesManifestPath;
use Midhunmonachan\DeploymateLaravel\Commands\Concerns\WarnsUnderDevelopment;
use Midhunmonachan\DeploymateLaravel\Manifest\DeployManifestWriter;
use Midhunmonachan\DeploymateLaravel\Manifest\EnabledModules;
use Midhunmonachan\DeploymateLaravel\Manifest\Hostname;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

class InitCommand extends Command
{
    use ResolvesManifestPath;
    use WarnsUnderDevelopment;

    protected $signature = 'deploymate:init {--path= : Path to deploy.yaml (relative to app root unless absolute)}';

    protected $description = 'Create a minimal deploy.yaml manifest for deploymate.';

    public function handle(Filesystem $files): int
    {
        $this->warnUnderDevelopmentOnce();

        if (! $this->input->isInteractive()) {
            $this->error('deploymate:init requires an interactive terminal (Prompts).');
            $this->error('Run without `--no-interaction`, or generate deploy.yaml manually and validate with `php artisan deploymate:check`.');

            return self::FAILURE;
        }

        $path = $this->resolveManifestPath('deploy.yaml');
        $bakPath = $path.'.bak';

        if ($files->exists($path)) {
            $action = select(
                label: 'deploy.yaml already exists. What do you want to do?',
                options: [
                    'abort' => 'Abort',
                    'overwrite' => 'Overwrite',
                    'backup' => 'Backup + Overwrite (creates deploy.yaml.bak)',
                ],
                default: 'abort',
            );

            if ($action === 'abort') {
                $this->info('Aborted. Existing deploy.yaml left unchanged.');

                return self::SUCCESS;
            }

            if ($action === 'backup') {
                $files->copy($path, $bakPath);
            }
        }

        $instances = [];
        $productionUsed = false;

        while (true) {
            $existingDomains = array_map(static fn (array $i) => $i['domain'], $instances);

            $domain = text(
                label: empty($instances) ? 'First domain (hostname only)' : 'Domain (hostname only)',
                placeholder: 'example.com',
                validate: static function (string $value) use ($existingDomains): ?string {
                    $normalized = Hostname::normalize($value);
                    if ($normalized === null) {
                        return 'Enter a valid hostname (no scheme, path, port, or wildcard).';
                    }

                    if (in_array($normalized, $existingDomains, true)) {
                        return 'Domain must be unique.';
                    }

                    return null;
                },
            );

            $domain = Hostname::normalize($domain);
            if ($domain === null) {
                $this->error('Domain validation failed unexpectedly.');

                return self::FAILURE;
            }

            $env = $this->promptEnv(productionAllowed: ! $productionUsed);

            if ($env === 'production') {
                $productionUsed = true;
            }

            $instances[] = [
                'domain' => $domain,
                'env' => $env,
            ];

            if (! confirm('Add another domain?', default: false)) {
                break;
            }
        }

        $db = select('Database', ['sqlite', 'postgresql', 'mysql'], default: 'sqlite');
        $cache = select('Cache', ['database', 'redis', 'file'], default: 'database');
        $session = select('Session', ['database', 'redis', 'file', 'cookie'], default: 'database');
        $queue = select('Queue connection', ['database', 'redis', 'sync'], default: 'database');
        $http = select('HTTP runtime', ['fpm', 'octane'], default: 'fpm');
        $runQueue = select('Run queue worker process on server', ['off', 'on'], default: 'off');
        $runScheduler = select('Run scheduler process on server', ['off', 'on'], default: 'off');
        $https = select('HTTPS automation', ['on', 'off'], default: 'on');

        $enabled = EnabledModules::fromSelections(
            https: $https,
            db: $db,
            cache: $cache,
            session: $session,
            queue: $queue,
            http: $http,
            runQueue: $runQueue,
            runScheduler: $runScheduler,
        );

        if ($enabled === []) {
            $this->error('No modules were enabled. Re-run and enable at least one module (e.g. HTTPS on, or choose a non-sqlite DB).');

            return self::FAILURE;
        }

        $manifest = [
            'version' => 1,
            'instances' => array_map(
                static fn (array $i) => ['domain' => $i['domain'], 'env' => $i['env']],
                $instances
            ),
            'defaults' => [
                'enabled' => $enabled,
            ],
        ];

        $files->put($path, DeployManifestWriter::toYaml($manifest));

        $modules = implode(', ', $enabled);
        $this->info(sprintf('Wrote %s (%d instance%s, enabled: %s).', $path, count($instances), count($instances) === 1 ? '' : 's', $modules));

        return self::SUCCESS;
    }

    private function promptEnv(bool $productionAllowed): string
    {
        $options = $productionAllowed
            ? ['production', 'staging', 'test', 'custom']
            : ['staging', 'test', 'custom'];

        $env = select('Environment', $options, default: $productionAllowed ? 'production' : 'staging');

        if ($env !== 'custom') {
            return $env;
        }

        $custom = text(
            label: 'Custom environment name',
            placeholder: 'preview',
            validate: function (string $value) use ($productionAllowed): ?string {
                $value = trim($value);
                if (! preg_match('/^[a-z0-9][a-z0-9_-]{0,31}$/', $value)) {
                    return 'Use 1-32 chars: lowercase letters, numbers, underscores, hyphens.';
                }

                if (! $productionAllowed && $value === 'production') {
                    return 'Only one instance may use env=production.';
                }

                return null;
            },
        );

        return trim($custom);
    }
}

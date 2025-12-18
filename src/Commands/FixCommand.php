<?php

namespace Midhunmonachan\DeploymateLaravel\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Midhunmonachan\DeploymateLaravel\Commands\Concerns\ResolvesManifestPath;
use Midhunmonachan\DeploymateLaravel\Commands\Concerns\WarnsUnderDevelopment;
use Midhunmonachan\DeploymateLaravel\Manifest\DeployManifestFixer;

class FixCommand extends Command
{
    use ResolvesManifestPath;
    use WarnsUnderDevelopment;

    protected $signature = 'deploymate:fix
                            {--path= : Path to deploy.yaml (relative to app root unless absolute)}
                            {--dry-run : Print the fixed YAML without writing}';

    protected $description = 'Fix common deploy.yaml issues (remove invalid keys, dedupe, and normalize formatting).';

    public function handle(Filesystem $files): int
    {
        $this->warnUnderDevelopmentOnce();

        $path = $this->resolveManifestPath('deploy.yaml');

        if (! $files->exists($path)) {
            $this->error("$path: file not found.");

            return self::FAILURE;
        }

        $yaml = $files->get($path);

        $result = DeployManifestFixer::fix($yaml, $path);

        foreach ($result->errors as $error) {
            $this->error($error);
        }

        if ($result->errors !== []) {
            return self::FAILURE;
        }

        foreach ($result->warnings as $warning) {
            $this->warn($warning);
        }

        if ((bool) $this->option('dry-run')) {
            $this->line($result->yaml);

            return self::SUCCESS;
        }

        if ($result->changed) {
            $files->put($path, $result->yaml);
            $this->info("Fixed $path.");
        } else {
            $this->info("No changes needed for $path.");
        }

        return self::SUCCESS;
    }
}

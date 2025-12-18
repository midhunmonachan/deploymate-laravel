<?php

namespace Midhunmonachan\DeploymateLaravel\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Midhunmonachan\DeploymateLaravel\Commands\Concerns\ResolvesManifestPath;
use Midhunmonachan\DeploymateLaravel\Commands\Concerns\WarnsUnderDevelopment;
use Midhunmonachan\DeploymateLaravel\Manifest\DeployManifestValidator;
use Midhunmonachan\DeploymateLaravel\Manifest\DuplicateKeyDetector;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

class CheckCommand extends Command
{
    use ResolvesManifestPath;
    use WarnsUnderDevelopment;

    protected $signature = 'deploymate:check
                            {--path= : Path to deploy.yaml (relative to app root unless absolute)}';

    protected $description = 'Validate deploy.yaml schema for deploymate (CI gate).';

    public function handle(Filesystem $files): int
    {
        $this->warnUnderDevelopmentOnce();

        $path = $this->resolveManifestPath('deploy.yaml');

        if (! $files->exists($path)) {
            $this->error("$path: file not found. Run `php artisan deploymate:init` to generate it.");

            return self::FAILURE;
        }

        $duplicateKeyErrors = DuplicateKeyDetector::detectForManifest($files->get($path), $path);
        if ($duplicateKeyErrors !== []) {
            foreach ($duplicateKeyErrors as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        try {
            $data = Yaml::parseFile(
                $path,
                Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE
            );
        } catch (ParseException $e) {
            $this->error("$path: YAML parse error: {$e->getMessage()}");

            return self::FAILURE;
        }

        $errors = DeployManifestValidator::validate($data, $path);

        if ($errors === []) {
            $this->info("$path: OK");

            return self::SUCCESS;
        }

        foreach ($errors as $error) {
            $this->error($error);
        }

        return self::FAILURE;
    }
}

<?php

namespace Midhunmonachan\DeploymateLaravel\Commands\Concerns;

trait ResolvesManifestPath
{
    private function resolveManifestPath(string $default = 'deploy.yaml'): string
    {
        return $this->resolvePathOption() ?? base_path($default);
    }

    private function resolvePathOption(): ?string
    {
        $value = $this->option('path');
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (str_starts_with($value, '/')) {
            return $value;
        }

        if (preg_match('/^[A-Za-z]:\\\\/', $value) === 1) {
            return $value;
        }

        return base_path($value);
    }
}


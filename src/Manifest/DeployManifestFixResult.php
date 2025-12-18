<?php

namespace Midhunmonachan\DeploymateLaravel\Manifest;

class DeployManifestFixResult
{
    /**
     * @param  array<int, string>  $errors
     * @param  array<int, string>  $warnings
     */
    public function __construct(
        public bool $changed,
        public string $yaml,
        public array $errors = [],
        public array $warnings = [],
    ) {
    }
}


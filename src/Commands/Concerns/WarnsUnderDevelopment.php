<?php

namespace Midhunmonachan\DeploymateLaravel\Commands\Concerns;

trait WarnsUnderDevelopment
{
    protected function warnUnderDevelopmentOnce(): void
    {
        static $warned = false;
        if ($warned) {
            return;
        }

        $warned = true;

        $this->warn('deploymate-laravel is under development. Use for development/testing only.');
    }
}


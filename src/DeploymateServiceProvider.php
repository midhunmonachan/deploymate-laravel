<?php

namespace Midhunmonachan\DeploymateLaravel;

use Illuminate\Support\ServiceProvider;
use Midhunmonachan\DeploymateLaravel\Commands\CheckCommand;
use Midhunmonachan\DeploymateLaravel\Commands\FixCommand;
use Midhunmonachan\DeploymateLaravel\Commands\InitCommand;

class DeploymateServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([
            InitCommand::class,
            CheckCommand::class,
            FixCommand::class,
        ]);
    }
}

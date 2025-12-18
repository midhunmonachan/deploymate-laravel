<?php

namespace Midhunmonachan\DeploymateLaravel\Tests;

use Illuminate\Filesystem\Filesystem;
use Midhunmonachan\DeploymateLaravel\DeploymateServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    public static function applicationBasePath(): string
    {
        $path = __DIR__.'/../build/testbench';

        if (! is_dir($path)) {
            mkdir($path, 0777, true);
        }

        foreach (['config', 'bootstrap/cache', 'storage/framework/cache'] as $dir) {
            if (! is_dir($path.'/'.$dir)) {
                mkdir($path.'/'.$dir, 0777, true);
            }
        }

        return $path;
    }

    protected function getPackageProviders($app): array
    {
        return [
            DeploymateServiceProvider::class,
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $files = new Filesystem();
        $files->delete($this->deployYamlPath());
        $files->delete($this->deployYamlBakPath());
        $files->delete($this->app->basePath('config/deploy.yaml'));
        $files->delete($this->app->basePath('config/deploy.yaml.bak'));
    }

    protected function tearDown(): void
    {
        $files = new Filesystem();
        $files->delete($this->deployYamlPath());
        $files->delete($this->deployYamlBakPath());
        $files->delete($this->app->basePath('config/deploy.yaml'));
        $files->delete($this->app->basePath('config/deploy.yaml.bak'));

        parent::tearDown();
    }

    protected function deployYamlPath(): string
    {
        return $this->app->basePath('deploy.yaml');
    }

    protected function deployYamlBakPath(): string
    {
        return $this->app->basePath('deploy.yaml.bak');
    }
}

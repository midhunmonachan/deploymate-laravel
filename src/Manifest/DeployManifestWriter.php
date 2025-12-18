<?php

namespace Midhunmonachan\DeploymateLaravel\Manifest;

class DeployManifestWriter
{
    /**
     * @param  array{version:int,instances:array<int,array{domain:string,env:string}>,defaults:array{enabled:array<int,string>}}  $manifest
     */
    public static function toYaml(array $manifest): string
    {
        $lines = [];
        $lines[] = 'version: '.$manifest['version'];
        $lines[] = 'instances:';

        foreach ($manifest['instances'] as $instance) {
            $lines[] = '  - domain: "'.self::escapeDoubleQuotes($instance['domain']).'"';
            $lines[] = '    env: '.$instance['env'];
        }

        $lines[] = 'defaults:';
        $enabled = implode(', ', $manifest['defaults']['enabled']);
        $lines[] = '  enabled: ['.$enabled.']';

        return implode("\n", $lines)."\n";
    }

    private static function escapeDoubleQuotes(string $value): string
    {
        return str_replace('"', '\"', $value);
    }
}


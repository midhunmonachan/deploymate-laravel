<?php

namespace Midhunmonachan\DeploymateLaravel\Manifest;

class EnabledModules
{
    public const ALLOWED = [
        'https',
        'postgresql',
        'mysql',
        'redis',
        'octane',
        'queue',
        'scheduler',
    ];

    public const CANONICAL_ORDER = [
        'https',
        'postgresql',
        'mysql',
        'redis',
        'octane',
        'queue',
        'scheduler',
    ];

    /**
     * @return array<int, string>
     */
    public static function fromSelections(
        string $https,
        string $db,
        string $cache,
        string $session,
        string $queue,
        string $http,
        string $runQueue,
        string $runScheduler,
    ): array {
        $enabled = [];

        if ($https === 'on') {
            $enabled[] = 'https';
        }

        if ($db === 'postgresql') {
            $enabled[] = 'postgresql';
        } elseif ($db === 'mysql') {
            $enabled[] = 'mysql';
        }

        if ($cache === 'redis' || $session === 'redis' || $queue === 'redis') {
            $enabled[] = 'redis';
        }

        if ($http === 'octane') {
            $enabled[] = 'octane';
        }

        if ($runQueue === 'on') {
            $enabled[] = 'queue';
        }

        if ($runScheduler === 'on') {
            $enabled[] = 'scheduler';
        }

        return self::canonicalize($enabled);
    }

    /**
     * @param  array<int, string>  $enabled
     * @return array<int, string>
     */
    public static function canonicalize(array $enabled): array
    {
        $enabled = array_values(array_unique($enabled));

        $order = array_flip(self::CANONICAL_ORDER);
        usort($enabled, static fn (string $a, string $b) => ($order[$a] ?? 999) <=> ($order[$b] ?? 999));

        return $enabled;
    }
}


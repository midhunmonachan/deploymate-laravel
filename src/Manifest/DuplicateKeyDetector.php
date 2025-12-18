<?php

namespace Midhunmonachan\DeploymateLaravel\Manifest;

class DuplicateKeyDetector
{
    /**
     * Symfony YAML 7.x does not error on duplicate keys; detect duplicates for the manifest structure.
     *
     * @return array<int, string>
     */
    public static function detectForManifest(string $yaml, string $path): array
    {
        $errors = [];
        $rootKeys = [];
        $defaultsKeys = [];
        $instanceKeys = null;

        $inInstances = false;
        $inDefaults = false;

        $lines = preg_split("/\r\n|\n|\r/", $yaml) ?: [];

        foreach ($lines as $index => $line) {
            $lineNumber = $index + 1;

            $trimmed = ltrim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }

            $indent = strspn($line, ' ');

            if ($indent === 0 && preg_match('/^([A-Za-z0-9_-]+)\s*:/', $trimmed, $m) === 1) {
                $key = $m[1];

                if (isset($rootKeys[$key])) {
                    $errors[] = "$path:$lineNumber: duplicate key '$key' at top level.";
                } else {
                    $rootKeys[$key] = true;
                }

                $inInstances = $key === 'instances';
                $inDefaults = $key === 'defaults';
                $defaultsKeys = $inDefaults ? [] : $defaultsKeys;
                $instanceKeys = null;

                continue;
            }

            if ($inDefaults && $indent >= 2 && preg_match('/^([A-Za-z0-9_-]+)\s*:/', $trimmed, $m) === 1) {
                $key = $m[1];
                if (isset($defaultsKeys[$key])) {
                    $errors[] = "$path:$lineNumber: duplicate key '$key' under defaults.";
                } else {
                    $defaultsKeys[$key] = true;
                }

                continue;
            }

            if ($inInstances) {
                if ($indent >= 2 && preg_match('/^-\s*([A-Za-z0-9_-]+)\s*:/', $trimmed, $m) === 1) {
                    $instanceKeys = [];
                    $key = $m[1];
                    $instanceKeys[$key] = true;

                    continue;
                }

                if ($instanceKeys !== null && $indent >= 4 && preg_match('/^([A-Za-z0-9_-]+)\s*:/', $trimmed, $m) === 1) {
                    $key = $m[1];
                    if (isset($instanceKeys[$key])) {
                        $errors[] = "$path:$lineNumber: duplicate key '$key' in an instance.";
                    } else {
                        $instanceKeys[$key] = true;
                    }
                }
            }
        }

        return $errors;
    }
}


<?php

namespace Midhunmonachan\DeploymateLaravel\Manifest;

class DeployManifestValidator
{
    /**
     * @return array<int, string>
     */
    public static function validate(mixed $data, string $path): array
    {
        $errors = [];

        if (! is_array($data)) {
            return ["$path: expected a YAML mapping (top-level object)."];
        }

        self::validateExactKeys($data, ['version', 'instances', 'defaults'], "$path", $errors);

        if (($data['version'] ?? null) !== 1) {
            $errors[] = "$path: version must be 1.";
        }

        $instances = $data['instances'] ?? null;
        if (! is_array($instances) || ! array_is_list($instances) || $instances === []) {
            $errors[] = "$path: instances must be a non-empty list.";
        } else {
            $seenDomains = [];
            $productionCount = 0;

            foreach ($instances as $idx => $instance) {
                if (! is_array($instance)) {
                    $errors[] = "$path: instances[$idx] must be an object with domain and env.";
                    continue;
                }

                self::validateExactKeys($instance, ['domain', 'env'], "$path: instances[$idx]", $errors);

                $domain = $instance['domain'] ?? null;
                if (! is_string($domain) || ($normalized = Hostname::normalize($domain)) === null) {
                    $errors[] = "$path: instances[$idx].domain must be a valid hostname (no scheme, path, port, or wildcard).";
                } else {
                    if (isset($seenDomains[$normalized])) {
                        $errors[] = "$path: instances[$idx].domain must be unique (duplicate: $normalized).";
                    }
                    $seenDomains[$normalized] = true;
                }

                $env = $instance['env'] ?? null;
                if (! is_string($env) || trim($env) === '') {
                    $errors[] = "$path: instances[$idx].env must be a non-empty string.";
                } else {
                    if ($env === 'production') {
                        $productionCount++;
                        if ($productionCount > 1) {
                            $errors[] = "$path: only one instance may have env=production.";
                        }
                    }
                }
            }
        }

        $defaults = $data['defaults'] ?? null;
        if (! is_array($defaults)) {
            $errors[] = "$path: defaults must be an object.";
        } else {
            self::validateExactKeys($defaults, ['enabled'], "$path: defaults", $errors);

            $enabled = $defaults['enabled'] ?? null;
            if (! is_array($enabled) || ! array_is_list($enabled) || $enabled === []) {
                $errors[] = "$path: defaults.enabled must be a non-empty list.";
            } else {
                $enabledStrings = [];
                foreach ($enabled as $idx => $entry) {
                    if (! is_string($entry) || $entry === '') {
                        $errors[] = "$path: defaults.enabled[$idx] must be a non-empty string.";
                        continue;
                    }
                    $enabledStrings[] = $entry;

                    if (! in_array($entry, EnabledModules::ALLOWED, true)) {
                        $errors[] = "$path: defaults.enabled[$idx] is invalid ($entry). Allowed: ".implode(', ', EnabledModules::ALLOWED).'.';
                    }
                }

                if (count($enabledStrings) !== count(array_unique($enabledStrings))) {
                    $errors[] = "$path: defaults.enabled must not contain duplicates.";
                }

                if (in_array('mysql', $enabledStrings, true) && in_array('postgresql', $enabledStrings, true)) {
                    $errors[] = "$path: defaults.enabled must not contain both mysql and postgresql.";
                }
            }
        }

        return $errors;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, string>  $expectedKeys
     * @param  array<int, string>  $errors
     */
    private static function validateExactKeys(array $data, array $expectedKeys, string $prefix, array &$errors): void
    {
        $actualKeys = array_keys($data);
        sort($actualKeys);
        $expected = $expectedKeys;
        sort($expected);

        if ($actualKeys !== $expected) {
            $errors[] = $prefix.': keys must be exactly {'.implode(', ', $expectedKeys).'} (found {'.implode(', ', array_keys($data)).'}).';
        }
    }
}

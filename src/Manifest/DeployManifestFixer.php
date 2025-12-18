<?php

namespace Midhunmonachan\DeploymateLaravel\Manifest;

use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

class DeployManifestFixer
{
    public static function fix(string $yaml, string $path): DeployManifestFixResult
    {
        $errors = [];
        $warnings = [];

        $duplicateKeyErrors = DuplicateKeyDetector::detectForManifest($yaml, $path);
        if ($duplicateKeyErrors !== []) {
            return new DeployManifestFixResult(
                changed: false,
                yaml: $yaml,
                errors: $duplicateKeyErrors,
            );
        }

        try {
            $data = Yaml::parse($yaml, Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE);
        } catch (ParseException $e) {
            return new DeployManifestFixResult(
                changed: false,
                yaml: $yaml,
                errors: ["$path: YAML parse error: {$e->getMessage()}"],
            );
        }

        if (! is_array($data)) {
            return new DeployManifestFixResult(
                changed: false,
                yaml: $yaml,
                errors: ["$path: expected a YAML mapping (top-level object)."],
            );
        }

        $fixed = [];

        $version = $data['version'] ?? null;
        if ($version === null) {
            $warnings[] = "$path: added missing version: 1.";
            $version = 1;
        }
        if ($version !== 1) {
            return new DeployManifestFixResult(
                changed: false,
                yaml: $yaml,
                errors: ["$path: version must be 1 (found ".self::stringify($version).")."],
            );
        }
        $fixed['version'] = 1;

        $instances = $data['instances'] ?? null;
        if (! is_array($instances) || ! array_is_list($instances) || $instances === []) {
            return new DeployManifestFixResult(
                changed: false,
                yaml: $yaml,
                errors: ["$path: instances must be a non-empty list."],
            );
        }

        $seenDomains = [];
        $productionCount = 0;
        $fixedInstances = [];

        foreach ($instances as $idx => $instance) {
            if (! is_array($instance)) {
                return new DeployManifestFixResult(
                    changed: false,
                    yaml: $yaml,
                    errors: ["$path: instances[$idx] must be an object with domain and env."],
                );
            }

            $domain = $instance['domain'] ?? null;
            if (! is_string($domain) || ($normalized = Hostname::normalize($domain)) === null) {
                return new DeployManifestFixResult(
                    changed: false,
                    yaml: $yaml,
                    errors: ["$path: instances[$idx].domain must be a valid hostname (no scheme, path, port, or wildcard)."],
                );
            }

            $env = $instance['env'] ?? null;
            if (! is_string($env) || trim($env) === '') {
                return new DeployManifestFixResult(
                    changed: false,
                    yaml: $yaml,
                    errors: ["$path: instances[$idx].env must be a non-empty string."],
                );
            }
            $env = trim($env);

            if ($env === 'production') {
                $productionCount++;
            }

            if (isset($seenDomains[$normalized])) {
                $warnings[] = "$path: removed duplicate instance for domain $normalized.";
                continue;
            }

            $seenDomains[$normalized] = true;
            $fixedInstances[] = [
                'domain' => $normalized,
                'env' => $env,
            ];
        }

        if ($productionCount > 1) {
            return new DeployManifestFixResult(
                changed: false,
                yaml: $yaml,
                errors: ["$path: only one instance may have env=production."],
            );
        }

        if ($fixedInstances === []) {
            return new DeployManifestFixResult(
                changed: false,
                yaml: $yaml,
                errors: ["$path: instances must be a non-empty list."],
            );
        }

        $fixed['instances'] = $fixedInstances;

        $defaults = $data['defaults'] ?? null;
        if (! is_array($defaults)) {
            return new DeployManifestFixResult(
                changed: false,
                yaml: $yaml,
                errors: ["$path: defaults must be an object."],
            );
        }

        $enabled = $defaults['enabled'] ?? null;
        if (! is_array($enabled) || ! array_is_list($enabled) || $enabled === []) {
            return new DeployManifestFixResult(
                changed: false,
                yaml: $yaml,
                errors: ["$path: defaults.enabled must be a non-empty list."],
            );
        }

        $enabledStrings = [];
        foreach ($enabled as $idx => $entry) {
            if (! is_string($entry)) {
                $warnings[] = "$path: dropped non-string defaults.enabled[$idx].";
                continue;
            }

            $entry = trim($entry);
            if ($entry === '') {
                continue;
            }

            if (! in_array($entry, EnabledModules::ALLOWED, true)) {
                $warnings[] = "$path: removed unknown module '$entry'.";
                continue;
            }

            $enabledStrings[] = $entry;
        }

        if ($enabledStrings === []) {
            return new DeployManifestFixResult(
                changed: false,
                yaml: $yaml,
                errors: ["$path: defaults.enabled must contain at least one valid module."],
            );
        }

        if (in_array('mysql', $enabledStrings, true) && in_array('postgresql', $enabledStrings, true)) {
            $first = null;
            foreach ($enabledStrings as $m) {
                if ($m === 'mysql' || $m === 'postgresql') {
                    $first = $m;
                    break;
                }
            }

            $drop = $first === 'mysql' ? 'postgresql' : 'mysql';
            $enabledStrings = array_values(array_filter($enabledStrings, static fn (string $m) => $m !== $drop));
            $warnings[] = "$path: removed '$drop' because both mysql and postgresql were present.";
        }

        $enabledStrings = EnabledModules::canonicalize($enabledStrings);

        $fixed['defaults'] = [
            'enabled' => $enabledStrings,
        ];

        $written = DeployManifestWriter::toYaml($fixed);
        $changed = $written !== $yaml;

        $topExtras = array_diff(array_keys($data), ['version', 'instances', 'defaults']);
        if ($topExtras !== []) {
            $warnings[] = "$path: removed unknown top-level keys: ".implode(', ', $topExtras).'.';
        }

        $instanceHasExtras = false;
        foreach ($instances as $i => $instance) {
            if (is_array($instance)) {
                $extras = array_diff(array_keys($instance), ['domain', 'env']);
                if ($extras !== []) {
                    $instanceHasExtras = true;
                    break;
                }
            }
        }
        if ($instanceHasExtras) {
            $warnings[] = "$path: removed unknown keys from instance objects (allowed: domain, env).";
        }

        $defaultsExtras = is_array($defaults) ? array_diff(array_keys($defaults), ['enabled']) : [];
        if ($defaultsExtras !== []) {
            $warnings[] = "$path: removed unknown keys under defaults (allowed: enabled).";
        }

        return new DeployManifestFixResult(
            changed: $changed,
            yaml: $written,
            errors: $errors,
            warnings: $warnings,
        );
    }

    private static function stringify(mixed $value): string
    {
        if (is_scalar($value) || $value === null) {
            return var_export($value, true);
        }

        return get_debug_type($value);
    }
}

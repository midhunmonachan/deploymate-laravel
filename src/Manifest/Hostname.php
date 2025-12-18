<?php

namespace Midhunmonachan\DeploymateLaravel\Manifest;

class Hostname
{
    public static function normalize(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $value = strtolower($value);

        if (str_contains($value, '://')) {
            return null;
        }

        if (str_contains($value, '/') || str_contains($value, '\\')) {
            return null;
        }

        if (str_contains($value, '*')) {
            return null;
        }

        if (str_contains($value, ':')) {
            return null;
        }

        if (str_ends_with($value, '.')) {
            $value = rtrim($value, '.');
        }

        if ($value === '') {
            return null;
        }

        if (strlen($value) > 253) {
            return null;
        }

        $labels = explode('.', $value);
        foreach ($labels as $label) {
            if ($label === '' || strlen($label) > 63) {
                return null;
            }

            if (! preg_match('/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/', $label)) {
                return null;
            }
        }

        return $value;
    }
}


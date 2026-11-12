<?php

namespace RCV\Core\Services;

use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use JsonException;

class ModuleManifestValidator
{
    /**
     * @throws InvalidArgumentException
     */
    public static function read(string $moduleName, string $path): array
    {
        if (! File::exists($path)) {
            throw new InvalidArgumentException("Module manifest is missing for [{$moduleName}].");
        }

        try {
            $manifest = json_decode(File::get($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException("Module manifest for [{$moduleName}] contains invalid JSON.", previous: $exception);
        }

        if (! is_array($manifest) || array_is_list($manifest)) {
            throw new InvalidArgumentException("Module manifest for [{$moduleName}] must be a JSON object.");
        }

        if (! is_string($manifest['name'] ?? null) || $manifest['name'] !== $moduleName) {
            throw new InvalidArgumentException("Module manifest for [{$moduleName}] must have a matching name field.");
        }

        if (! is_string($manifest['version'] ?? null) || trim($manifest['version']) === '') {
            throw new InvalidArgumentException("Module manifest for [{$moduleName}] must have a non-empty version field.");
        }

        if (isset($manifest['description']) && ! is_string($manifest['description'])) {
            throw new InvalidArgumentException("Module manifest field [description] for [{$moduleName}] must be a string.");
        }

        foreach (['dependencies', 'dependents'] as $field) {
            if (! array_key_exists($field, $manifest)) {
                $manifest[$field] = [];

                continue;
            }

            if (! is_array($manifest[$field]) || ! array_is_list($manifest[$field])) {
                throw new InvalidArgumentException("Module manifest field [{$field}] for [{$moduleName}] must be a list.");
            }

            foreach ($manifest[$field] as $dependency) {
                $isValid = is_string($dependency)
                    && trim($dependency) !== ''
                    && ($field === 'dependencies' || ModuleNameValidator::isValid($dependency));

                if (! $isValid) {
                    $expected = $field === 'dependencies' ? 'Composer package requirement' : 'module name';

                    throw new InvalidArgumentException("Module manifest field [{$field}] for [{$moduleName}] must contain valid {$expected} values.");
                }
            }
        }

        return $manifest;
    }
}

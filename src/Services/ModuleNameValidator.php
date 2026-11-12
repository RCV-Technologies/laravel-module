<?php

namespace RCV\Core\Services;

class ModuleNameValidator
{
    public static function isValid(string $moduleName): bool
    {
        return preg_match('/\A[A-Za-z][A-Za-z0-9_-]*\z/', $moduleName) === 1;
    }
}

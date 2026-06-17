<?php

use RCV\Core\Hooks\Facades\Hook;

if (!function_exists('hook_listen')) {
    function hook_listen(string $hook, callable $callback, int $priority = 10): void
    {
        Hook::listen($hook, $callback, $priority);
    }
}

if (!function_exists('hook_execute')) {
    function hook_execute(string $hook, ...$args): void
    {
        Hook::execute($hook, ...$args);
    }
}

if (!function_exists('hook_filter')) {
    function hook_filter(string $hook, callable $callback, int $priority = 10): void
    {
        Hook::filter($hook, $callback, $priority);
    }
}

if (!function_exists('hook_apply')) {
    function hook_apply(string $hook, mixed $value, ...$args): mixed
    {
        return Hook::apply($hook, $value, ...$args);
    }
}

if (!function_exists('hook_render')) {
    function hook_render(string $hook, ...$args): string
    {
        return Hook::render($hook, ...$args);
    }
}

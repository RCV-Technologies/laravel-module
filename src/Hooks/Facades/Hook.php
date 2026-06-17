<?php

namespace RCV\Core\Hooks\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static void listen(string $hook, callable $callback, int $priority = 10)
 * @method static void filter(string $hook, callable $callback, int $priority = 10)
 * @method static void execute(string $hook, ...$args)
 * @method static mixed apply(string $hook, mixed $value, ...$args)
 * @method static string render(string $hook, ...$args)
 * @method static bool exists(string $hook)
 * @method static void remove(string $hook)
 */
class Hook extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'rcv.core.hooks';
    }
}

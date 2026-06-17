<?php

namespace RCV\Core\Hooks\Contracts;

interface HookListenerInterface
{
    /**
     * Get the hook name this listener subscribes to.
     */
    public static function getHook(): string;

    /**
     * Get the listener priority.
     */
    public static function getPriority(): int;

    /**
     * Handle the hook execution.
     */
    public function handle(...$args);
}

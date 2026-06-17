<?php

namespace RCV\Core\Hooks;

class HookRegistry
{
    /**
     * Map of registered listeners.
     * Format: [hookName => [priority => [listener1, listener2, ...]]]
     *
     * @var array
     */
    protected array $listeners = [];

    /**
     * Map of sorted listeners.
     * Format: [hookName => [listener1, listener2, ...]]
     *
     * @var array
     */
    protected array $sorted = [];

    /**
     * Add a listener.
     */
    public function register(string $hook, callable $callback, int $priority = 10): void
    {
        $this->listeners[$hook][$priority][] = $callback;
        unset($this->sorted[$hook]); // Invalidate sorted cache for this hook
    }

    /**
     * Get sorted listeners for a hook.
     */
    public function get(string $hook): array
    {
        if (isset($this->sorted[$hook])) {
            return $this->sorted[$hook];
        }

        if (empty($this->listeners[$hook])) {
            return [];
        }

        // Sort by priority (ascending)
        $priorities = $this->listeners[$hook];
        ksort($priorities);

        $flat = [];
        foreach ($priorities as $priorityListeners) {
            foreach ($priorityListeners as $listener) {
                $flat[] = $listener;
            }
        }

        return $this->sorted[$hook] = $flat;
    }

    /**
     * Check if listeners exist for a hook.
     */
    public function has(string $hook): bool
    {
        return !empty($this->listeners[$hook]);
    }

    /**
     * Remove all or specific listeners for a hook.
     */
    public function forget(string $hook): void
    {
        unset($this->listeners[$hook]);
        unset($this->sorted[$hook]);
    }

    /**
     * Clear registry.
     */
    public function clear(): void
    {
        $this->listeners = [];
        $this->sorted = [];
    }
}

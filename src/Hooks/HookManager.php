<?php

namespace RCV\Core\Hooks;

use Illuminate\Support\Facades\Log;
use Throwable;

class HookManager
{
    protected HookRegistry $registry;
    protected bool $enabled;
    protected string $failurePolicy;

    public function __construct(HookRegistry $registry)
    {
        $this->registry = $registry;
        $this->enabled = (bool) config('rcv-hooks.enabled', false);
        $this->failurePolicy = (string) config('rcv-hooks.failure_policy', 'continue');
    }

    /**
     * Register a listener for a hook.
     */
    public function listen(string $hook, callable $callback, int $priority = 10): void
    {
        if (!$this->enabled) {
            return;
        }
        $this->registry->register($hook, $callback, $priority);
    }

    /**
     * Alias for listen().
     */
    public function filter(string $hook, callable $callback, int $priority = 10): void
    {
        $this->listen($hook, $callback, $priority);
    }

    /**
     * Execute action hook.
     */
    public function execute(string $hook, ...$args): array
    {
        if (!$this->enabled) {
            return [];
        }

        $listeners = $this->registry->get($hook);

        $results = [];

        foreach ($listeners as $listener) {
            try {

                $results[] = $listener(...$args);

            } catch (Throwable $e) {

                $this->handleException($hook, $e);
            }
        }

        return $results;
    }

    /**
     * Apply filter hook to a value.
     */
    public function apply(string $hook, mixed $value, ...$args): mixed
    {
        if (!$this->enabled) {
            return $value;
        }

        $listeners = $this->registry->get($hook);

        foreach ($listeners as $listener) {
            try {
                $value = $listener($value, ...$args);
            } catch (Throwable $e) {
                $this->handleException($hook, $e);
            }
        }

        return $value;
    }

    /**
     * Render layout/view hook.
     */
    public function render(string $hook, ...$args): string
    {
        if (!$this->enabled) {
            return '';
        }

        $listeners = $this->registry->get($hook);
        $output = '';

        foreach ($listeners as $listener) {
            try {
                $result = $listener(...$args);
                if (is_string($result)) {
                    $output .= $result;
                }
            } catch (Throwable $e) {
                $this->handleException($hook, $e);
            }
        }

        return $output;
    }

    /**
     * Check if a hook has any listeners.
     */
    public function exists(string $hook): bool
    {
        if (!$this->enabled) {
            return false;
        }
        return $this->registry->has($hook);
    }

    /**
     * Remove all listeners for a hook.
     */
    public function remove(string $hook): void
    {
        if (!$this->enabled) {
            return;
        }
        $this->registry->forget($hook);
    }

    /**
     * Get the failure policy.
     */
    public function getFailurePolicy(): string
    {
        return $this->failurePolicy;
    }

    /**
     * Handle listener exception based on policy.
     */
    protected function handleException(string $hook, Throwable $e): void
    {
        if ($this->failurePolicy === 'fail_fast') {
            throw $e;
        }

        Log::error("Hook system error in '{$hook}': " . $e->getMessage(), [
            'exception' => $e,
            'hook' => $hook,
        ]);
    }
}

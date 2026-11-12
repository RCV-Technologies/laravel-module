<?php

namespace RCV\Core\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;

class ModuleMetrics
{
    /**
     * In-memory counters storage.
     *
     * @var array<string,int>
     */
    protected array $counters = [];

    /**
     * In-memory gauges storage.
     *
     * @var array<string,float|int>
     */
    protected array $gauges = [];

    /**
     * In-memory timers storage.
     *
     * @var array<string,float>
     */
    protected array $timers = [];

    /**
     * Whether metrics are enabled.
     */
    protected bool $enabled;

    /**
     * Storage driver: array|cache
     */
    protected string $driver;

    /**
     * Cache store name when driver is cache.
     */
    protected ?string $cacheStore;

    public function __construct()
    {
        $this->enabled = (bool) Config::get('metrics.enabled', true);
        $this->driver = (string) Config::get('metrics.driver', 'array');
        $this->cacheStore = Config::get('metrics.cache_store');
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function enable(): void
    {
        $this->enabled = true;
    }

    public function disable(): void
    {
        $this->enabled = false;
    }

    /**
     * Start a timer for a key.
     */
    public function startTimer(string $key): void
    {
        if (! $this->enabled) {
            return;
        }
        $this->timers[$key] = microtime(true);
    }

    /**
     * End a timer and record the elapsed milliseconds.
     * Returns elapsed milliseconds or null if not started.
     */
    public function endTimer(string $key): ?float
    {
        if (! $this->enabled) {
            return null;
        }
        if (! isset($this->timers[$key])) {
            return null;
        }
        $startedAt = $this->timers[$key];
        unset($this->timers[$key]);
        $elapsedMs = (microtime(true) - $startedAt) * 1000.0;
        $this->setGauge("timer.ms.$key", $elapsedMs);

        return $elapsedMs;
    }

    /**
     * Get the last recorded timer value in milliseconds.
     */
    public function getTimer(string $key): ?float
    {
        $value = $this->getGauge("timer.ms.$key");

        return $value === null ? null : (float) $value;
    }

    /**
     * Increment a counter.
     */
    public function increment(string $key, int $value = 1): void
    {
        if (! $this->enabled) {
            return;
        }
        if ($this->driver === 'cache') {
            $cacheKey = $this->cacheKey("counter.$key");
            $cache = Cache::store($this->cacheStore);
            $cache->add($cacheKey, 0);
            if ($value < 0) {
                $cache->decrement($cacheKey, abs($value));
            } else {
                $cache->increment($cacheKey, $value);
            }

            return;
        }

        $this->counters[$key] = ($this->counters[$key] ?? 0) + $value;
    }

    /**
     * Decrement a counter.
     */
    public function decrement(string $key, int $value = 1): void
    {
        $this->increment($key, -$value);
    }

    /**
     * Get a counter value.
     */
    public function getCounter(string $key): ?int
    {
        if ($this->driver === 'cache') {
            return Cache::store($this->cacheStore)->get($this->cacheKey("counter.$key"));
        }

        return $this->counters[$key] ?? null;
    }

    /**
     * Set a gauge value.
     */
    public function setGauge(string $key, float|int $value): void
    {
        if (! $this->enabled) {
            return;
        }
        if ($this->driver === 'cache') {
            Cache::store($this->cacheStore)->forever($this->cacheKey("gauge.$key"), $value);

            return;
        }
        $this->gauges[$key] = $value;
    }

    /**
     * Get a gauge value.
     */
    public function getGauge(string $key): float|int|null
    {
        if ($this->driver === 'cache') {
            return Cache::store($this->cacheStore)->get($this->cacheKey("gauge.$key"));
        }

        return $this->gauges[$key] ?? null;
    }

    /**
     * Convenience: record current PHP memory usage for a module key.
     */
    public function recordMemoryUsage(string $moduleKey): void
    {
        $this->setGauge("memory.bytes.$moduleKey", memory_get_usage(true));
    }

    /**
     * Remove all in-memory metrics. Cache driver remains intact.
     */
    public function flushInMemory(): void
    {
        $this->counters = [];
        $this->gauges = [];
        $this->timers = [];
    }

    protected function storeCounter(string $key, int $value): void
    {
        if ($this->driver === 'cache') {
            Cache::store($this->cacheStore)->forever($this->cacheKey("counter.$key"), $value);

            return;
        }
        $this->counters[$key] = $value;
    }

    protected function cacheKey(string $key): string
    {
        $prefix = (string) Config::get('metrics.cache_prefix', 'rcv:metrics:');

        return $prefix.$key;
    }
}

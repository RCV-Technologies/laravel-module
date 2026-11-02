<?php

namespace RCV\Core\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use RCV\Core\Models\ModuleState;

class CacheManager
{
    const CACHE_VERSION = '1.0.0';

    const CACHE_TAG = 'rcv_core:modules';

    const CACHE_PREFIX = 'rcv_core:module_';

    const CACHE_INDEX_KEY = 'rcv_core:module_cache_index:v1';

    protected $ttl;

    protected $isEnabled;

    public function __construct()
    {
        $this->ttl = Config::get('marketplace.cache.ttl', 3600);
        $this->isEnabled = Config::get('marketplace.cache.enabled', true);
    }

    public function remember(string $key, \Closure $callback)
    {
        if (! $this->isEnabled) {
            return $callback();
        }

        $versionedKey = $this->getVersionedKey($key);

        if ($this->supportsTags()) {
            return Cache::tags([self::CACHE_TAG])->remember($versionedKey, $this->ttl, $callback);
        }

        $value = Cache::remember($versionedKey, $this->ttl, $callback);
        $this->rememberKey($versionedKey);

        return $value;
    }

    public function put(string $key, $value, $ttl = null)
    {
        if (! $this->isEnabled) {
            return $value;
        }

        $versionedKey = $this->getVersionedKey($key);
        $cache = Cache::store();

        if ($this->supportsTags()) {
            $cache->tags([self::CACHE_TAG])->put($versionedKey, $value, $ttl ?? $this->ttl);
        } else {
            $cache->put($versionedKey, $value, $ttl ?? $this->ttl);
            $this->rememberKey($versionedKey);
        }

        return $value;
    }

    public function get(string $key, $default = null)
    {
        if (! $this->isEnabled) {
            return $default;
        }

        $versionedKey = $this->getVersionedKey($key);

        return $this->supportsTags()
            ? Cache::tags([self::CACHE_TAG])->get($versionedKey, $default)
            : Cache::get($versionedKey, $default);
    }

    public function forget(string $key)
    {
        if (! $this->isEnabled) {
            return;
        }

        $versionedKey = $this->getVersionedKey($key);
        $cache = Cache::store();

        if ($this->supportsTags()) {
            $cache->tags([self::CACHE_TAG])->forget($versionedKey);
        } else {
            $cache->forget($versionedKey);
            $this->forgetKey($versionedKey);
        }
    }

    public function flush(): void
    {
        if (! $this->isEnabled) {
            return;
        }

        if ($this->supportsTags()) {
            Cache::tags([self::CACHE_TAG])->flush();

            return;
        }

        $cache = Cache::store();
        foreach ($cache->get(self::CACHE_INDEX_KEY, []) as $key) {
            $cache->forget($key);
        }

        $cache->forget(self::CACHE_INDEX_KEY);
    }

    public function warmUp(): void
    {
        if (! $this->isEnabled) {
            return;
        }

        $states = ModuleState::all();
        foreach ($states as $state) {
            $this->put(
                $this->getModuleKey($state->name),
                [
                    'name' => $state->name,
                    'version' => $state->version,
                    'status' => $state->status,
                    'last_enabled' => $state->last_enabled_at,
                    'last_disabled' => $state->last_disabled_at,
                    'applied_migrations' => $state->applied_migrations,
                    'failed_migrations' => $state->failed_migrations,
                ]
            );
        }

        $this->put('modules.list', $states->pluck('name')->toArray());
    }

    protected function getVersionedKey(string $key): string
    {
        return self::CACHE_PREFIX.self::CACHE_VERSION.':'.$key;
    }

    protected function getModuleKey(string $moduleName): string
    {
        return 'module.'.strtolower($moduleName);
    }

    protected function supportsTags(): bool
    {
        return Cache::store()->supportsTags();
    }

    protected function rememberKey(string $key): void
    {
        $cache = Cache::store();
        $keys = $cache->get(self::CACHE_INDEX_KEY, []);
        $keys[] = $key;
        $cache->forever(self::CACHE_INDEX_KEY, array_values(array_unique($keys)));
    }

    protected function forgetKey(string $key): void
    {
        $cache = Cache::store();
        $keys = array_filter($cache->get(self::CACHE_INDEX_KEY, []), fn (string $cachedKey): bool => $cachedKey !== $key);

        if ($keys === []) {
            $cache->forget(self::CACHE_INDEX_KEY);

            return;
        }

        $cache->forever(self::CACHE_INDEX_KEY, array_values($keys));
    }

    public function invalidateModule(string $moduleName): void
    {
        if (! $this->isEnabled) {
            return;
        }

        $this->forget($this->getModuleKey($moduleName));
        $this->forget('modules.list');
    }

    public function invalidateAll(): void
    {
        if (! $this->isEnabled) {
            return;
        }

        $this->flush();
        $this->warmUp();
    }
}

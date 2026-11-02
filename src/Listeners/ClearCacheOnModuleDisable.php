<?php

namespace RCV\Core\Listeners;

use RCV\Core\Events\ModuleDisabled;
use RCV\Core\Services\CacheManager;
use RCV\Core\Services\ModuleManager;

class ClearCacheOnModuleDisable
{
    public function handle(ModuleDisabled $event): void
    {
        app(CacheManager::class)->invalidateModule($event->name);
        app(ModuleManager::class)->refreshEnabledModulesCache();

        info("RCV Core cache entries invalidated after disabling module: {$event->name}".
             ($event->removed ? ' (removed)' : ''));
    }
}

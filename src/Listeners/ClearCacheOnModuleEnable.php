<?php

namespace RCV\Core\Listeners;

use RCV\Core\Events\ModuleEnabled;
use RCV\Core\Services\CacheManager;
use RCV\Core\Services\ModuleManager;

class ClearCacheOnModuleEnable
{
    public function handle(ModuleEnabled $event): void
    {
        app(CacheManager::class)->invalidateModule($event->name);
        app(ModuleManager::class)->refreshEnabledModulesCache();

        info("RCV Core cache entries invalidated after enabling module: {$event->name}");
    }
}

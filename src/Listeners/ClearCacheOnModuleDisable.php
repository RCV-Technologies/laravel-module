<?php

namespace RCV\Core\Listeners;

use RCV\Core\Events\ModuleDisabled;
use RCV\Core\Services\CacheManager;
use RCV\Core\Services\MarketplaceService;
use RCV\Core\Services\ModuleManager;

class ClearCacheOnModuleDisable
{
    public function handle(ModuleDisabled $event): void
    {
        app(CacheManager::class)->invalidateModule($event->name);
        app(ModuleManager::class)->refreshEnabledModulesCache();
        app(MarketplaceService::class)->clearCachedState($event->name);
    }
}

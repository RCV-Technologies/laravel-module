<?php

namespace RCV\Core\Console\Commands\Actions;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;

class ModuleUnuseCommand extends Command
{
    protected $signature = 'module:unuse';

    protected $description = 'Unset the active module for the current CLI session';

    public function handle()
    {
        Config::set('app.active_module', null);

        $this->info('Active module has been unset.');

        return 0;
    }
}

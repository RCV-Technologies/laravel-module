<?php

namespace Rcv\Core\Console\Commands;
use Illuminate\Console\Command;
use Rcv\Core\Models\ModuleState;
use Illuminate\Support\Facades\File;

class ModuleStateCommand extends Command
{
    protected $signature = 'module:state {name?}';
    protected $description = 'Show module states from the database';

    public function handle()
    {
        $name = $this->argument('name');
        $modulePath = base_path('Modules');

        if ($name) {
            $state = ModuleState::where('name', $name)->first();
            if ($state && File::exists("{$modulePath}/{$name}")) {
                $this->info("Module [{$name}] state:");
                $this->table(
                    ['Name', 'Status', 'Version', 'Last Enabled', 'Last Disabled'],
                    [[
                        $state->name,
                        $state->status,
                        $state->version,
                        $state->last_enabled_at,
                        $state->last_disabled_at
                    ]]
                );
            } else {
                if (!$state) {
                    $this->error("Module [{$name}] state not found in database");
                } else {
                    $this->error("Module [{$name}] files not found in filesystem");
                }
            }
        } else {
            $states = ModuleState::all();
            $validStates = $states->filter(function ($state) use ($modulePath) {
                return File::exists("{$modulePath}/{$state->name}");
            });

            if ($validStates->count() > 0) {
                $this->info('All module states:');
                $this->table(
                    ['Name', 'Status', 'Version', 'Last Enabled', 'Last Disabled'],
                    $validStates->map(function ($state) {
                        return [
                            $state->name,
                            $state->status,
                            $state->version,
                            $state->last_enabled_at,
                            $state->last_disabled_at
                        ];
                    })
                );

                // Show warning for orphaned database entries
                $orphanedCount = $states->count() - $validStates->count();
                if ($orphanedCount > 0) {
                    $this->warn("Found {$orphanedCount} orphaned module state(s) in database. Run 'php artisan module:marketplace cleanup' to remove them.");
                }
            } else {
                $this->warn('No valid module states found');
            }
        }
    }
} 
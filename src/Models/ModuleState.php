<?php

namespace RCV\Core\Models;

use Illuminate\Database\Eloquent\Model;

class ModuleState extends Model
{
    protected $fillable = [
        'name',
        'version',
        'description',
        'status',
        'enabled',
        'last_enabled_at',
        'last_disabled_at',
        'applied_migrations',
        'failed_migrations',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'last_enabled_at' => 'datetime',
        'last_disabled_at' => 'datetime',
        'applied_migrations' => 'array',
        'failed_migrations' => 'array',
    ];

    public function isEnabled()
    {
        return $this->enabled;
    }

    public function isDisabled()
    {
        return ! $this->enabled;
    }

    public function hasMigration($migration)
    {
        return in_array($migration, $this->applied_migrations ?? []);
    }

    public function hasFailed($migration)
    {
        return in_array($migration, $this->failed_migrations ?? []);
    }
}

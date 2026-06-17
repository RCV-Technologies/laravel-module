<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Hook System Toggle
    |--------------------------------------------------------------------------
    |
    | Controls whether the hook system and its provider/listeners are loaded.
    | Must be false by default. Set to true after running initialization.
    |
    */
    'enabled' => false,

    /*
    |--------------------------------------------------------------------------
    | Failure Isolation Policy
    |--------------------------------------------------------------------------
    |
    | Determine how to handle exceptions thrown inside hook listeners.
    | Options:
    |   - 'continue': Log error and proceed to the next listener (default).
    |   - 'fail_fast': Throw the exception and stop execution.
    |
    */
    'failure_policy' => 'continue',

    /*
    |--------------------------------------------------------------------------
    | Listener Auto-Discovery
    |--------------------------------------------------------------------------
    |
    | Configuration for auto-discovering listener classes in modules.
    |
    */
    'discovery' => [
        'enabled' => true,
        'directories' => [
            'Hooks', // Subdirectory in Modules to scan (e.g., Modules/Blog/src/Hooks)
        ],
        'cache_enabled' => true,
        'cache_key' => 'rcv_hooks_discovered_listeners',
        'cache_ttl' => 86400, // 24 hours
    ],
];

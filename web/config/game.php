<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Game Engine Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the game engine connection. The game requires the Kotlin engine
    | to be running. See CLAUDE.md for setup instructions.
    |
    */

    'engine_url' => env('GAME_ENGINE_URL', 'http://localhost:8085'),

    /*
    |--------------------------------------------------------------------------
    | Game Settings
    |--------------------------------------------------------------------------
    */

    // Exposure decay rate (per minute when idle)
    'exposure_decay' => env('GAME_EXPOSURE_DECAY', 0.5),

    // Starting credits for new players
    'starting_credits' => env('GAME_STARTING_CREDITS', 5000),

    // Auto-save interval (seconds)
    'autosave_interval' => env('GAME_AUTOSAVE_INTERVAL', 300),

];

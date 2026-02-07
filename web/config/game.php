<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Game Engine Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the game engine connection. The engine can run in "mock" mode
    | for UI development or "kotlin" mode to connect to the real engine.
    |
    */

    'engine' => env('GAME_ENGINE', 'mock'), // 'mock' or 'kotlin'

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

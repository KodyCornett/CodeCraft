<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\GameEngine\GameEngineInterface;
use App\Services\GameEngine\MockGameEngine;
use Illuminate\Support\ServiceProvider;

class GameEngineServiceProvider extends ServiceProvider
{
    /**
     * Register the game engine binding.
     *
     * In production/Phase 2+, this will switch to the real Kotlin
     * engine WebSocket client based on configuration.
     */
    public function register(): void
    {
        $this->app->singleton(GameEngineInterface::class, function ($app) {
            // TODO: Check config to determine if we should use real engine
            // return config('game.use_real_engine')
            //     ? new KotlinGameEngine(config('game.engine_url'))
            //     : new MockGameEngine();

            return new MockGameEngine();
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}

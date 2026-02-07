<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\GameEngine\GameEngineInterface;
use App\Services\GameEngine\KotlinGameEngine;
use App\Services\GameEngine\MockGameEngine;
use Illuminate\Support\ServiceProvider;

class GameEngineServiceProvider extends ServiceProvider
{
    /**
     * Register the game engine binding.
     *
     * Uses mock engine for UI development or real Kotlin engine in production.
     */
    public function register(): void
    {
        $this->app->singleton(GameEngineInterface::class, function ($app) {
            $engineType = config('game.engine', 'mock');

            if ($engineType === 'kotlin') {
                $kotlinEngine = new KotlinGameEngine();

                // Check if Kotlin engine is available, fall back to mock if not
                if ($kotlinEngine->isAvailable()) {
                    return $kotlinEngine;
                }

                // Log warning and fall back to mock
                logger()->warning('Kotlin engine not available, falling back to mock engine');
            }

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

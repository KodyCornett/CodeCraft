<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\GameEngine\GameEngineInterface;
use App\Services\GameEngine\KotlinGameEngine;
use Illuminate\Support\ServiceProvider;

class GameEngineServiceProvider extends ServiceProvider
{
    /**
     * Register the game engine binding.
     *
     * Always uses the Kotlin game engine.
     */
    public function register(): void
    {
        $this->app->singleton(GameEngineInterface::class, function ($app) {
            return new KotlinGameEngine();
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

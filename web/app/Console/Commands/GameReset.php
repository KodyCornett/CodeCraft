<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class GameReset extends Command
{
    protected $signature = 'game:reset';
    protected $description = 'Reset all game state (sessions, cache, databases)';

    public function handle(): int
    {
        $this->info('Resetting game state...');

        // Clear sessions
        $sessionPath = storage_path('framework/sessions');
        $sessionFiles = File::glob($sessionPath . '/*');
        foreach ($sessionFiles as $file) {
            if (basename($file) !== '.gitignore') {
                File::delete($file);
            }
        }
        $this->info('✓ Cleared ' . count($sessionFiles) . ' session files');

        // Clear cache
        $this->call('cache:clear');
        $this->call('config:clear');
        $this->info('✓ Cleared cache and config');

        // Reset Laravel database
        if (File::exists(database_path('database.sqlite'))) {
            File::delete(database_path('database.sqlite'));
            File::put(database_path('database.sqlite'), '');
            $this->call('migrate', ['--force' => true]);
            $this->info('✓ Reset Laravel database');
        }

        // Delete Kotlin engine database
        $engineDb = base_path('../engine/codecraft.db');
        if (File::exists($engineDb)) {
            File::delete($engineDb);
            $this->info('✓ Deleted Kotlin engine database');
        }

        $this->newLine();
        $this->warn('⚠️  Don\'t forget to clear your browser cookies or use incognito mode!');
        $this->info('Game state has been reset. Start fresh with: php artisan serve');

        return Command::SUCCESS;
    }
}
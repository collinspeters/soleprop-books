<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;

class RailwayServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register Railway-specific services
        if ($this->isRailwayEnvironment()) {
            $this->configureForRailway();
        }
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        if ($this->isRailwayEnvironment()) {
            $this->bootRailwayServices();
        }
    }

    /**
     * Check if running on Railway
     */
    private function isRailwayEnvironment(): bool
    {
        return !empty(env('RAILWAY_ENVIRONMENT')) || 
               !empty(env('RAILWAY_STATIC_URL')) ||
               !empty(env('MYSQLHOST'));
    }

    /**
     * Configure application for Railway
     */
    private function configureForRailway(): void
    {
        // Set database connection to use Railway variables
        if (env('MYSQLHOST')) {
            Config::set('database.default', 'mysql');
            Config::set('database.connections.mysql.host', env('MYSQLHOST'));
            Config::set('database.connections.mysql.port', env('MYSQLPORT', 3306));
            Config::set('database.connections.mysql.database', env('MYSQLDATABASE'));
            Config::set('database.connections.mysql.username', env('MYSQLUSER'));
            Config::set('database.connections.mysql.password', env('MYSQLPASSWORD'));
        }

        // Configure Redis if available
        if (env('REDIS_URL')) {
            $redisUrl = parse_url(env('REDIS_URL'));
            Config::set('database.redis.default.host', $redisUrl['host'] ?? '127.0.0.1');
            Config::set('database.redis.default.port', $redisUrl['port'] ?? 6379);
            if (isset($redisUrl['pass'])) {
                Config::set('database.redis.default.password', $redisUrl['pass']);
            }
        }

        // Set app URL from Railway
        if (env('RAILWAY_STATIC_URL')) {
            Config::set('app.url', env('RAILWAY_STATIC_URL'));
        }

        // Configure for production
        Config::set('app.debug', false);
        Config::set('logging.default', 'single');
        Config::set('logging.level', 'error');
    }

    /**
     * Boot Railway-specific services
     */
    private function bootRailwayServices(): void
    {
        // Add Railway-specific middleware or services here
        Log::info('Railway environment detected and configured');
    }
}
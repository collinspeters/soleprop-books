<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Exception;

class RailwaySetup extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'railway:setup {--force : Force setup even if already configured}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Set up Akaunting for Railway deployment';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🚀 Setting up Akaunting for Railway...');

        try {
            // Check if running on Railway
            if (!$this->isRailwayEnvironment()) {
                $this->warn('⚠️  This command is designed for Railway environment');
                if (!$this->confirm('Continue anyway?')) {
                    return 1;
                }
            }

            // Test database connection
            $this->testDatabaseConnection();

            // Set up storage
            $this->setupStorage();

            // Clear and optimize caches
            $this->optimizeApplication();

            // Set up queue tables if needed
            $this->setupQueues();

            // Verify S3 connection if configured
            $this->verifyS3Connection();

            $this->info('✅ Railway setup completed successfully!');
            return 0;

        } catch (Exception $e) {
            $this->error('❌ Setup failed: ' . $e->getMessage());
            return 1;
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
     * Test database connection
     */
    private function testDatabaseConnection(): void
    {
        $this->info('🔍 Testing database connection...');
        
        try {
            DB::connection()->getPdo();
            $this->info('✅ Database connection successful');
        } catch (Exception $e) {
            throw new Exception('Database connection failed: ' . $e->getMessage());
        }
    }

    /**
     * Set up storage directories
     */
    private function setupStorage(): void
    {
        $this->info('📁 Setting up storage directories...');

        $directories = [
            'storage/app/uploads',
            'storage/app/temp',
            'storage/framework/cache',
            'storage/framework/sessions',
            'storage/framework/views',
            'storage/logs',
            'bootstrap/cache',
        ];

        foreach ($directories as $dir) {
            if (!file_exists($dir)) {
                mkdir($dir, 0775, true);
                $this->line("Created: {$dir}");
            }
        }

        // Set proper permissions
        chmod('storage', 0775);
        chmod('bootstrap/cache', 0775);
        
        $this->info('✅ Storage directories configured');
    }

    /**
     * Optimize application for production
     */
    private function optimizeApplication(): void
    {
        $this->info('⚡ Optimizing application...');

        // Clear all caches
        Artisan::call('cache:clear');
        Artisan::call('config:clear');
        Artisan::call('route:clear');
        Artisan::call('view:clear');

        // Cache for production
        Artisan::call('config:cache');
        Artisan::call('route:cache');
        Artisan::call('view:cache');

        $this->info('✅ Application optimized');
    }

    /**
     * Set up queue tables
     */
    private function setupQueues(): void
    {
        $this->info('🔄 Setting up queue system...');

        try {
            // Check if jobs table exists
            if (!DB::getSchemaBuilder()->hasTable('jobs')) {
                Artisan::call('queue:table');
                Artisan::call('migrate', ['--force' => true]);
                $this->info('✅ Queue tables created');
            } else {
                $this->info('✅ Queue tables already exist');
            }
        } catch (Exception $e) {
            $this->warn('⚠️  Queue setup skipped: ' . $e->getMessage());
        }
    }

    /**
     * Verify S3 connection
     */
    private function verifyS3Connection(): void
    {
        if (env('FILESYSTEM_DISK') !== 's3') {
            $this->warn('⚠️  S3 not configured - file uploads will use local storage');
            return;
        }

        $this->info('☁️  Verifying S3 connection...');

        try {
            $disk = Storage::disk('s3');
            $testFile = 'railway-test-' . time() . '.txt';
            
            // Test write
            $disk->put($testFile, 'Railway deployment test');
            
            // Test read
            $content = $disk->get($testFile);
            
            // Clean up
            $disk->delete($testFile);
            
            if ($content === 'Railway deployment test') {
                $this->info('✅ S3 connection verified');
            } else {
                throw new Exception('S3 read/write test failed');
            }
        } catch (Exception $e) {
            $this->error('❌ S3 verification failed: ' . $e->getMessage());
            $this->warn('⚠️  Please check your S3 credentials and bucket configuration');
        }
    }
}
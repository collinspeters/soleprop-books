<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;

class RailwayPerformance
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);

        $response = $next($request);

        // Calculate performance metrics
        $executionTime = microtime(true) - $startTime;
        $memoryUsed = memory_get_usage(true) - $startMemory;
        $peakMemory = memory_get_peak_usage(true);

        // Add performance headers for debugging
        if (config('app.debug') || env('RAILWAY_ENVIRONMENT')) {
            $response->headers->set('X-Railway-Execution-Time', round($executionTime * 1000, 2) . 'ms');
            $response->headers->set('X-Railway-Memory-Used', $this->formatBytes($memoryUsed));
            $response->headers->set('X-Railway-Peak-Memory', $this->formatBytes($peakMemory));
            $response->headers->set('X-Railway-Environment', env('RAILWAY_ENVIRONMENT', 'unknown'));
        }

        // Log slow requests
        if ($executionTime > 2.0) {
            Log::warning('Slow request detected', [
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'execution_time' => $executionTime,
                'memory_used' => $memoryUsed,
                'peak_memory' => $peakMemory,
                'user_agent' => $request->userAgent(),
            ]);
        }

        // Log high memory usage
        if ($peakMemory > 128 * 1024 * 1024) { // 128MB
            Log::warning('High memory usage detected', [
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'peak_memory' => $peakMemory,
                'execution_time' => $executionTime,
            ]);
        }

        return $response;
    }

    /**
     * Format bytes to human readable format
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $power = floor(log($bytes, 1024));
        return round($bytes / pow(1024, $power), 2) . ' ' . $units[$power];
    }
}
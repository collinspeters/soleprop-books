<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckAiEnabled
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        // Check if AI is enabled in settings
        $aiEnabled = setting('ai.reports.enabled', false);
        
        if (!$aiEnabled) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'error' => 'AI features are not enabled for this company.',
                ], 403);
            }
            
            abort(403, 'AI features are not enabled.');
        }

        return $next($request);
    }
}
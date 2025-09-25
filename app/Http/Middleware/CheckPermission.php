<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        if (!$request->user()) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $user = $request->user();
        
        try {
            // Check if user has the required permission
            if (!$user->hasPermission($permission)) {
                return response()->json([
                    'message' => 'Forbidden. Required permission: ' . $permission
                ], 403);
            }
        } catch (\Exception $e) {
            // If database is not ready or there's a connection issue, log and return 503
            \Illuminate\Support\Facades\Log::warning('Permission check failed due to database issue: ' . $e->getMessage());
            return response()->json([
                'message' => 'Service temporarily unavailable. Database connection issue.',
                'error' => 'DATABASE_CONNECTION_ERROR'
            ], 503);
        }

        return $next($request);
    }
}

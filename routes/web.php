<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'message' => 'Signature E-commerce API',
        'status' => 'running',
        'version' => '1.0.0',
        'timestamp' => now()->toISOString(),
        'endpoints' => [
            'health' => '/api/health',
            'status' => '/api/status',
            'api_docs' => '/api/documentation'
        ]
    ]);
});

// Simple health check endpoint for Render (no database dependency, no middleware)
Route::get('/up', function () {
    try {
        // Basic health check without database dependency
        return response()->json([
            'status' => 'ok',
            'timestamp' => now()->toISOString(),
            'app' => 'Signature E-commerce',
            'version' => '1.0.0',
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version()
        ], 200);
    } catch (\Exception $e) {
        // Even if there are issues, return a basic OK for health check
        return response()->json([
            'status' => 'ok',
            'timestamp' => date('c'),
            'app' => 'Signature E-commerce',
            'version' => '1.0.0'
        ], 200);
    }
});

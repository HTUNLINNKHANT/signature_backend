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

// Simple health check endpoint for Render (no database dependency)
Route::get('/up', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now()->toISOString(),
        'app' => 'Signature E-commerce',
        'version' => '1.0.0'
    ], 200);
});

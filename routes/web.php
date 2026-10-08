<?php

use Illuminate\Support\Facades\Route;

// API-only backend: the UI is served by the React SPA (lead_f).
Route::get('/', fn () => response()->json([
    'name' => config('app.name'),
    'api' => url('/api/v1'),
]));

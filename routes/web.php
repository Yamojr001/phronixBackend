<?php

use Illuminate\Support\Facades\Route;

// The decoupled frontend will be hosted separately or served from a public directory.
// Laravel now acts primarily as an API.
Route::get('/', function () {
    return response()->json(['message' => 'Phronix AI API is running.']);
});
<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// API controllers land in the next build batch.
// Keeping only the Sanctum sanity check for now.

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
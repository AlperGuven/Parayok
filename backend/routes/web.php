<?php

use App\Http\Controllers\Auth\JiraAuthController;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

// Route::get('/', function () {
//     return view('welcome');
// });

Route::get('/auth/jira', [JiraAuthController::class, 'redirect']);

Broadcast::routes(['middleware' => ['auth:sanctum']]);

// SPA Catch-all Route (Must be last)
Route::get('/{any}', function () {
    return file_get_contents(public_path('index.html'));
})->where('any', '.*');

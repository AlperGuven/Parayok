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
Route::get('/{any}', function ($any = null) {
    $publicPath = public_path($any);
    
    // Serve static files directly if they exist in public/
    if ($any && file_exists($publicPath) && is_file($publicPath)) {
        return response()->file($publicPath);
    }
    
    return file_get_contents(public_path('index.html'));
})->where('any', '.*');

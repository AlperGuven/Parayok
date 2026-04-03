<?php

use App\Http\Controllers\Auth\JiraAuthController;
use App\Http\Controllers\GuestLoginController;
use App\Http\Controllers\IssueController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\VoteController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Broadcast;

Route::post('/broadcasting/auth', function (Illuminate\Http\Request $request) {
    return Broadcast::auth($request);
})->middleware('auth:sanctum');

require base_path('routes/channels.php');

// Guest login
Route::post('/auth/guest/login', [GuestLoginController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [JiraAuthController::class, 'user']);
    Route::post('/user/profile', [JiraAuthController::class, 'updateProfile']);
    Route::post('/auth/logout', [JiraAuthController::class, 'logout']);
    Route::get('/rooms', [RoomController::class, 'index']);
    Route::post('/rooms', [RoomController::class, 'store']);
    Route::get('/rooms/{uuid}', [RoomController::class, 'show']);
    Route::post('/rooms/{uuid}/join', [RoomController::class, 'join']);
    Route::post('/rooms/{uuid}/leave', [RoomController::class, 'leave']);
    Route::post('/rooms/{uuid}/complete', [RoomController::class, 'complete']);
    Route::post('/rooms/{uuid}/reopen', [RoomController::class, 'reopen']);
    Route::post('/rooms/{uuid}/ice-breaker', [RoomController::class, 'updateIceBreaker']);
    Route::post('/rooms/{uuid}/delete', [RoomController::class, 'destroy']);
    Route::delete('/rooms/{uuid}', [RoomController::class, 'destroy']);
    Route::post('/rooms/{uuid}/issues/from-url', [IssueController::class, 'storeFromUrl']);
    Route::post('/rooms/{uuid}/issues/from-jql', [IssueController::class, 'storeFromJql']);
    Route::delete('/rooms/{uuid}/issues/{id}', [IssueController::class, 'destroy']);
    Route::patch('/rooms/{uuid}/issues/{id}/order', [IssueController::class, 'reorder']);
    Route::post('/rooms/{uuid}/issues/{id}/vote', [VoteController::class, 'castVote']);
    Route::post('/rooms/{uuid}/issues/{id}/start-voting', [VoteController::class, 'startVoting']);
    Route::post('/rooms/{uuid}/issues/{id}/reveal', [VoteController::class, 'revealVotes']);
    Route::post('/rooms/{uuid}/issues/{id}/reset', [VoteController::class, 'resetVoting']);
    Route::post('/rooms/{uuid}/issues/{id}/update-score', [VoteController::class, 'updateFinalScore']);
});

Route::post('/auth/jira/callback', [JiraAuthController::class, 'handleCallback']);

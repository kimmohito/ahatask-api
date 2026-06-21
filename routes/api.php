<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\OrganizationController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\TaskController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware(['auth:api', 'org'])->group(function () {

    // Auth
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Organizations
    Route::apiResource('organizations', OrganizationController::class);

    // Projects
    Route::apiResource('projects', ProjectController::class);

    // Tasks
    Route::apiResource('tasks', TaskController::class);
    Route::get('tasks/{task}/history', [TaskController::class, 'history']);
    Route::get('tasks/{task}/comments', [TaskController::class, 'comments']);
    Route::post('tasks/{task}/comments', [TaskController::class, 'storeComment']);
    Route::post('tasks/{task}/favorite', [TaskController::class, 'favorite']);
    Route::delete('tasks/{task}/favorite', [TaskController::class, 'unfavorite']);
    Route::post('tasks/{task}/bookmark', [TaskController::class, 'bookmark']);
    Route::delete('tasks/{task}/bookmark', [TaskController::class, 'unbookmark']);
    Route::post('tasks/{task}/pin', [TaskController::class, 'pin']);
    Route::delete('tasks/{task}/pin', [TaskController::class, 'unpin']);

    // Helper endpoints used by frontend
    Route::get('statuses', [TaskController::class, 'statuses']);
    Route::get('priorities', [TaskController::class, 'priorities']);
    Route::get('users', [TaskController::class, 'users']);
    // Global search endpoint (alias to tasks index)
    Route::get('search', [TaskController::class, 'index']);

});


<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
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

    // Organizations (admin/superadmin)
    Route::apiResource('organizations', OrganizationController::class)
        ->middleware('permission:manage organizations');

    // Projects (admin/superadmin)
    Route::apiResource('projects', ProjectController::class)
        ->middleware('permission:manage projects');

    // Tasks
    Route::get('tasks', [TaskController::class, 'index'])->middleware('permission:view tasks');
    Route::get('tasks/{task}', [TaskController::class, 'show'])->middleware('permission:view tasks');
    Route::post('tasks', [TaskController::class, 'store'])->middleware('permission:create tasks');
    Route::put('tasks/{task}', [TaskController::class, 'update'])->middleware('permission:edit tasks');
    Route::patch('tasks/{task}', [TaskController::class, 'update'])->middleware('permission:edit tasks');
    Route::delete('tasks/{task}', [TaskController::class, 'destroy'])->middleware('permission:delete tasks');

    Route::get('tasks/{task}/history', [TaskController::class, 'history'])->middleware('permission:view tasks');
    Route::get('tasks/{task}/comments', [TaskController::class, 'comments'])->middleware('permission:view tasks');
    Route::post('tasks/{task}/comments', [TaskController::class, 'storeComment'])->middleware('permission:edit tasks');
    Route::post('tasks/{task}/favorite', [TaskController::class, 'favorite'])->middleware('permission:edit tasks');
    Route::delete('tasks/{task}/favorite', [TaskController::class, 'unfavorite'])->middleware('permission:edit tasks');
    Route::post('tasks/{task}/bookmark', [TaskController::class, 'bookmark'])->middleware('permission:edit tasks');
    Route::delete('tasks/{task}/bookmark', [TaskController::class, 'unbookmark'])->middleware('permission:edit tasks');
    Route::post('tasks/{task}/pin', [TaskController::class, 'pin'])->middleware('permission:edit tasks');
    Route::delete('tasks/{task}/pin', [TaskController::class, 'unpin'])->middleware('permission:edit tasks');

    // Helper endpoints used by frontend
    Route::get('statuses', [TaskController::class, 'statuses'])->middleware('permission:view tasks');
    Route::get('priorities', [TaskController::class, 'priorities'])->middleware('permission:view tasks');
    Route::get('users', [TaskController::class, 'users'])->middleware('permission:view tasks');
    Route::get('dashboard', [DashboardController::class, 'overview'])->middleware('permission:view tasks');
    // Global search endpoint (alias to tasks index)
    Route::get('search', [TaskController::class, 'index'])->middleware('permission:view tasks');

});


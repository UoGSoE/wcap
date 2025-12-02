<?php

use App\Http\Controllers\Api\ManagerPlanController;
use App\Http\Controllers\Api\PlanController;
use App\Http\Controllers\Api\ReportController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// API v1 - Protected by Sanctum
Route::middleware('auth:sanctum')->prefix('v1')->group(function () {

    // Personal Planning Data
    // Accessible by: ALL authenticated users with valid token
    Route::get('/plan', [PlanController::class, 'myPlan']);
    Route::post('/plan', [PlanController::class, 'upsert']);
    Route::delete('/plan/{id}', [PlanController::class, 'destroy']);

    // Reference Data
    Route::get('/locations', [PlanController::class, 'locations']);

    // Organizational Reporting
    // Accessible by: Managers and Admins only (requires view:team-plans OR view:all-plans)
    Route::prefix('reports')
        ->middleware('abilities:view:team-plans,view:all-plans')
        ->group(function () {
            Route::get('/team', [ReportController::class, 'team']);
            Route::get('/location', [ReportController::class, 'location']);
            Route::get('/coverage', [ReportController::class, 'coverage']);

            if (config('wcap.services_enabled')) {
                Route::get('/service-availability', [ReportController::class, 'serviceAvailability']);
            }
        });

    // Manager Plan Management
    // Accessible by: Managers and Admins with manage:team-plans ability
    Route::prefix('manager')
        ->middleware('abilities:manage:team-plans')
        ->group(function () {
            Route::get('/team-members', [ManagerPlanController::class, 'teamMembers']);
            Route::get('/team-members/{userId}/plan', [ManagerPlanController::class, 'show']);
            Route::post('/team-members/{userId}/plan', [ManagerPlanController::class, 'upsert']);
            Route::delete('/team-members/{userId}/plan/{entryId}', [ManagerPlanController::class, 'destroy']);
        });
});

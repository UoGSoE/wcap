<?php

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

    // Organizational Reporting
    // Accessible by: Managers and Admins only (requires view:team-plans OR view:all-plans)
    Route::prefix('reports')
        ->middleware('abilities:view:team-plans,view:all-plans')
        ->group(function () {
            Route::get('/team', [ReportController::class, 'team']);
            Route::get('/location', [ReportController::class, 'location']);
            Route::get('/coverage', [ReportController::class, 'coverage']);
            Route::get('/service-availability', [ReportController::class, 'serviceAvailability']);
        });
});

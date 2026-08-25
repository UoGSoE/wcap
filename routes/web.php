<?php

use App\Http\Controllers\HomeRedirectController;
use App\Http\Controllers\ImpersonationController;
use App\Livewire\AdminLocations;
use App\Livewire\AdminServices;
use App\Livewire\AdminTeams;
use App\Livewire\AdminUsers;
use App\Livewire\ImportPlanEntries;
use App\Livewire\ManagerReport;
use App\Livewire\ManageTeamEntries;
use App\Livewire\OccupancyReport;
use App\Livewire\Profile;
use Illuminate\Support\Facades\Route;

require __DIR__.'/sso-auth.php';

Route::middleware('auth')->group(function () {
    Route::get('/', HomeRedirectController::class)->name('home');
    Route::get('/profile', Profile::class)->name('profile');
    Route::post('/impersonate/stop', [ImpersonationController::class, 'stop'])->name('impersonate.stop');
    Route::post('/impersonate/{user}', [ImpersonationController::class, 'start'])->name('impersonate.start');
    Route::get('/manager/report', ManagerReport::class)->name('manager.report');
    Route::middleware('manager')->group(function () {
        Route::get('/manager/occupancy', OccupancyReport::class)->name('manager.occupancy');
        Route::get('/manager/entries', ManageTeamEntries::class)->name('manager.entries');
        Route::get('/manager/import', ImportPlanEntries::class)->name('manager.import');
    });
    Route::middleware('admin')->group(function () {
        Route::get('/admin/teams', AdminTeams::class)->name('admin.teams');
        Route::get('/admin/services', AdminServices::class)->name('admin.services');
        Route::get('/admin/locations', AdminLocations::class)->name('admin.locations');
        Route::get('/admin/users', AdminUsers::class)->name('admin.users');
    });
});

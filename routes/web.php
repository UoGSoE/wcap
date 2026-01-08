<?php

use Illuminate\Support\Facades\Route;

require __DIR__.'/sso-auth.php';

Route::middleware('auth')->group(function () {
    Route::get('/', \App\Http\Controllers\HomeRedirectController::class)->name('home');
    Route::get('/profile', \App\Livewire\Profile::class)->name('profile');
    Route::group(['middleware' => 'manager'], function () {
        Route::get('/manager/report', \App\Livewire\ManagerReport::class)->name('manager.report');
        Route::get('/manager/occupancy', \App\Livewire\OccupancyReport::class)->name('manager.occupancy');
        Route::get('/manager/entries', \App\Livewire\ManageTeamEntries::class)->name('manager.entries');
        Route::get('/manager/import', \App\Livewire\ImportPlanEntries::class)->name('manager.import');
        Route::get('/admin/teams', \App\Livewire\AdminTeams::class)->name('admin.teams');
        Route::get('/admin/services', \App\Livewire\AdminServices::class)->name('admin.services');
        Route::get('/admin/locations', \App\Livewire\AdminLocations::class)->name('admin.locations');
        Route::get('/admin/users', \App\Livewire\AdminUsers::class)->name('admin.users');
    });
});

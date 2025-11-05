<?php

use Illuminate\Support\Facades\Route;

require __DIR__.'/sso-auth.php';

Route::middleware('auth')->group(function () {
    Route::get('/', \App\Livewire\HomePage::class)->name('home');
    Route::get('/profile', \App\Livewire\Profile::class)->name('profile');
    Route::get('/manager/report', \App\Livewire\ManagerReport::class)->name('manager.report');
    Route::get('/admin/teams', \App\Livewire\AdminTeams::class)->name('admin.teams');
    Route::get('/admin/users', \App\Livewire\AdminUsers::class)->name('admin.users');
});

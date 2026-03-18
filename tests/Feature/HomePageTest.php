<?php

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('home page loads the profile page for regular users', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('home'))->assertRedirect(route('profile'));
});

test('home page redirects to manager edit entries page for managers', function () {
    $manager = User::factory()->create();
    $team = Team::factory()->create(['manager_id' => $manager->id]);

    $this->actingAs($manager)->get(route('home'))->assertRedirect(route('manager.entries'));
});

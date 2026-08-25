<?php

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('guests are redirected to the login page', function () {
    $this->get('/')->assertRedirect(route('login'));
});

test('home page loads the profile page for regular users', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('home'))->assertRedirect(route('profile'));
});

test('home page redirects to manager edit entries page for managers', function () {
    $manager = User::factory()->create();
    $team = Team::factory()->create(['manager_id' => $manager->id]);

    $this->actingAs($manager)->get(route('home'))->assertRedirect(route('manager.entries'));
});

test('the layout renders a skip link to the main content', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('profile'))
        ->assertSee('Skip to main content')
        ->assertSee('href="#main-content"', false);
});

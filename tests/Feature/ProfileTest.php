<?php

use App\Enums\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

test('profile page renders', function () {
    $user = User::factory()->create();

    actingAs($user);

    Livewire::test(\App\Livewire\Profile::class)
        ->assertOk()
        ->assertSee('Profile Settings')
        ->assertSee('Default Location')
        ->assertSee('Default Work Category');
});

test('profile loads existing defaults', function () {
    $user = User::factory()->create([
        'default_location' => Location::OTHER->value,
        'default_category' => 'Active Directory',
    ]);

    actingAs($user);

    Livewire::test(\App\Livewire\Profile::class)
        ->assertSet('default_location', Location::OTHER->value)
        ->assertSet('default_category', 'Active Directory');
});

test('saving profile updates user defaults', function () {
    $user = User::factory()->create([
        'default_location' => '',
        'default_category' => '',
    ]);

    actingAs($user);

    Livewire::test(\App\Livewire\Profile::class)
        ->set('default_location', Location::JWS->value)
        ->set('default_category', 'Support Tickets')
        ->call('save')
        ->assertOk();

    $user->refresh();

    expect($user->default_location)->toBe(Location::JWS->value);
    expect($user->default_category)->toBe('Support Tickets');
});

test('profile allows empty defaults', function () {
    $user = User::factory()->create([
        'default_location' => Location::OTHER->value,
        'default_category' => 'Something',
    ]);

    actingAs($user);

    Livewire::test(\App\Livewire\Profile::class)
        ->set('default_location', '')
        ->set('default_category', '')
        ->call('save')
        ->assertHasNoErrors();

    $user->refresh();

    expect($user->default_location)->toBe('');
    expect($user->default_category)->toBe('');
});

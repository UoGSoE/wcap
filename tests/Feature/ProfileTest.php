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

test('non-admin users only see their own tokens', function () {
    $user = User::factory()->create(['is_admin' => false]);
    $otherUser = User::factory()->create();

    $userToken = $user->createToken('My Token', ['view:own-plan']);
    $otherToken = $otherUser->createToken('Other Token', ['view:own-plan']);

    actingAs($user);

    Livewire::test(\App\Livewire\Profile::class)
        ->assertSet('showAllTokens', false)
        ->assertSee('My Token')
        ->assertDontSee('Other Token');
});

test('admin users can toggle to view all tokens', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $otherUser = User::factory()->create();

    $adminToken = $admin->createToken('Admin Token', ['view:all-plans']);
    $otherToken = $otherUser->createToken('User Token', ['view:own-plan']);

    actingAs($admin);

    Livewire::test(\App\Livewire\Profile::class)
        ->assertSet('showAllTokens', false)
        ->assertSee('Admin Token')
        ->assertDontSee('User Token')
        ->set('showAllTokens', true)
        ->assertSee('Admin Token')
        ->assertSee('User Token')
        ->assertSee($otherUser->full_name);
});

test('non-admin users cannot see showAllTokens toggle', function () {
    $user = User::factory()->create(['is_admin' => false]);

    actingAs($user);

    Livewire::test(\App\Livewire\Profile::class)
        ->assertDontSee('View All Tokens');
});

test('admin users can see showAllTokens toggle', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    actingAs($admin);

    Livewire::test(\App\Livewire\Profile::class)
        ->assertSee('View All Tokens');
});

test('non-admin users can only revoke their own tokens', function () {
    $user = User::factory()->create(['is_admin' => false]);
    $otherUser = User::factory()->create();

    $userToken = $user->createToken('My Token', ['view:own-plan']);
    $otherToken = $otherUser->createToken('Other Token', ['view:own-plan']);

    actingAs($user);

    Livewire::test(\App\Livewire\Profile::class)
        ->call('revokeToken', $otherToken->accessToken->id);

    expect($user->tokens()->count())->toBe(1);
    expect($otherUser->tokens()->count())->toBe(1);
});

test('admin users can revoke any token when toggle is on', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $otherUser = User::factory()->create();

    $adminToken = $admin->createToken('Admin Token', ['view:all-plans']);
    $otherToken = $otherUser->createToken('User Token', ['view:own-plan']);

    actingAs($admin);

    Livewire::test(\App\Livewire\Profile::class)
        ->set('showAllTokens', true)
        ->call('revokeToken', $otherToken->accessToken->id);

    expect($admin->tokens()->count())->toBe(1);
    expect($otherUser->tokens()->count())->toBe(0);
});

test('admin users cannot revoke other tokens when toggle is off', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $otherUser = User::factory()->create();

    $adminToken = $admin->createToken('Admin Token', ['view:all-plans']);
    $otherToken = $otherUser->createToken('User Token', ['view:own-plan']);

    actingAs($admin);

    Livewire::test(\App\Livewire\Profile::class)
        ->set('showAllTokens', false)
        ->call('revokeToken', $otherToken->accessToken->id);

    expect($admin->tokens()->count())->toBe(1);
    expect($otherUser->tokens()->count())->toBe(1);
});

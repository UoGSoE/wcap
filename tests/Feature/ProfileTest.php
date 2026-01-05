<?php

use App\Models\Location;
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
    $location = Location::factory()->create(['slug' => 'other', 'name' => 'Other']);
    $user = User::factory()->create([
        'default_location_id' => $location->id,
        'default_category' => 'Active Directory',
    ]);

    actingAs($user);

    Livewire::test(\App\Livewire\Profile::class)
        ->assertSet('default_location_id', $location->id)
        ->assertSet('default_category', 'Active Directory');
});

test('saving profile updates user defaults', function () {
    $location = Location::factory()->create(['slug' => 'jws', 'name' => 'JWS']);
    $user = User::factory()->create([
        'default_location_id' => null,
        'default_category' => '',
    ]);

    actingAs($user);

    Livewire::test(\App\Livewire\Profile::class)
        ->set('default_location_id', $location->id)
        ->set('default_category', 'Support Tickets')
        ->call('save')
        ->assertOk();

    $user->refresh();

    expect($user->default_location_id)->toBe($location->id);
    expect($user->default_category)->toBe('Support Tickets');
});

test('profile allows empty defaults', function () {
    $location = Location::factory()->create(['slug' => 'other', 'name' => 'Other']);
    $user = User::factory()->create([
        'default_location_id' => $location->id,
        'default_category' => 'Something',
    ]);

    actingAs($user);

    Livewire::test(\App\Livewire\Profile::class)
        ->set('default_location_id', null)
        ->set('default_category', '')
        ->call('save')
        ->assertHasNoErrors();

    $user->refresh();

    expect($user->default_location_id)->toBeNull();
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

test('clicking token name sets selectedTokenId', function () {
    $user = User::factory()->create();
    $token = $user->createToken('Test Token', ['view:own-plan']);

    actingAs($user);

    Livewire::test(\App\Livewire\Profile::class)
        ->assertSet('selectedTokenId', null)
        ->call('selectToken', $token->accessToken->id)
        ->assertSet('selectedTokenId', $token->accessToken->id);
});

test('clicking same token again clears selectedTokenId', function () {
    $user = User::factory()->create();
    $token = $user->createToken('Test Token', ['view:own-plan']);

    actingAs($user);

    Livewire::test(\App\Livewire\Profile::class)
        ->call('selectToken', $token->accessToken->id)
        ->assertSet('selectedTokenId', $token->accessToken->id)
        ->call('selectToken', $token->accessToken->id)
        ->assertSet('selectedTokenId', null);
});

test('documentation shows correct endpoints for view:own-plan only', function () {
    $user = User::factory()->create(['is_admin' => false]);
    $token = $user->createToken('Staff Token', ['view:own-plan']);

    actingAs($user);

    Livewire::test(\App\Livewire\Profile::class)
        ->call('selectToken', $token->accessToken->id)
        ->assertSee('Personal Plan')
        ->assertSee('/api/v1/plan')
        ->assertDontSee('/api/v1/reports/team')
        ->assertDontSee('Team Report');
});

test('documentation shows all endpoints for view:team-plans ability', function () {
    $manager = User::factory()->create(['is_admin' => false]);
    $manager->managedTeams()->create(['name' => 'Test Team']);
    $token = $manager->createToken('Manager Token', ['view:own-plan', 'view:team-plans']);

    actingAs($manager);

    $component = Livewire::test(\App\Livewire\Profile::class)
        ->call('selectToken', $token->accessToken->id)
        ->assertSee('Personal Plan')
        ->assertSee('/api/v1/plan')
        ->assertSee('Team Report')
        ->assertSee('/api/v1/reports/team')
        ->assertSee('Location Report')
        ->assertSee('/api/v1/reports/location')
        ->assertSee('Coverage Report')
        ->assertSee('/api/v1/reports/coverage');

    if (config('wcap.services_enabled')) {
        $component->assertSee('Service Availability')
            ->assertSee('/api/v1/reports/service-availability');
    }
});

test('no documentation section when user has no tokens', function () {
    $user = User::factory()->create();

    actingAs($user);

    Livewire::test(\App\Livewire\Profile::class)
        ->assertDontSee('How to Use Your API Token');
});

test('token placeholder appears in CLI examples', function () {
    $user = User::factory()->create();
    $token = $user->createToken('Test Token', ['view:own-plan']);

    actingAs($user);

    Livewire::test(\App\Livewire\Profile::class)
        ->call('selectToken', $token->accessToken->id)
        ->assertSee('Authorization: Bearer YOUR_TOKEN_HERE')
        ->assertSee('Replace')
        ->assertSee('YOUR_TOKEN_HERE')
        ->assertSee('curl -H');
});

test('token placeholder appears in PowerBI examples', function () {
    $user = User::factory()->create();
    $token = $user->createToken('Test Token', ['view:own-plan']);

    actingAs($user);

    Livewire::test(\App\Livewire\Profile::class)
        ->call('selectToken', $token->accessToken->id)
        ->assertSee('Bearer YOUR_TOKEN_HERE')
        ->assertSee('Replace')
        ->assertSee('with the token you received when you created it');
});

test('documentation shows CRUD endpoints for view:own-plan', function () {
    $user = User::factory()->create();
    $token = $user->createToken('Test Token', ['view:own-plan']);

    actingAs($user);

    Livewire::test(\App\Livewire\Profile::class)
        ->call('selectToken', $token->accessToken->id)
        ->assertSee('Personal Plan')
        ->assertSee('Create/Update Plan Entries')
        ->assertSee('Delete Plan Entry')
        ->assertSee('List Locations')
        ->assertSee('POST')
        ->assertSee('DELETE')
        ->assertSee('Create New Entry')
        ->assertSee('Create/Update Plan Entries')
        ->assertSee('curl -X POST')
        ->assertSee('curl -X DELETE')
        ->assertSee('entries')
        ->assertSee('entry_date')
        ->assertSee('location');
});

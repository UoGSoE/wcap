<?php

use App\Models\Location;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

test('non-admin cannot access user management page', function () {
    $user = User::factory()->create(['is_admin' => false]);

    $this->actingAs($user)->get(route('admin.users'))->assertForbidden();
});

test('admin can view user management page', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $response = $this->actingAs($admin)->get(route('admin.users'))->assertOk();
    $response->assertSee('User Management');
    $response->assertSee('Create New User');
});

test('admin can see all users in the list', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $user1 = User::factory()->create([
        'surname' => 'Smith',
        'forenames' => 'John',
        'username' => 'jsmith',
        'email' => 'jsmith@example.com',
    ]);
    $user2 = User::factory()->create([
        'surname' => 'Doe',
        'forenames' => 'Jane',
        'username' => 'jdoe',
        'email' => 'jdoe@example.com',
    ]);

    actingAs($admin);

    Livewire::test(\App\Livewire\AdminUsers::class)
        ->assertOk()
        ->assertSee('Smith, John')
        ->assertSee('jsmith')
        ->assertSee('jsmith@example.com')
        ->assertSee('Doe, Jane')
        ->assertSee('jdoe')
        ->assertSee('jdoe@example.com');
});

test('admin can create a new user', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    actingAs($admin);

    Livewire::test(\App\Livewire\AdminUsers::class)
        ->call('createUser')
        ->set('username', 'newuser')
        ->set('email', 'newuser@example.com')
        ->set('surname', 'New')
        ->set('forenames', 'User')
        ->set('isAdmin', false)
        ->set('isStaff', true)
        ->call('save')
        ->assertSet('editingUserId', -1);

    $user = User::where('username', 'newuser')->first();
    expect($user)->not->toBeNull();
    expect($user->email)->toBe('newuser@example.com');
    expect($user->surname)->toBe('New');
    expect($user->forenames)->toBe('User');
    expect($user->is_admin)->toBeFalse();
    expect($user->is_staff)->toBeTrue();
});

test('username must be unique when creating', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    User::factory()->create(['username' => 'existinguser']);

    actingAs($admin);

    Livewire::test(\App\Livewire\AdminUsers::class)
        ->call('createUser')
        ->set('username', 'existinguser')
        ->set('email', 'unique@example.com')
        ->set('surname', 'Test')
        ->set('forenames', 'User')
        ->call('save')
        ->assertHasErrors(['username' => 'unique']);

    $this->assertEquals(1, User::where('username', 'existinguser')->count());
});

test('email must be unique when creating', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    User::factory()->create(['email' => 'existing@example.com']);

    actingAs($admin);

    Livewire::test(\App\Livewire\AdminUsers::class)
        ->call('createUser')
        ->set('username', 'uniqueuser')
        ->set('email', 'existing@example.com')
        ->set('surname', 'Test')
        ->set('forenames', 'User')
        ->call('save')
        ->assertHasErrors(['email' => 'unique']);

    $this->assertEquals(1, User::where('email', 'existing@example.com')->count());
});

test('admin can edit an existing user', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $user = User::factory()->create([
        'username' => 'oldusername',
        'email' => 'old@example.com',
        'surname' => 'Old',
        'forenames' => 'Name',
        'is_admin' => false,
        'is_staff' => true,
    ]);

    actingAs($admin);

    Livewire::test(\App\Livewire\AdminUsers::class)
        ->call('editUser', $user->id)
        ->assertSet('editingUserId', $user->id)
        ->assertSet('username', 'oldusername')
        ->assertSet('email', 'old@example.com')
        ->set('username', 'newusername')
        ->set('email', 'new@example.com')
        ->set('surname', 'Updated')
        ->set('forenames', 'User')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('editingUserId', -1);

    $user->refresh();

    expect($user->username)->toBe('newusername');
    expect($user->email)->toBe('new@example.com');
    expect($user->surname)->toBe('Updated');
    expect($user->forenames)->toBe('User');
});

test('username must be unique when updating but can keep same username', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $user1 = User::factory()->create(['username' => 'user1']);
    $user2 = User::factory()->create(['username' => 'user2']);

    actingAs($admin);

    // Try to change user2's username to user1's - should fail
    Livewire::test(\App\Livewire\AdminUsers::class)
        ->call('editUser', $user2->id)
        ->set('username', 'user1')
        ->call('save')
        ->assertHasErrors(['username' => 'unique']);

    // Keeping the same username should work
    Livewire::test(\App\Livewire\AdminUsers::class)
        ->call('editUser', $user2->id)
        ->set('username', 'user2')
        ->set('surname', 'Updated')
        ->call('save')
        ->assertHasNoErrors();
});

test('email must be unique when updating but can keep same email', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $user1 = User::factory()->create(['email' => 'user1@example.com']);
    $user2 = User::factory()->create(['email' => 'user2@example.com']);

    actingAs($admin);

    // Try to change user2's email to user1's - should fail
    Livewire::test(\App\Livewire\AdminUsers::class)
        ->call('editUser', $user2->id)
        ->set('email', 'user1@example.com')
        ->call('save')
        ->assertHasErrors(['email' => 'unique']);

    // Keeping the same email should work
    Livewire::test(\App\Livewire\AdminUsers::class)
        ->call('editUser', $user2->id)
        ->set('email', 'user2@example.com')
        ->set('surname', 'Updated')
        ->call('save')
        ->assertHasNoErrors();
});

test('admin can promote user to admin', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $user = User::factory()->create(['is_admin' => false]);

    actingAs($admin);

    Livewire::test(\App\Livewire\AdminUsers::class)
        ->call('editUser', $user->id)
        ->set('isAdmin', true)
        ->call('save');

    $user->refresh();

    expect($user->is_admin)->toBeTrue();
});

test('admin can demote another admin', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $otherAdmin = User::factory()->create(['is_admin' => true]);

    actingAs($admin);

    Livewire::test(\App\Livewire\AdminUsers::class)
        ->call('editUser', $otherAdmin->id)
        ->set('isAdmin', false)
        ->call('save');

    $otherAdmin->refresh();

    expect($otherAdmin->is_admin)->toBeFalse();
});

test('admin can change staff status', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $user = User::factory()->create(['is_staff' => true]);

    actingAs($admin);

    Livewire::test(\App\Livewire\AdminUsers::class)
        ->call('editUser', $user->id)
        ->set('isStaff', false)
        ->call('save');

    $user->refresh();

    expect($user->is_staff)->toBeFalse();
});

test('admin can delete a user', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $user = User::factory()->create();

    actingAs($admin);

    Livewire::test(\App\Livewire\AdminUsers::class)
        ->call('confirmDelete', $user->id)
        ->assertSet('deletingUserId', $user->id)
        ->call('deleteUser');

    expect(User::find($user->id))->toBeNull();
});

test('deleting user removes team associations', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $user = User::factory()->create();
    $manager = User::factory()->create();
    $team = Team::factory()->create(['manager_id' => $manager->id]);

    $team->users()->attach($user->id);

    actingAs($admin);

    Livewire::test(\App\Livewire\AdminUsers::class)
        ->call('confirmDelete', $user->id)
        ->call('deleteUser');

    expect($team->users()->where('user_id', $user->id)->count())->toBe(0);
});

test('deleting user removes their plan entries', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $user = User::factory()->create();
    $location = Location::factory()->create(['slug' => 'other']);

    $planEntry = $user->planEntries()->create([
        'entry_date' => '2025-11-04',
        'location_id' => $location->id,
        'note' => 'Test entry',
    ]);

    actingAs($admin);

    Livewire::test(\App\Livewire\AdminUsers::class)
        ->call('confirmDelete', $user->id)
        ->call('deleteUser');

    expect(\App\Models\PlanEntry::find($planEntry->id))->toBeNull();
});

test('deleting user unassigns them as manager from teams', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $manager = User::factory()->create();
    $team = Team::factory()->create(['manager_id' => $manager->id]);

    actingAs($admin);

    Livewire::test(\App\Livewire\AdminUsers::class)
        ->call('confirmDelete', $manager->id)
        ->call('deleteUser');

    $team->refresh();

    expect($team->manager_id)->toBeNull();
});

test('validation requires username', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    actingAs($admin);

    Livewire::test(\App\Livewire\AdminUsers::class)
        ->call('createUser')
        ->set('username', '')
        ->set('email', 'test@example.com')
        ->set('surname', 'Test')
        ->set('forenames', 'User')
        ->call('save')
        ->assertHasErrors(['username' => 'required']);
});

test('validation requires email', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    actingAs($admin);

    Livewire::test(\App\Livewire\AdminUsers::class)
        ->call('createUser')
        ->set('username', 'testuser')
        ->set('email', '')
        ->set('surname', 'Test')
        ->set('forenames', 'User')
        ->call('save')
        ->assertHasErrors(['email' => 'required']);
});

test('validation requires valid email format', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    actingAs($admin);

    Livewire::test(\App\Livewire\AdminUsers::class)
        ->call('createUser')
        ->set('username', 'testuser')
        ->set('email', 'invalid-email')
        ->set('surname', 'Test')
        ->set('forenames', 'User')
        ->call('save')
        ->assertHasErrors(['email' => 'email']);
});

test('validation requires surname', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    actingAs($admin);

    Livewire::test(\App\Livewire\AdminUsers::class)
        ->call('createUser')
        ->set('username', 'testuser')
        ->set('email', 'test@example.com')
        ->set('surname', '')
        ->set('forenames', 'User')
        ->call('save')
        ->assertHasErrors(['surname' => 'required']);
});

test('validation requires forenames', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    actingAs($admin);

    Livewire::test(\App\Livewire\AdminUsers::class)
        ->call('createUser')
        ->set('username', 'testuser')
        ->set('email', 'test@example.com')
        ->set('surname', 'Test')
        ->set('forenames', '')
        ->call('save')
        ->assertHasErrors(['forenames' => 'required']);
});

test('user list shows admin and staff badges', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $adminUser = User::factory()->create(['is_admin' => true, 'is_staff' => true]);
    $staffUser = User::factory()->create(['is_admin' => false, 'is_staff' => true]);
    $neitherUser = User::factory()->create(['is_admin' => false, 'is_staff' => false]);

    actingAs($admin);

    $component = Livewire::test(\App\Livewire\AdminUsers::class);

    $users = $component->viewData('users');

    $foundAdmin = $users->firstWhere('id', $adminUser->id);
    $foundStaff = $users->firstWhere('id', $staffUser->id);
    $foundNeither = $users->firstWhere('id', $neitherUser->id);

    expect($foundAdmin->is_admin)->toBeTrue();
    expect($foundAdmin->is_staff)->toBeTrue();
    expect($foundStaff->is_admin)->toBeFalse();
    expect($foundStaff->is_staff)->toBeTrue();
    expect($foundNeither->is_admin)->toBeFalse();
    expect($foundNeither->is_staff)->toBeFalse();
});

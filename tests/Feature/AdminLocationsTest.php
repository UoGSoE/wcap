<?php

use App\Models\Location;
use App\Models\PlanEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

test('admin can view location management page', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    actingAs($admin);

    Livewire::test(\App\Livewire\AdminLocations::class)
        ->assertOk()
        ->assertSee('Location Management')
        ->assertSee('Create New Location');
});

test('non-admin cannot access location management page', function () {
    $user = User::factory()->create(['is_admin' => false]);

    actingAs($user);

    Livewire::test(\App\Livewire\AdminLocations::class)
        ->assertForbidden();
});

test('admin can see all locations in the list', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $location1 = Location::factory()->create([
        'name' => 'James Watt South',
        'short_label' => 'JWS',
        'slug' => 'jws',
        'is_physical' => true,
    ]);

    $location2 = Location::factory()->create([
        'name' => 'Boyd Orr',
        'short_label' => 'BO',
        'slug' => 'boyd-orr',
        'is_physical' => true,
    ]);

    actingAs($admin);

    Livewire::test(\App\Livewire\AdminLocations::class)
        ->assertOk()
        ->assertSee('James Watt South')
        ->assertSee('JWS')
        ->assertSee('Boyd Orr')
        ->assertSee('BO');
});

test('admin can create a new location', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    actingAs($admin);

    Livewire::test(\App\Livewire\AdminLocations::class)
        ->call('createLocation')
        ->set('locationName', 'New Building')
        ->set('shortLabel', 'NB')
        ->set('isPhysical', true)
        ->call('save')
        ->assertSet('editingLocationId', null);

    $this->assertDatabaseHas('locations', [
        'name' => 'New Building',
        'short_label' => 'NB',
        'slug' => 'new-building',
        'is_physical' => true,
    ]);
});

test('slug is generated uniquely when creating location', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    Location::factory()->create([
        'name' => 'Test Building',
        'slug' => 'test-building',
    ]);

    actingAs($admin);

    Livewire::test(\App\Livewire\AdminLocations::class)
        ->call('createLocation')
        ->set('locationName', 'Test Building 2')
        ->set('shortLabel', 'TB2')
        ->call('save');

    $this->assertDatabaseHas('locations', [
        'name' => 'Test Building 2',
        'slug' => 'test-building-2',
    ]);
});

test('location name must be unique when creating', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    Location::factory()->create(['name' => 'Existing Building']);

    actingAs($admin);

    Livewire::test(\App\Livewire\AdminLocations::class)
        ->call('createLocation')
        ->set('locationName', 'Existing Building')
        ->set('shortLabel', 'EB')
        ->call('save')
        ->assertHasErrors(['locationName' => 'unique']);

    $this->assertEquals(1, Location::where('name', 'Existing Building')->count());
});

test('admin can edit an existing location', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $location = Location::factory()->create([
        'name' => 'Original Name',
        'short_label' => 'ON',
        'is_physical' => true,
    ]);

    actingAs($admin);

    Livewire::test(\App\Livewire\AdminLocations::class)
        ->call('editLocation', $location->id)
        ->assertSet('editingLocationId', $location->id)
        ->assertSet('locationName', 'Original Name')
        ->assertSet('shortLabel', 'ON')
        ->assertSet('isPhysical', true)
        ->set('locationName', 'Updated Name')
        ->set('shortLabel', 'UN')
        ->set('isPhysical', false)
        ->call('save')
        ->assertSet('editingLocationId', null);

    $location->refresh();

    expect($location->name)->toBe('Updated Name');
    expect($location->short_label)->toBe('UN');
    expect($location->is_physical)->toBeFalse();
});

test('editing location does not change its slug', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $location = Location::factory()->create([
        'name' => 'Original Name',
        'slug' => 'original-slug',
    ]);

    actingAs($admin);

    Livewire::test(\App\Livewire\AdminLocations::class)
        ->call('editLocation', $location->id)
        ->set('locationName', 'Completely Different Name')
        ->call('save');

    $location->refresh();

    expect($location->name)->toBe('Completely Different Name');
    expect($location->slug)->toBe('original-slug');
});

test('location name must be unique when updating but can keep same name', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $location1 = Location::factory()->create(['name' => 'Location One']);
    $location2 = Location::factory()->create(['name' => 'Location Two']);

    actingAs($admin);

    // Try to change location2's name to location1's name - should fail
    Livewire::test(\App\Livewire\AdminLocations::class)
        ->call('editLocation', $location2->id)
        ->set('locationName', 'Location One')
        ->call('save')
        ->assertHasErrors(['locationName' => 'unique']);

    // Keeping the same name should work
    Livewire::test(\App\Livewire\AdminLocations::class)
        ->call('editLocation', $location2->id)
        ->set('locationName', 'Location Two')
        ->call('save')
        ->assertHasNoErrors();
});

test('admin can delete a location', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $location = Location::factory()->create();

    actingAs($admin);

    Livewire::test(\App\Livewire\AdminLocations::class)
        ->call('confirmDelete', $location->id)
        ->assertSet('showDeleteModal', true)
        ->assertSet('deletingLocationId', $location->id)
        ->call('deleteLocation');

    $this->assertDatabaseMissing('locations', ['id' => $location->id]);
});

test('cannot delete location in use by plan entries', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $location = Location::factory()->create();

    PlanEntry::factory()->create(['location_id' => $location->id]);

    actingAs($admin);

    Livewire::test(\App\Livewire\AdminLocations::class)
        ->call('confirmDelete', $location->id)
        ->call('deleteLocation');

    $this->assertDatabaseHas('locations', ['id' => $location->id]);
});

test('cannot delete location set as user default', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $location = Location::factory()->create();

    User::factory()->create(['default_location_id' => $location->id]);

    actingAs($admin);

    Livewire::test(\App\Livewire\AdminLocations::class)
        ->call('confirmDelete', $location->id)
        ->call('deleteLocation');

    $this->assertDatabaseHas('locations', ['id' => $location->id]);
});

test('validation requires location name', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    actingAs($admin);

    Livewire::test(\App\Livewire\AdminLocations::class)
        ->call('createLocation')
        ->set('locationName', '')
        ->set('shortLabel', 'TB')
        ->call('save')
        ->assertHasErrors(['locationName' => 'required']);
});

test('validation requires short label', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    actingAs($admin);

    Livewire::test(\App\Livewire\AdminLocations::class)
        ->call('createLocation')
        ->set('locationName', 'Test Building')
        ->set('shortLabel', '')
        ->call('save')
        ->assertHasErrors(['shortLabel' => 'required']);
});

test('admin can cancel editing', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $location = Location::factory()->create();

    actingAs($admin);

    Livewire::test(\App\Livewire\AdminLocations::class)
        ->call('editLocation', $location->id)
        ->assertSet('editingLocationId', $location->id)
        ->call('cancelEdit')
        ->assertSet('editingLocationId', null)
        ->assertSet('locationName', '')
        ->assertSet('shortLabel', '');
});

test('admin can close delete modal', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $location = Location::factory()->create();

    actingAs($admin);

    Livewire::test(\App\Livewire\AdminLocations::class)
        ->call('confirmDelete', $location->id)
        ->assertSet('showDeleteModal', true)
        ->call('closeDeleteModal')
        ->assertSet('showDeleteModal', false)
        ->assertSet('deletingLocationId', null);
});

test('location list shows is_physical status correctly', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $physicalLocation = Location::factory()->create([
        'name' => 'Physical Building',
        'is_physical' => true,
    ]);

    $nonPhysicalLocation = Location::factory()->nonPhysical()->create([
        'name' => 'Other Location',
    ]);

    actingAs($admin);

    $component = Livewire::test(\App\Livewire\AdminLocations::class);

    $locations = $component->viewData('locations');

    expect($locations->firstWhere('name', 'Physical Building')->is_physical)->toBeTrue();
    expect($locations->firstWhere('name', 'Other Location')->is_physical)->toBeFalse();
});

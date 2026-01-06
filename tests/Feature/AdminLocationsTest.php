<?php

use App\Models\Location;
use App\Models\PlanEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

test('non-admin cannot access location management page', function () {
    $user = User::factory()->create(['is_admin' => false]);

    $this->actingAs($user)->get(route('admin.locations'))->assertForbidden();
});

test('admin can view location management page', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $response = $this->actingAs($admin)->get(route('admin.locations'))->assertOk();
    $response->assertOk();
    $response->assertSee('Location Management');
    $response->assertSee('Create New Location');
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
        ->assertSet('editingLocationId', -1);

    $location = Location::where('name', 'New Building')->first();
    expect($location->name)->toBe('New Building');
    expect($location->short_label)->toBe('NB');
    expect($location->slug)->toBe('new-building');
    expect($location->is_physical)->toBeTrue();
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

    $location = Location::where('name', 'Test Building 2')->first();
    expect($location->name)->toBe('Test Building 2');
    expect($location->slug)->toBe('test-building-2');
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

    expect(Location::where('name', 'Existing Building')->count())->toBe(1);
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
        ->assertSet('editingLocationId', -1);

    $location->refresh();

    expect($location->name)->toBe('Updated Name');
    expect($location->short_label)->toBe('UN');
    expect($location->is_physical)->toBeFalse();
});

test('editing location updates its slug if the name changes', function () {
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
    expect($location->slug)->toBe('completely-different-name');
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
        ->assertSet('deletingLocationId', $location->id)
        ->call('deleteLocation');

    expect(Location::where('id', $location->id)->count())->toBe(0);
});

test('replacement location is pre-selected when deleting location in use', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $locationToDelete = Location::factory()->create(['name' => 'Old Building']);
    $replacementLocation = Location::factory()->create(['name' => 'New Building']);

    PlanEntry::factory()->create(['location_id' => $locationToDelete->id]);

    actingAs($admin);

    // Replacement should be pre-selected, so delete works immediately
    Livewire::test(\App\Livewire\AdminLocations::class)
        ->call('confirmDelete', $locationToDelete->id)
        ->assertSet('planEntryCount', 1)
        ->assertSet('userDefaultCount', 0)
        ->assertSet('replacementLocationId', $replacementLocation->id)
        ->call('deleteLocation')
        ->assertHasNoErrors();

    expect(Location::where('id', $locationToDelete->id)->count())->toBe(0);
});

test('can delete location in use by migrating both plan entries and user defaults', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $locationToDelete = Location::factory()->create(['name' => 'Old Building']);
    $replacementLocation = Location::factory()->create(['name' => 'New Building']);

    $planEntry1 = PlanEntry::factory()->create(['location_id' => $locationToDelete->id]);
    $planEntry2 = PlanEntry::factory()->create(['location_id' => $locationToDelete->id]);
    $userWithDefault = User::factory()->create(['default_location_id' => $locationToDelete->id]);

    actingAs($admin);

    Livewire::test(\App\Livewire\AdminLocations::class)
        ->call('confirmDelete', $locationToDelete->id)
        ->assertSet('planEntryCount', 2)
        ->assertSet('userDefaultCount', 1)
        ->set('replacementLocationId', $replacementLocation->id)
        ->call('deleteLocation')
        ->assertHasNoErrors();

    expect(Location::where('id', $locationToDelete->id)->count())->toBe(0);

    $planEntry1->refresh();
    $planEntry2->refresh();
    $userWithDefault->refresh();

    expect($planEntry1->location_id)->toBe($replacementLocation->id);
    expect($planEntry2->location_id)->toBe($replacementLocation->id);
    expect($userWithDefault->default_location_id)->toBe($replacementLocation->id);
});

test('replacement location list excludes the location being deleted', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $locationToDelete = Location::factory()->create(['name' => 'Location A']);
    $otherLocation1 = Location::factory()->create(['name' => 'Location B']);
    $otherLocation2 = Location::factory()->create(['name' => 'Location C']);

    actingAs($admin);

    $component = Livewire::test(\App\Livewire\AdminLocations::class)
        ->call('confirmDelete', $locationToDelete->id);

    $replacementLocations = $component->viewData('replacementLocations');

    expect($replacementLocations)->toHaveCount(2);
    expect($replacementLocations->pluck('id')->toArray())->not->toContain($locationToDelete->id);
    expect($replacementLocations->pluck('id')->toArray())->toContain($otherLocation1->id);
    expect($replacementLocations->pluck('id')->toArray())->toContain($otherLocation2->id);
});

test('required fields must be present', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    actingAs($admin);

    Livewire::test(\App\Livewire\AdminLocations::class)
        ->call('createLocation')
        ->set('locationName', '')
        ->set('shortLabel', '')
        ->call('save')
        ->assertHasErrors(['locationName' => 'required', 'shortLabel' => 'required']);
});

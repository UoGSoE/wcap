<?php

use App\Models\Location;
use App\Models\PlanEntry;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

test('manager can view occupancy report page', function () {
    $manager = User::factory()->create();
    Team::factory()->create(['manager_id' => $manager->id]);

    actingAs($manager);

    Livewire::test(\App\Livewire\OccupancyReport::class)
        ->assertOk()
        ->assertSee('Office Occupancy Report')
        ->assertSee('Today')
        ->assertSee('This Period')
        ->assertSee('Summary');
});

test('non-manager cannot access occupancy report page', function () {
    $user = User::factory()->create();

    actingAs($user);

    $this->get(route('manager.occupancy'))
        ->assertForbidden();
});

test('page shows 10 weekdays', function () {
    $manager = User::factory()->create();
    Team::factory()->create(['manager_id' => $manager->id]);

    actingAs($manager);

    $component = Livewire::test(\App\Livewire\OccupancyReport::class);

    expect(count($component->viewData('days')))->toBe(10);
});

test('home occupants are users with matching default_location_id who are ONSITE', function () {
    $manager = User::factory()->create();
    Team::factory()->create(['manager_id' => $manager->id]);

    $location = Location::factory()->create(['name' => 'JWS', 'is_physical' => true]);

    $homeUser = User::factory()->create([
        'default_location_id' => $location->id,
        'surname' => 'Home',
    ]);

    $monday = now()->startOfWeek();

    PlanEntry::factory()->onsite()->create([
        'user_id' => $homeUser->id,
        'entry_date' => $monday,
        'location_id' => $location->id,
    ]);

    actingAs($manager);

    Date::setTestNow($monday->copy()->setTime(10, 0));

    $component = Livewire::test(\App\Livewire\OccupancyReport::class);
    $daySnapshot = $component->viewData('daySnapshot');

    $jwsData = collect($daySnapshot)->firstWhere('location_name', 'JWS');

    expect($jwsData['home_count'])->toBe(1);
    expect($jwsData['visitor_count'])->toBe(0);
    expect($jwsData['total_present'])->toBe(1);

    Date::setTestNow();
});

test('visitors are users with different default_location_id who are ONSITE at location', function () {
    $manager = User::factory()->create();
    Team::factory()->create(['manager_id' => $manager->id]);

    $locationJws = Location::factory()->create(['name' => 'JWS', 'is_physical' => true]);
    $locationRankine = Location::factory()->create(['name' => 'Rankine', 'is_physical' => true]);

    $visitorUser = User::factory()->create([
        'default_location_id' => $locationRankine->id,
        'surname' => 'Visitor',
    ]);

    $monday = now()->startOfWeek();

    PlanEntry::factory()->onsite()->create([
        'user_id' => $visitorUser->id,
        'entry_date' => $monday,
        'location_id' => $locationJws->id,
    ]);

    actingAs($manager);

    Date::setTestNow($monday->copy()->setTime(10, 0));

    $component = Livewire::test(\App\Livewire\OccupancyReport::class);
    $daySnapshot = $component->viewData('daySnapshot');

    $jwsData = collect($daySnapshot)->firstWhere('location_name', 'JWS');

    expect($jwsData['home_count'])->toBe(0);
    expect($jwsData['visitor_count'])->toBe(1);
    expect($jwsData['total_present'])->toBe(1);

    Date::setTestNow();
});

test('REMOTE entries do not count as physically present', function () {
    $manager = User::factory()->create();
    Team::factory()->create(['manager_id' => $manager->id]);

    $location = Location::factory()->create(['name' => 'JWS', 'is_physical' => true]);

    $remoteUser = User::factory()->create([
        'default_location_id' => $location->id,
        'surname' => 'Remote',
    ]);

    $monday = now()->startOfWeek();

    PlanEntry::factory()->remote()->create([
        'user_id' => $remoteUser->id,
        'entry_date' => $monday,
        'location_id' => $location->id,
    ]);

    actingAs($manager);

    Date::setTestNow($monday->copy()->setTime(10, 0));

    $component = Livewire::test(\App\Livewire\OccupancyReport::class);
    $daySnapshot = $component->viewData('daySnapshot');

    $jwsData = collect($daySnapshot)->firstWhere('location_name', 'JWS');

    expect($jwsData['home_count'])->toBe(0);
    expect($jwsData['visitor_count'])->toBe(0);
    expect($jwsData['total_present'])->toBe(0);

    Date::setTestNow();
});

test('NOT_AVAILABLE entries do not count as present', function () {
    $manager = User::factory()->create();
    Team::factory()->create(['manager_id' => $manager->id]);

    $location = Location::factory()->create(['name' => 'JWS', 'is_physical' => true]);

    $unavailableUser = User::factory()->create([
        'default_location_id' => $location->id,
        'surname' => 'Unavailable',
    ]);

    $monday = now()->startOfWeek();

    PlanEntry::factory()->unavailable()->create([
        'user_id' => $unavailableUser->id,
        'entry_date' => $monday,
        'location_id' => null,
    ]);

    actingAs($manager);

    Date::setTestNow($monday->copy()->setTime(10, 0));

    $component = Livewire::test(\App\Livewire\OccupancyReport::class);
    $daySnapshot = $component->viewData('daySnapshot');

    $jwsData = collect($daySnapshot)->firstWhere('location_name', 'JWS');

    expect($jwsData['total_present'])->toBe(0);

    Date::setTestNow();
});

test('base capacity reflects users assigned to location', function () {
    $manager = User::factory()->create();
    Team::factory()->create(['manager_id' => $manager->id]);

    $location = Location::factory()->create(['name' => 'JWS', 'is_physical' => true]);

    User::factory()->count(3)->create(['default_location_id' => $location->id]);

    actingAs($manager);

    $component = Livewire::test(\App\Livewire\OccupancyReport::class);
    $daySnapshot = $component->viewData('daySnapshot');

    $jwsData = collect($daySnapshot)->firstWhere('location_name', 'JWS');

    expect($jwsData['base_capacity'])->toBe(3);
});

test('only physical locations appear in report', function () {
    $manager = User::factory()->create();
    Team::factory()->create(['manager_id' => $manager->id]);

    $physicalLocation = Location::factory()->create(['name' => 'Office A', 'is_physical' => true]);
    $nonPhysicalLocation = Location::factory()->nonPhysical()->create(['name' => 'Remote']);

    actingAs($manager);

    $component = Livewire::test(\App\Livewire\OccupancyReport::class);
    $daySnapshot = $component->viewData('daySnapshot');

    $locationNames = collect($daySnapshot)->pluck('location_name')->toArray();

    expect($locationNames)->toContain('Office A');
    expect($locationNames)->not->toContain('Remote');
});

test('period matrix shows correct occupancy over multiple days', function () {
    $manager = User::factory()->create();
    Team::factory()->create(['manager_id' => $manager->id]);

    $location = Location::factory()->create(['name' => 'JWS', 'is_physical' => true]);

    $user1 = User::factory()->create(['default_location_id' => $location->id]);
    $user2 = User::factory()->create(['default_location_id' => $location->id]);

    $monday = now()->startOfWeek();
    $tuesday = $monday->copy()->addDay();

    PlanEntry::factory()->onsite()->create([
        'user_id' => $user1->id,
        'entry_date' => $monday,
        'location_id' => $location->id,
    ]);

    PlanEntry::factory()->onsite()->create([
        'user_id' => $user1->id,
        'entry_date' => $tuesday,
        'location_id' => $location->id,
    ]);
    PlanEntry::factory()->onsite()->create([
        'user_id' => $user2->id,
        'entry_date' => $tuesday,
        'location_id' => $location->id,
    ]);

    actingAs($manager);

    $component = Livewire::test(\App\Livewire\OccupancyReport::class);
    $periodMatrix = $component->viewData('periodMatrix');

    $jwsRow = collect($periodMatrix)->firstWhere('location_name', 'JWS');

    expect($jwsRow['base_capacity'])->toBe(2);
    expect($jwsRow['days'][0]['home_count'])->toBe(1);
    expect($jwsRow['days'][1]['home_count'])->toBe(2);
});

test('summary stats calculate mean correctly', function () {
    $manager = User::factory()->create();
    Team::factory()->create(['manager_id' => $manager->id]);

    $location = Location::factory()->create(['name' => 'JWS', 'is_physical' => true]);

    $user = User::factory()->create(['default_location_id' => $location->id]);

    $monday = now()->startOfWeek();

    for ($i = 0; $i < 5; $i++) {
        PlanEntry::factory()->onsite()->create([
            'user_id' => $user->id,
            'entry_date' => $monday->copy()->addDays($i),
            'location_id' => $location->id,
        ]);
    }

    actingAs($manager);

    $component = Livewire::test(\App\Livewire\OccupancyReport::class);
    $summaryStats = $component->viewData('summaryStats');

    $jwsStats = collect($summaryStats)->firstWhere('location_name', 'JWS');

    expect($jwsStats['mean_occupancy'])->toBe(0.5);
});

test('summary stats identify peak occupancy and date', function () {
    $manager = User::factory()->create();
    Team::factory()->create(['manager_id' => $manager->id]);

    $location = Location::factory()->create(['name' => 'JWS', 'is_physical' => true]);

    $user1 = User::factory()->create(['default_location_id' => $location->id]);
    $user2 = User::factory()->create(['default_location_id' => $location->id]);
    $user3 = User::factory()->create(['default_location_id' => $location->id]);

    $monday = now()->startOfWeek();
    $wednesday = $monday->copy()->addDays(2);

    PlanEntry::factory()->onsite()->create([
        'user_id' => $user1->id,
        'entry_date' => $monday,
        'location_id' => $location->id,
    ]);

    PlanEntry::factory()->onsite()->create([
        'user_id' => $user1->id,
        'entry_date' => $wednesday,
        'location_id' => $location->id,
    ]);
    PlanEntry::factory()->onsite()->create([
        'user_id' => $user2->id,
        'entry_date' => $wednesday,
        'location_id' => $location->id,
    ]);
    PlanEntry::factory()->onsite()->create([
        'user_id' => $user3->id,
        'entry_date' => $wednesday,
        'location_id' => $location->id,
    ]);

    actingAs($manager);

    $component = Livewire::test(\App\Livewire\OccupancyReport::class);
    $summaryStats = $component->viewData('summaryStats');

    $jwsStats = collect($summaryStats)->firstWhere('location_name', 'JWS');

    expect($jwsStats['peak_occupancy'])->toBe(3);
    expect($jwsStats['peak_date']->toDateString())->toBe($wednesday->toDateString());
});

test('location with no assigned users shows zero capacity', function () {
    $manager = User::factory()->create();
    Team::factory()->create(['manager_id' => $manager->id]);

    $location = Location::factory()->create(['name' => 'Empty Office', 'is_physical' => true]);

    actingAs($manager);

    $component = Livewire::test(\App\Livewire\OccupancyReport::class);
    $daySnapshot = $component->viewData('daySnapshot');

    $emptyData = collect($daySnapshot)->firstWhere('location_name', 'Empty Office');

    expect($emptyData['base_capacity'])->toBe(0);
});

test('weekend viewing shows Monday data', function () {
    $manager = User::factory()->create();
    Team::factory()->create(['manager_id' => $manager->id]);

    $location = Location::factory()->create(['name' => 'JWS', 'is_physical' => true]);
    $user = User::factory()->create(['default_location_id' => $location->id]);

    $monday = now()->startOfWeek();
    $saturday = $monday->copy()->addDays(5);

    PlanEntry::factory()->onsite()->create([
        'user_id' => $user->id,
        'entry_date' => $monday,
        'location_id' => $location->id,
    ]);

    actingAs($manager);

    Date::setTestNow($saturday->copy()->setTime(10, 0));

    $component = Livewire::test(\App\Livewire\OccupancyReport::class);
    $snapshotDate = $component->viewData('snapshotDate');

    expect($snapshotDate->isMonday())->toBeTrue();

    Date::setTestNow();
});

test('user working at non-default location counts as visitor there and absent from home', function () {
    $manager = User::factory()->create();
    Team::factory()->create(['manager_id' => $manager->id]);

    $homeLocation = Location::factory()->create(['name' => 'JWS', 'is_physical' => true]);
    $visitLocation = Location::factory()->create(['name' => 'Rankine', 'is_physical' => true]);

    $user = User::factory()->create([
        'default_location_id' => $homeLocation->id,
        'surname' => 'Traveller',
    ]);

    $monday = now()->startOfWeek();

    PlanEntry::factory()->onsite()->create([
        'user_id' => $user->id,
        'entry_date' => $monday,
        'location_id' => $visitLocation->id,
    ]);

    actingAs($manager);

    Date::setTestNow($monday->copy()->setTime(10, 0));

    $component = Livewire::test(\App\Livewire\OccupancyReport::class);
    $daySnapshot = $component->viewData('daySnapshot');

    $homeData = collect($daySnapshot)->firstWhere('location_name', 'JWS');
    $visitData = collect($daySnapshot)->firstWhere('location_name', 'Rankine');

    expect($homeData['home_count'])->toBe(0);
    expect($homeData['base_capacity'])->toBe(1);

    expect($visitData['visitor_count'])->toBe(1);
    expect($visitData['home_count'])->toBe(0);

    Date::setTestNow();
});

test('tab switching works correctly', function () {
    $manager = User::factory()->create();
    Team::factory()->create(['manager_id' => $manager->id]);

    Location::factory()->create(['name' => 'JWS', 'is_physical' => true]);

    actingAs($manager);

    Livewire::test(\App\Livewire\OccupancyReport::class)
        ->assertSet('tab', 'today')
        ->assertSee('Base capacity')
        ->set('tab', 'period')
        ->assertSet('tab', 'period')
        ->assertSee('Two-week occupancy overview')
        ->set('tab', 'summary')
        ->assertSet('tab', 'summary')
        ->assertSee('Occupancy statistics');
});

test('utilization percentage is calculated correctly', function () {
    $manager = User::factory()->create();
    Team::factory()->create(['manager_id' => $manager->id]);

    $location = Location::factory()->create(['name' => 'JWS', 'is_physical' => true]);

    User::factory()->count(4)->create(['default_location_id' => $location->id]);
    $presentUser = User::factory()->create(['default_location_id' => $location->id]);

    $monday = now()->startOfWeek();

    PlanEntry::factory()->onsite()->create([
        'user_id' => $presentUser->id,
        'entry_date' => $monday,
        'location_id' => $location->id,
    ]);

    actingAs($manager);

    Date::setTestNow($monday->copy()->setTime(10, 0));

    $component = Livewire::test(\App\Livewire\OccupancyReport::class);
    $daySnapshot = $component->viewData('daySnapshot');

    $jwsData = collect($daySnapshot)->firstWhere('location_name', 'JWS');

    expect($jwsData['base_capacity'])->toBe(5);
    expect($jwsData['home_count'])->toBe(1);
    expect($jwsData['utilization_pct'])->toBe(20.0);

    Date::setTestNow();
});

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
        ->assertSee('Date')
        ->assertSee('Heatmap')
        ->assertSee('Stats')
        ->assertSee('Trends');
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

    // Mean is 5 entries over 10 days = 0.5, rounded up to 1 (partial people need desk space)
    expect($jwsStats['mean_occupancy'])->toBe(1);
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
        ->assertSee('Shows total occupancy for each location')
        ->set('tab', 'summary')
        ->assertSet('tab', 'summary')
        ->assertSee('Summary statistics across the selected period');
});

test('utilization percentage is based on total present including visitors', function () {
    $manager = User::factory()->create();
    Team::factory()->create(['manager_id' => $manager->id]);

    $location = Location::factory()->create(['name' => 'JWS', 'is_physical' => true]);
    $otherLocation = Location::factory()->create(['name' => 'Rankine', 'is_physical' => true]);

    // 4 users assigned to JWS (base capacity = 4)
    $homeUsers = User::factory()->count(4)->create(['default_location_id' => $location->id]);

    // 1 visitor from another location
    $visitor = User::factory()->create(['default_location_id' => $otherLocation->id]);

    $monday = now()->startOfWeek();

    // 2 home users present
    PlanEntry::factory()->onsite()->create([
        'user_id' => $homeUsers[0]->id,
        'entry_date' => $monday,
        'location_id' => $location->id,
    ]);
    PlanEntry::factory()->onsite()->create([
        'user_id' => $homeUsers[1]->id,
        'entry_date' => $monday,
        'location_id' => $location->id,
    ]);

    // 1 visitor present
    PlanEntry::factory()->onsite()->create([
        'user_id' => $visitor->id,
        'entry_date' => $monday,
        'location_id' => $location->id,
    ]);

    actingAs($manager);

    Date::setTestNow($monday->copy()->setTime(10, 0));

    $component = Livewire::test(\App\Livewire\OccupancyReport::class);
    $daySnapshot = $component->viewData('daySnapshot');

    $jwsData = collect($daySnapshot)->firstWhere('location_name', 'JWS');

    expect($jwsData['base_capacity'])->toBe(4);
    expect($jwsData['home_count'])->toBe(2);
    expect($jwsData['visitor_count'])->toBe(1);
    expect($jwsData['total_present'])->toBe(3);
    // Utilization = total_present / base_capacity = 3/4 = 75%
    expect($jwsData['utilization_pct'])->toBe(75.0);

    Date::setTestNow();
});

test('utilization can exceed 100 percent when visitors push occupancy above capacity', function () {
    $manager = User::factory()->create();
    Team::factory()->create(['manager_id' => $manager->id]);

    $location = Location::factory()->create(['name' => 'JWS', 'is_physical' => true]);
    $otherLocation = Location::factory()->create(['name' => 'Rankine', 'is_physical' => true]);

    // 2 users assigned to JWS (base capacity = 2)
    $homeUsers = User::factory()->count(2)->create(['default_location_id' => $location->id]);

    // 2 visitors from another location
    $visitors = User::factory()->count(2)->create(['default_location_id' => $otherLocation->id]);

    $monday = now()->startOfWeek();

    // Both home users present
    foreach ($homeUsers as $user) {
        PlanEntry::factory()->onsite()->create([
            'user_id' => $user->id,
            'entry_date' => $monday,
            'location_id' => $location->id,
        ]);
    }

    // Both visitors present
    foreach ($visitors as $visitor) {
        PlanEntry::factory()->onsite()->create([
            'user_id' => $visitor->id,
            'entry_date' => $monday,
            'location_id' => $location->id,
        ]);
    }

    actingAs($manager);

    Date::setTestNow($monday->copy()->setTime(10, 0));

    $component = Livewire::test(\App\Livewire\OccupancyReport::class);
    $daySnapshot = $component->viewData('daySnapshot');

    $jwsData = collect($daySnapshot)->firstWhere('location_name', 'JWS');

    expect($jwsData['base_capacity'])->toBe(2);
    expect($jwsData['total_present'])->toBe(4);
    // Utilization = 4/2 = 200%
    expect($jwsData['utilization_pct'])->toBe(200.0);

    Date::setTestNow();
});

test('heatmap shows daily aggregation for ranges under 25 weekdays', function () {
    $manager = User::factory()->create();
    Team::factory()->create(['manager_id' => $manager->id]);

    Location::factory()->create(['name' => 'JWS', 'is_physical' => true]);

    actingAs($manager);

    // Default 2-week range has 10 weekdays - should be daily
    $component = Livewire::test(\App\Livewire\OccupancyReport::class);

    expect($component->viewData('aggregation'))->toBe('daily');
    expect(count($component->viewData('days')))->toBe(10);
});

test('heatmap switches to weekly aggregation for ranges of 25+ weekdays', function () {
    $manager = User::factory()->create();
    Team::factory()->create(['manager_id' => $manager->id]);

    Location::factory()->create(['name' => 'JWS', 'is_physical' => true]);

    actingAs($manager);

    // Set a 6-week range (30 weekdays) - should trigger weekly aggregation
    $start = now()->startOfWeek();
    $end = $start->copy()->addWeeks(6)->subDay();

    $component = Livewire::test(\App\Livewire\OccupancyReport::class)
        ->set('range', [
            'start' => $start->toDateString(),
            'end' => $end->toDateString(),
        ]);

    expect($component->viewData('aggregation'))->toBe('weekly');
    // 6 weeks = 6 columns (one per week)
    expect(count($component->viewData('days')))->toBeLessThanOrEqual(7);
});

test('weekly aggregation calculates averages correctly', function () {
    $manager = User::factory()->create();
    Team::factory()->create(['manager_id' => $manager->id]);

    $location = Location::factory()->create(['name' => 'JWS', 'is_physical' => true]);
    $user = User::factory()->create(['default_location_id' => $location->id]);

    $monday = now()->startOfWeek();

    // Create entries for Mon, Tue, Wed (3 out of 5 weekdays) with 1 person each day
    for ($i = 0; $i < 3; $i++) {
        PlanEntry::factory()->onsite()->create([
            'user_id' => $user->id,
            'entry_date' => $monday->copy()->addDays($i),
            'location_id' => $location->id,
        ]);
    }

    actingAs($manager);

    // Set a 6-week range to trigger weekly aggregation
    $start = $monday;
    $end = $monday->copy()->addWeeks(6)->subDay();

    $component = Livewire::test(\App\Livewire\OccupancyReport::class)
        ->set('range', [
            'start' => $start->toDateString(),
            'end' => $end->toDateString(),
        ]);

    $periodMatrix = $component->viewData('periodMatrix');
    $jwsRow = collect($periodMatrix)->firstWhere('location_name', 'JWS');

    // First week: 3 entries over 5 weekdays = 0.6 average, ceil to 1
    expect($jwsRow['days'][0]['total_present'])->toBe(1);
});

test('export current view downloads excel file', function () {
    $manager = User::factory()->create();
    Team::factory()->create(['manager_id' => $manager->id]);

    Location::factory()->create(['name' => 'JWS', 'is_physical' => true]);

    actingAs($manager);

    Livewire::test(\App\Livewire\OccupancyReport::class)
        ->call('exportCurrent')
        ->assertFileDownloaded();
});

test('export detailed downloads excel file', function () {
    $manager = User::factory()->create();
    Team::factory()->create(['manager_id' => $manager->id]);

    Location::factory()->create(['name' => 'JWS', 'is_physical' => true]);

    actingAs($manager);

    Livewire::test(\App\Livewire\OccupancyReport::class)
        ->call('exportDetailed')
        ->assertFileDownloaded();
});

test('export detailed skips weekly aggregation for large ranges', function () {
    $manager = User::factory()->create();
    Team::factory()->create(['manager_id' => $manager->id]);

    $location = Location::factory()->create(['name' => 'JWS', 'is_physical' => true]);
    $user = User::factory()->create(['default_location_id' => $location->id]);

    $monday = now()->startOfWeek();

    // Create entries for each weekday in 6 weeks (30 weekdays)
    for ($week = 0; $week < 6; $week++) {
        for ($day = 0; $day < 5; $day++) {
            PlanEntry::factory()->onsite()->create([
                'user_id' => $user->id,
                'entry_date' => $monday->copy()->addWeeks($week)->addDays($day),
                'location_id' => $location->id,
            ]);
        }
    }

    actingAs($manager);

    // Set a 6-week range that would normally trigger weekly aggregation
    $start = $monday;
    $end = $monday->copy()->addWeeks(6)->subDay();

    $component = Livewire::test(\App\Livewire\OccupancyReport::class)
        ->set('range', [
            'start' => $start->toDateString(),
            'end' => $end->toDateString(),
        ]);

    // Current view should be weekly aggregated
    expect($component->viewData('aggregation'))->toBe('weekly');

    // But detailed export should skip aggregation - filename should NOT contain '-weekly'
    $result = $component->call('exportDetailed');
    $downloadedFilename = data_get($result->effects, 'download.name');

    expect($downloadedFilename)->not->toContain('-weekly');
});

test('export current view respects aggregation for large ranges', function () {
    $manager = User::factory()->create();
    Team::factory()->create(['manager_id' => $manager->id]);

    Location::factory()->create(['name' => 'JWS', 'is_physical' => true]);

    actingAs($manager);

    // Set a 6-week range to trigger weekly aggregation
    $start = now()->startOfWeek();
    $end = $start->copy()->addWeeks(6)->subDay();

    $result = Livewire::test(\App\Livewire\OccupancyReport::class)
        ->set('range', [
            'start' => $start->toDateString(),
            'end' => $end->toDateString(),
        ])
        ->call('exportCurrent');

    $downloadedFilename = data_get($result->effects, 'download.name');

    expect($downloadedFilename)->toContain('-weekly');
});

test('trends tab shows chart with utilization percentages', function () {
    $manager = User::factory()->create();
    Team::factory()->create(['manager_id' => $manager->id]);

    $location = Location::factory()->create(['name' => 'JWS', 'is_physical' => true]);
    $user = User::factory()->create(['default_location_id' => $location->id]);

    $monday = now()->startOfWeek();

    PlanEntry::factory()->onsite()->create([
        'user_id' => $user->id,
        'entry_date' => $monday,
        'location_id' => $location->id,
    ]);

    actingAs($manager);

    $component = Livewire::test(\App\Livewire\OccupancyReport::class)
        ->set('tab', 'trends');

    $component->assertSee('Shows utilization trends over the selected period')
        ->assertSee('Locations');

    $chartData = $component->viewData('chartData');

    expect($chartData)->toBeArray();
    expect($chartData[0])->toHaveKey('date');
    expect($chartData[0])->toHaveKey('JWS');
    // Value should be a decimal (1.0 = 100%) since 1 user at location with capacity 1
    expect($chartData[0]['JWS'])->toBe(1.0);
});

test('trends tab defaults to all locations selected', function () {
    $manager = User::factory()->create();
    Team::factory()->create(['manager_id' => $manager->id]);

    Location::factory()->create(['name' => 'JWS', 'is_physical' => true]);
    Location::factory()->create(['name' => 'Rankine', 'is_physical' => true]);

    actingAs($manager);

    $component = Livewire::test(\App\Livewire\OccupancyReport::class);

    $selectedLocations = $component->viewData('selectedLocations');

    expect(count($selectedLocations))->toBe(2);
});

test('trends chart data reflects selected locations only', function () {
    $manager = User::factory()->create();
    Team::factory()->create(['manager_id' => $manager->id]);

    $jws = Location::factory()->create(['name' => 'JWS', 'is_physical' => true]);
    $rankine = Location::factory()->create(['name' => 'Rankine', 'is_physical' => true]);

    actingAs($manager);

    // Select only JWS
    $component = Livewire::test(\App\Livewire\OccupancyReport::class)
        ->set('selectedLocations', [(string) $jws->id])
        ->set('tab', 'trends');

    $selectedLocations = $component->viewData('selectedLocations');

    expect($selectedLocations)->toContain((string) $jws->id);
    expect($selectedLocations)->not->toContain((string) $rankine->id);
});

test('trends chart shows message when no locations selected', function () {
    $manager = User::factory()->create();
    Team::factory()->create(['manager_id' => $manager->id]);

    Location::factory()->create(['name' => 'JWS', 'is_physical' => true]);

    actingAs($manager);

    Livewire::test(\App\Livewire\OccupancyReport::class)
        ->set('selectedLocations', [])
        ->set('tab', 'trends')
        ->assertSee('No locations selected')
        ->assertSee('Select at least one location to view the trend chart');
});

test('chart colors are assigned to each location', function () {
    $manager = User::factory()->create();
    Team::factory()->create(['manager_id' => $manager->id]);

    Location::factory()->create(['name' => 'JWS', 'is_physical' => true]);
    Location::factory()->create(['name' => 'Rankine', 'is_physical' => true]);

    actingAs($manager);

    $component = Livewire::test(\App\Livewire\OccupancyReport::class);

    $chartColors = $component->viewData('chartColors');

    expect($chartColors)->toBeArray();
    expect(count($chartColors))->toBe(2);

    foreach ($chartColors as $locationId => $colorData) {
        expect($colorData)->toHaveKey('name');
        expect($colorData)->toHaveKey('color');
    }
});

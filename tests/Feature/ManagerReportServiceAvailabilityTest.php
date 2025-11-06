<?php

use App\Enums\Location;
use App\Models\PlanEntry;
use App\Models\Service;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

test('service availability tab is visible', function () {
    $manager = User::factory()->create();
    $team = Team::factory()->create(['manager_id' => $manager->id]);

    actingAs($manager);

    Livewire::test(\App\Livewire\ManagerReport::class)
        ->assertOk()
        ->assertSee('Service Availability');
});

test('service availability tab displays all services', function () {
    $manager = User::factory()->create();
    $team = Team::factory()->create(['manager_id' => $manager->id]);

    $service1 = Service::factory()->create(['name' => 'Active Directory']);
    $service2 = Service::factory()->create(['name' => 'Email Service']);
    $service3 = Service::factory()->create(['name' => 'Backup Service']);

    actingAs($manager);

    $component = Livewire::test(\App\Livewire\ManagerReport::class);

    $serviceAvailabilityMatrix = $component->viewData('serviceAvailabilityMatrix');

    expect($serviceAvailabilityMatrix)->toHaveCount(3);
    expect($serviceAvailabilityMatrix[0]['label'])->toBe('Active Directory');
    expect($serviceAvailabilityMatrix[1]['label'])->toBe('Backup Service');
    expect($serviceAvailabilityMatrix[2]['label'])->toBe('Email Service');
});

test('only counts entries where is_available is true', function () {
    $manager = User::factory()->create();
    $team = Team::factory()->create(['manager_id' => $manager->id]);

    $service = Service::factory()->create(['name' => 'Test Service']);

    $availableMember = User::factory()->create();
    $unavailableMember = User::factory()->create();
    $service->users()->attach([$availableMember->id, $unavailableMember->id]);

    $monday = now()->startOfWeek();

    PlanEntry::factory()->create([
        'user_id' => $availableMember->id,
        'entry_date' => $monday,
        'location' => Location::OTHER,
        'is_available' => true,
    ]);

    PlanEntry::factory()->create([
        'user_id' => $unavailableMember->id,
        'entry_date' => $monday,
        'location' => null,
        'is_available' => false,
    ]);

    actingAs($manager);

    $component = Livewire::test(\App\Livewire\ManagerReport::class);
    $serviceAvailabilityMatrix = $component->viewData('serviceAvailabilityMatrix');

    $testServiceRow = collect($serviceAvailabilityMatrix)->firstWhere('label', 'Test Service');

    expect($testServiceRow['entries'][0]['count'])->toBe(1);
});

test('shows zero when no one is available', function () {
    $manager = User::factory()->create();
    $team = Team::factory()->create(['manager_id' => $manager->id]);

    $service = Service::factory()->create(['name' => 'Empty Service']);

    $unavailableMember = User::factory()->create();
    $service->users()->attach($unavailableMember->id);

    $monday = now()->startOfWeek();

    PlanEntry::factory()->create([
        'user_id' => $unavailableMember->id,
        'entry_date' => $monday,
        'location' => null,
        'is_available' => false,
    ]);

    actingAs($manager);

    $component = Livewire::test(\App\Livewire\ManagerReport::class);
    $serviceAvailabilityMatrix = $component->viewData('serviceAvailabilityMatrix');

    $emptyServiceRow = collect($serviceAvailabilityMatrix)->firstWhere('label', 'Empty Service');

    expect($emptyServiceRow['entries'][0]['count'])->toBe(0);
});

test('shows correct counts with multiple available people', function () {
    $manager = User::factory()->create();
    $team = Team::factory()->create(['manager_id' => $manager->id]);

    $service = Service::factory()->create(['name' => 'Popular Service']);

    $member1 = User::factory()->create();
    $member2 = User::factory()->create();
    $member3 = User::factory()->create();
    $service->users()->attach([$member1->id, $member2->id, $member3->id]);

    $monday = now()->startOfWeek();
    $tuesday = $monday->copy()->addDay();

    PlanEntry::factory()->create([
        'user_id' => $member1->id,
        'entry_date' => $monday,
        'location' => Location::JWS,
        'is_available' => true,
    ]);

    PlanEntry::factory()->create([
        'user_id' => $member2->id,
        'entry_date' => $monday,
        'location' => Location::OTHER,
        'is_available' => true,
    ]);

    PlanEntry::factory()->create([
        'user_id' => $member3->id,
        'entry_date' => $monday,
        'location' => Location::JWN,
        'is_available' => true,
    ]);

    PlanEntry::factory()->create([
        'user_id' => $member1->id,
        'entry_date' => $tuesday,
        'location' => Location::JWS,
        'is_available' => true,
    ]);

    PlanEntry::factory()->create([
        'user_id' => $member2->id,
        'entry_date' => $tuesday,
        'location' => null,
        'is_available' => false,
    ]);

    actingAs($manager);

    $component = Livewire::test(\App\Livewire\ManagerReport::class);
    $serviceAvailabilityMatrix = $component->viewData('serviceAvailabilityMatrix');

    $popularServiceRow = collect($serviceAvailabilityMatrix)->firstWhere('label', 'Popular Service');

    expect($popularServiceRow['entries'][0]['count'])->toBe(3);
    expect($popularServiceRow['entries'][1]['count'])->toBe(1);
});

test('services always show all members regardless of admin toggle', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $team = Team::factory()->create(['manager_id' => $admin->id]);

    $service = Service::factory()->create(['name' => 'Test Service']);

    $teamMember = User::factory()->create(['surname' => 'TeamMember', 'forenames' => 'John']);
    $nonTeamMember = User::factory()->create(['surname' => 'NonTeamMember', 'forenames' => 'Jane']);

    $team->users()->attach($teamMember->id);
    $service->users()->attach([$teamMember->id, $nonTeamMember->id]);

    $monday = now()->startOfWeek();

    PlanEntry::factory()->create([
        'user_id' => $teamMember->id,
        'entry_date' => $monday,
        'location' => Location::JWS,
        'is_available' => true,
    ]);

    PlanEntry::factory()->create([
        'user_id' => $nonTeamMember->id,
        'entry_date' => $monday,
        'location' => Location::OTHER,
        'is_available' => true,
    ]);

    actingAs($admin);

    $componentWithToggleOn = Livewire::test(\App\Livewire\ManagerReport::class)
        ->set('showAllUsers', true);

    $serviceMatrixToggleOn = $componentWithToggleOn->viewData('serviceAvailabilityMatrix');
    $testServiceRowToggleOn = collect($serviceMatrixToggleOn)->firstWhere('label', 'Test Service');

    expect($testServiceRowToggleOn['entries'][0]['count'])->toBe(2);

    $componentWithToggleOff = Livewire::test(\App\Livewire\ManagerReport::class)
        ->set('showAllUsers', false);

    $serviceMatrixToggleOff = $componentWithToggleOff->viewData('serviceAvailabilityMatrix');
    $testServiceRowToggleOff = collect($serviceMatrixToggleOff)->firstWhere('label', 'Test Service');

    expect($testServiceRowToggleOff['entries'][0]['count'])->toBe(2);
});

test('service with no members shows zero availability', function () {
    $manager = User::factory()->create();
    $team = Team::factory()->create(['manager_id' => $manager->id]);

    $service = Service::factory()->create(['name' => 'Unmanned Service']);

    actingAs($manager);

    $component = Livewire::test(\App\Livewire\ManagerReport::class);
    $serviceAvailabilityMatrix = $component->viewData('serviceAvailabilityMatrix');

    $unmannedServiceRow = collect($serviceAvailabilityMatrix)->firstWhere('label', 'Unmanned Service');

    foreach ($unmannedServiceRow['entries'] as $entry) {
        expect($entry['count'])->toBe(0);
    }
});

test('service availability counts across all 10 weekdays', function () {
    $manager = User::factory()->create();
    $team = Team::factory()->create(['manager_id' => $manager->id]);

    $service = Service::factory()->create(['name' => 'Test Service']);
    $member = User::factory()->create();
    $service->users()->attach($member->id);

    $startOfWeek = now()->startOfWeek();

    for ($offset = 0; $offset < 20; $offset++) {
        $day = $startOfWeek->copy()->addDays($offset);

        if ($day->isWeekday()) {
            PlanEntry::factory()->create([
                'user_id' => $member->id,
                'entry_date' => $day,
                'location' => Location::JWS,
                'is_available' => true,
            ]);
        }
    }

    actingAs($manager);

    $component = Livewire::test(\App\Livewire\ManagerReport::class);
    $serviceAvailabilityMatrix = $component->viewData('serviceAvailabilityMatrix');

    $testServiceRow = collect($serviceAvailabilityMatrix)->firstWhere('label', 'Test Service');

    expect($testServiceRow['entries'])->toHaveCount(10);

    $availableDays = collect($testServiceRow['entries'])->filter(fn ($entry) => $entry['count'] > 0)->count();
    expect($availableDays)->toBeGreaterThanOrEqual(9);
});

test('manager only coverage shows manager_only flag', function () {
    $manager = User::factory()->create();
    $team = Team::factory()->create(['manager_id' => $manager->id]);

    $serviceManager = User::factory()->create();
    $service = Service::factory()->create([
        'name' => 'Test Service',
        'manager_id' => $serviceManager->id,
    ]);

    $monday = now()->startOfWeek();

    PlanEntry::factory()->create([
        'user_id' => $serviceManager->id,
        'entry_date' => $monday,
        'location' => Location::JWS,
        'is_available' => true,
    ]);

    actingAs($manager);

    $component = Livewire::test(\App\Livewire\ManagerReport::class);
    $serviceAvailabilityMatrix = $component->viewData('serviceAvailabilityMatrix');

    $testServiceRow = collect($serviceAvailabilityMatrix)->firstWhere('label', 'Test Service');

    expect($testServiceRow['entries'][0]['count'])->toBe(0);
    expect($testServiceRow['entries'][0]['manager_only'])->toBe(true);

    $component->assertSee('Manager');
});

test('manager available but members also available shows count not manager_only', function () {
    $manager = User::factory()->create();
    $team = Team::factory()->create(['manager_id' => $manager->id]);

    $serviceManager = User::factory()->create();
    $serviceMember = User::factory()->create();

    $service = Service::factory()->create([
        'name' => 'Test Service',
        'manager_id' => $serviceManager->id,
    ]);

    $service->users()->attach($serviceMember->id);

    $monday = now()->startOfWeek();

    PlanEntry::factory()->create([
        'user_id' => $serviceManager->id,
        'entry_date' => $monday,
        'location' => Location::JWS,
        'is_available' => true,
    ]);

    PlanEntry::factory()->create([
        'user_id' => $serviceMember->id,
        'entry_date' => $monday,
        'location' => Location::OTHER,
        'is_available' => true,
    ]);

    actingAs($manager);

    $component = Livewire::test(\App\Livewire\ManagerReport::class);
    $serviceAvailabilityMatrix = $component->viewData('serviceAvailabilityMatrix');

    $testServiceRow = collect($serviceAvailabilityMatrix)->firstWhere('label', 'Test Service');

    expect($testServiceRow['entries'][0]['count'])->toBe(1);
    expect($testServiceRow['entries'][0]['manager_only'])->toBe(false);
});

test('manager unavailable when count is zero shows blank cell', function () {
    $manager = User::factory()->create();
    $team = Team::factory()->create(['manager_id' => $manager->id]);

    $serviceManager = User::factory()->create();
    $service = Service::factory()->create([
        'name' => 'Test Service',
        'manager_id' => $serviceManager->id,
    ]);

    $monday = now()->startOfWeek();

    PlanEntry::factory()->create([
        'user_id' => $serviceManager->id,
        'entry_date' => $monday,
        'location' => null,
        'is_available' => false,
    ]);

    actingAs($manager);

    $component = Livewire::test(\App\Livewire\ManagerReport::class);
    $serviceAvailabilityMatrix = $component->viewData('serviceAvailabilityMatrix');

    $testServiceRow = collect($serviceAvailabilityMatrix)->firstWhere('label', 'Test Service');

    expect($testServiceRow['entries'][0]['count'])->toBe(0);
    expect($testServiceRow['entries'][0]['manager_only'])->toBe(false);
});

test('manager is also a service member counts in regular count', function () {
    $manager = User::factory()->create();
    $team = Team::factory()->create(['manager_id' => $manager->id]);

    $serviceManager = User::factory()->create();
    $service = Service::factory()->create([
        'name' => 'Test Service',
        'manager_id' => $serviceManager->id,
    ]);

    $service->users()->attach($serviceManager->id);

    $monday = now()->startOfWeek();

    PlanEntry::factory()->create([
        'user_id' => $serviceManager->id,
        'entry_date' => $monday,
        'location' => Location::JWS,
        'is_available' => true,
    ]);

    actingAs($manager);

    $component = Livewire::test(\App\Livewire\ManagerReport::class);
    $serviceAvailabilityMatrix = $component->viewData('serviceAvailabilityMatrix');

    $testServiceRow = collect($serviceAvailabilityMatrix)->firstWhere('label', 'Test Service');

    expect($testServiceRow['entries'][0]['count'])->toBe(1);
    expect($testServiceRow['entries'][0]['manager_only'])->toBe(false);
});

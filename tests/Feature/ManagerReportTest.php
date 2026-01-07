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

test('manager can view team report page', function () {
    $manager = User::factory()->create();
    $team = Team::factory()->create(['manager_id' => $manager->id]);

    actingAs($manager);

    Livewire::test(\App\Livewire\ManagerReport::class)
        ->assertOk()
        ->assertSee('Team Report')
        ->assertSee('My Reports')
        ->assertSee('By Location');
});

test('non-manager cannot access team report page', function () {
    $user = User::factory()->create();

    actingAs($user);

    Livewire::test(\App\Livewire\ManagerReport::class)
        ->assertForbidden();
});

test('manager sees their team members', function () {
    $manager = User::factory()->create();
    $team = Team::factory()->create(['manager_id' => $manager->id]);

    $teamMember1 = User::factory()->create(['surname' => 'Smith', 'forenames' => 'John']);
    $teamMember2 = User::factory()->create(['surname' => 'Doe', 'forenames' => 'Jane']);

    $team->users()->attach([$teamMember1->id, $teamMember2->id]);

    actingAs($manager);

    Livewire::test(\App\Livewire\ManagerReport::class)
        ->assertOk()
        ->assertSee('Smith, John')
        ->assertSee('Doe, Jane');
});

test('page shows 10 weekdays only', function () {
    $manager = User::factory()->create();
    Team::factory()->create(['manager_id' => $manager->id]);

    actingAs($manager);

    $component = Livewire::test(\App\Livewire\ManagerReport::class);

    // Should see weekday names
    $component->assertSee('Mon')
        ->assertSee('Tue')
        ->assertSee('Wed')
        ->assertSee('Thu')
        ->assertSee('Fri');

    // Count should be 10 days (2 weeks of weekdays)
    expect(count($component->viewData('days')))->toBe(10);
});

test('existing plan entries display correctly in team view', function () {
    $manager = User::factory()->create();
    $team = Team::factory()->create(['manager_id' => $manager->id]);

    $teamMember = User::factory()->create(['surname' => 'Smith', 'forenames' => 'John']);
    $team->users()->attach($teamMember->id);
    $location = Location::factory()->create(['slug' => 'other', 'name' => 'Other']);

    $monday = now()->startOfWeek();

    PlanEntry::factory()->create([
        'user_id' => $teamMember->id,
        'entry_date' => $monday,
        'note' => 'Working on tickets',
        'location_id' => $location->id,
    ]);

    actingAs($manager);

    Livewire::test(\App\Livewire\ManagerReport::class)
        ->assertOk()
        ->assertSee('Smith, John')
        ->assertSee('Other');
});

test('empty entries are handled gracefully', function () {
    $manager = User::factory()->create();
    $team = Team::factory()->create(['manager_id' => $manager->id]);

    $teamMember = User::factory()->create(['surname' => 'Jones', 'forenames' => 'Bob']);
    $team->users()->attach($teamMember->id);

    actingAs($manager);

    Livewire::test(\App\Livewire\ManagerReport::class)
        ->assertOk()
        ->assertSee('Jones, Bob')
        ->assertSee('-'); // Empty entry indicator
});

test('by location view groups team members correctly', function () {
    $manager = User::factory()->create();
    $team = Team::factory()->create(['manager_id' => $manager->id]);

    $member1 = User::factory()->create(['surname' => 'Smith', 'forenames' => 'John']);
    $member2 = User::factory()->create(['surname' => 'Doe', 'forenames' => 'Jane']);
    $team->users()->attach([$member1->id, $member2->id]);

    $locationOther = Location::factory()->create(['slug' => 'other', 'name' => 'Other']);
    $locationJws = Location::factory()->create(['slug' => 'jws', 'name' => 'JWS']);

    $monday = now()->startOfWeek();

    PlanEntry::factory()->create([
        'user_id' => $member1->id,
        'entry_date' => $monday,
        'note' => 'Support tickets',
        'location_id' => $locationOther->id,
    ]);

    PlanEntry::factory()->create([
        'user_id' => $member2->id,
        'entry_date' => $monday,
        'note' => 'Development work',
        'location_id' => $locationJws->id,
    ]);

    actingAs($manager);

    Livewire::test(\App\Livewire\ManagerReport::class)
        ->assertOk()
        ->assertSee('Other')
        ->assertSee('JWS')
        ->assertSee('Smith, John')
        ->assertSee('Doe, Jane')
        ->assertSee('Support tickets')
        ->assertSee('Development work');
});

test('manager sees multiple team members from same team', function () {
    $manager = User::factory()->create();
    $team = Team::factory()->create(['manager_id' => $manager->id]);

    $members = User::factory()->count(5)->create();
    $team->users()->attach($members->pluck('id'));

    actingAs($manager);

    $component = Livewire::test(\App\Livewire\ManagerReport::class);

    expect(count($component->viewData('teamRows')))->toBe(5);
});

test('manager with multiple teams sees all team members', function () {
    $manager = User::factory()->create();
    $team1 = Team::factory()->create(['manager_id' => $manager->id]);
    $team2 = Team::factory()->create(['manager_id' => $manager->id]);

    $member1 = User::factory()->create(['surname' => 'Alpha', 'forenames' => 'User']);
    $member2 = User::factory()->create(['surname' => 'Beta', 'forenames' => 'User']);
    $member3 = User::factory()->create(['surname' => 'Gamma', 'forenames' => 'User']);

    $team1->users()->attach([$member1->id, $member2->id]);
    $team2->users()->attach([$member3->id]);

    actingAs($manager);

    Livewire::test(\App\Livewire\ManagerReport::class)
        ->assertOk()
        ->assertSee('Alpha, User')
        ->assertSee('Beta, User')
        ->assertSee('Gamma, User');
});

test('duplicate team members across teams only shown once', function () {
    $manager = User::factory()->create();
    $team1 = Team::factory()->create(['manager_id' => $manager->id]);
    $team2 = Team::factory()->create(['manager_id' => $manager->id]);

    $member = User::factory()->create(['surname' => 'Shared', 'forenames' => 'Member']);

    // Same member on both teams
    $team1->users()->attach($member->id);
    $team2->users()->attach($member->id);

    actingAs($manager);

    $component = Livewire::test(\App\Livewire\ManagerReport::class);

    // Should only appear once
    expect(count($component->viewData('teamRows')))->toBe(1);
});

test('manager can download excel report', function () {
    Date::setTestNow('2024-01-01 09:00:00');

    $manager = User::factory()->create();
    Team::factory()->create(['manager_id' => $manager->id]);

    actingAs($manager);

    Livewire::test(\App\Livewire\ManagerReport::class)
        ->call('exportAll')
        ->assertFileDownloaded('manager-report-20240101-20240112.xlsx');

    Date::setTestNow();
});

test('toggle switch changes display between location and note', function () {
    $manager = User::factory()->create();
    $team = Team::factory()->create(['manager_id' => $manager->id]);

    $teamMember = User::factory()->create(['surname' => 'Smith', 'forenames' => 'John']);
    $team->users()->attach($teamMember->id);
    $location = Location::factory()->create(['slug' => 'other', 'name' => 'Other']);

    $monday = now()->startOfWeek();

    PlanEntry::factory()->create([
        'user_id' => $teamMember->id,
        'entry_date' => $monday,
        'note' => 'Working on tickets',
        'location_id' => $location->id,
    ]);

    actingAs($manager);

    Livewire::test(\App\Livewire\ManagerReport::class)
        ->assertOk()
        ->assertSet('showLocation', true)
        ->assertSee('Other')
        ->assertSee('Show Locations')
        ->set('showLocation', false)
        ->assertSet('showLocation', false)
        ->assertSee('Working on tickets');
});

test('coverage tab shows location coverage grid', function () {
    $manager = User::factory()->create();
    $team = Team::factory()->create(['manager_id' => $manager->id]);

    $member1 = User::factory()->create();
    $member2 = User::factory()->create();
    $member3 = User::factory()->create();
    $team->users()->attach([$member1->id, $member2->id, $member3->id]);

    $locationOther = Location::factory()->create(['slug' => 'other', 'name' => 'Other']);
    $locationJws = Location::factory()->create(['slug' => 'jws', 'name' => 'JWS']);
    $locationJwn = Location::factory()->create(['slug' => 'jwn', 'name' => 'JWN']);

    $monday = now()->startOfWeek();
    $tuesday = $monday->copy()->addDay();

    // Monday: 2 at Other, 1 at JWS
    PlanEntry::factory()->create(['user_id' => $member1->id, 'entry_date' => $monday, 'location_id' => $locationOther->id]);
    PlanEntry::factory()->create(['user_id' => $member2->id, 'entry_date' => $monday, 'location_id' => $locationOther->id]);
    PlanEntry::factory()->create(['user_id' => $member3->id, 'entry_date' => $monday, 'location_id' => $locationJws->id]);

    // Tuesday: 3 at JWS
    PlanEntry::factory()->create(['user_id' => $member1->id, 'entry_date' => $tuesday, 'location_id' => $locationJws->id]);
    PlanEntry::factory()->create(['user_id' => $member2->id, 'entry_date' => $tuesday, 'location_id' => $locationJws->id]);
    PlanEntry::factory()->create(['user_id' => $member3->id, 'entry_date' => $tuesday, 'location_id' => $locationJws->id]);

    actingAs($manager);

    $component = Livewire::test(\App\Livewire\ManagerReport::class);

    $coverageMatrix = $component->viewData('coverageMatrix');

    // Find the 'Other', 'JWS', and 'JWN' rows in the coverage matrix
    $otherRow = collect($coverageMatrix)->firstWhere('label', 'Other');
    $jwsRow = collect($coverageMatrix)->firstWhere('label', 'JWS');
    $jwnRow = collect($coverageMatrix)->firstWhere('label', 'JWN');

    // Monday (index 0) coverage
    expect($otherRow['entries'][0]['count'])->toBe(2);
    expect($jwsRow['entries'][0]['count'])->toBe(1);
    expect($jwnRow['entries'][0]['count'])->toBe(0);

    // Tuesday (index 1) coverage
    expect($jwsRow['entries'][1]['count'])->toBe(3);
    expect($otherRow['entries'][1]['count'])->toBe(0);

    // Check UI renders coverage tab
    $component->assertSee('Coverage')
        ->assertSee('Location coverage at a glance');
});

test('admin can see toggle switch for all users', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $team = Team::factory()->create(['manager_id' => $admin->id]);

    actingAs($admin);

    Livewire::test(\App\Livewire\ManagerReport::class)
        ->assertOk()
        ->assertSee('View All Users');
});

test('non-admin does not see toggle switch', function () {
    $manager = User::factory()->create(['is_admin' => false]);
    $team = Team::factory()->create(['manager_id' => $manager->id]);

    actingAs($manager);

    Livewire::test(\App\Livewire\ManagerReport::class)
        ->assertOk()
        ->assertDontSee('View All Users');
});

test('admin with toggle enabled sees all users not just team', function () {
    $admin = User::factory()->create(['is_admin' => true, 'surname' => 'McAdmin', 'forenames' => 'Admin']);
    $team = Team::factory()->create(['manager_id' => $admin->id]);

    // Team member
    $teamMember = User::factory()->create(['surname' => 'TeamMember', 'forenames' => 'John']);
    $team->users()->attach($teamMember->id);

    // Non-team member
    $otherUser = User::factory()->create(['surname' => 'OtherUser', 'forenames' => 'Jane']);

    actingAs($admin);

    Livewire::test(\App\Livewire\ManagerReport::class)
        ->assertOk()
        ->assertSet('showAllUsers', true)
        ->assertSee('McAdmin, Admin')
        ->assertSee('TeamMember, John')
        ->assertSee('OtherUser, Jane')
        ->set('showAllUsers', false)
        ->assertSet('showAllUsers', false)
        ->assertSee('TeamMember, John')
        ->assertDontSee('OtherUser, Jane')
        ->assertDontSee('McAdmin, Admin');
});

test('admin with toggle disabled sees only their team', function () {
    $admin = User::factory()->create(['is_admin' => true, 'surname' => 'McAdmin', 'forenames' => 'Admin']);
    $team = Team::factory()->create(['manager_id' => $admin->id]);

    // Team member
    $teamMember = User::factory()->create(['surname' => 'TeamMember', 'forenames' => 'John']);
    $team->users()->attach($teamMember->id);

    // Non-team member
    $otherUser = User::factory()->create(['surname' => 'OtherUser', 'forenames' => 'Jane']);

    actingAs($admin);

    $component = Livewire::test(\App\Livewire\ManagerReport::class);

    // Default state - toggle is on for admins
    expect(count($component->viewData('teamRows')))->toBe(3);
    $component->assertSee('TeamMember, John')
        ->assertSee('OtherUser, Jane')
        ->assertSee('McAdmin, Admin');
});

test('manager sees team filter pillbox with their teams', function () {
    $manager = User::factory()->create();
    $team1 = Team::factory()->create(['manager_id' => $manager->id, 'name' => 'Infrastructure Team']);
    $team2 = Team::factory()->create(['manager_id' => $manager->id, 'name' => 'Support Team']);

    actingAs($manager);

    Livewire::test(\App\Livewire\ManagerReport::class)
        ->assertOk()
        ->assertSee('Teams')
        ->assertSee('Infrastructure Team')
        ->assertSee('Support Team');
});

test('filtering by specific team shows only that team members', function () {
    $manager = User::factory()->create();
    $team1 = Team::factory()->create(['manager_id' => $manager->id, 'name' => 'Team Alpha']);
    $team2 = Team::factory()->create(['manager_id' => $manager->id, 'name' => 'Team Beta']);

    $member1 = User::factory()->create(['surname' => 'Alpha', 'forenames' => 'User']);
    $member2 = User::factory()->create(['surname' => 'Beta', 'forenames' => 'User']);

    $team1->users()->attach($member1->id);
    $team2->users()->attach($member2->id);

    actingAs($manager);

    Livewire::test(\App\Livewire\ManagerReport::class)
        ->assertOk()
        ->assertSee('Alpha, User')
        ->assertSee('Beta, User')
        ->set('selectedTeams', [$team1->id])
        ->assertSee('Alpha, User')
        ->assertDontSee('Beta, User');
});

test('filtering by multiple teams shows all their members', function () {
    $manager = User::factory()->create();
    $team1 = Team::factory()->create(['manager_id' => $manager->id, 'name' => 'Team One']);
    $team2 = Team::factory()->create(['manager_id' => $manager->id, 'name' => 'Team Two']);
    $team3 = Team::factory()->create(['manager_id' => $manager->id, 'name' => 'Team Three']);

    $member1 = User::factory()->create(['surname' => 'One', 'forenames' => 'User']);
    $member2 = User::factory()->create(['surname' => 'Two', 'forenames' => 'User']);
    $member3 = User::factory()->create(['surname' => 'Three', 'forenames' => 'User']);

    $team1->users()->attach($member1->id);
    $team2->users()->attach($member2->id);
    $team3->users()->attach($member3->id);

    actingAs($manager);

    Livewire::test(\App\Livewire\ManagerReport::class)
        ->assertOk()
        ->set('selectedTeams', [$team1->id, $team2->id])
        ->assertSee('One, User')
        ->assertSee('Two, User')
        ->assertDontSee('Three, User');
});

test('admin with show all users enabled can filter by any team', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $adminTeam = Team::factory()->create(['manager_id' => $admin->id, 'name' => 'Admin Team']);

    // Another manager's team
    $otherManager = User::factory()->create();
    $otherTeam = Team::factory()->create(['manager_id' => $otherManager->id, 'name' => 'Other Team']);

    $adminMember = User::factory()->create(['surname' => 'AdminMember', 'forenames' => 'John']);
    $otherMember = User::factory()->create(['surname' => 'OtherMember', 'forenames' => 'Jane']);

    $adminTeam->users()->attach($adminMember->id);
    $otherTeam->users()->attach($otherMember->id);

    actingAs($admin);

    Livewire::test(\App\Livewire\ManagerReport::class)
        ->assertOk()
        ->set('showAllUsers', true)
        ->assertSee('Other Team') // Should see all teams in pillbox
        ->set('selectedTeams', [$otherTeam->id])
        ->assertDontSee('AdminMember, John')
        ->assertSee('OtherMember, Jane');
});

test('team filtering overrides show all users toggle', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $team = Team::factory()->create(['manager_id' => $admin->id, 'name' => 'Test Team']);

    $teamMember = User::factory()->create(['surname' => 'TeamMember', 'forenames' => 'John']);
    $otherUser = User::factory()->create(['surname' => 'OtherUser', 'forenames' => 'Jane']);

    $team->users()->attach($teamMember->id);

    actingAs($admin);

    Livewire::test(\App\Livewire\ManagerReport::class)
        ->assertOk()
        ->set('showAllUsers', true)
        ->assertSee('TeamMember, John')
        ->assertSee('OtherUser, Jane')
        ->set('selectedTeams', [$team->id])
        ->assertSee('TeamMember, John')
        ->assertDontSee('OtherUser, Jane'); // Team filter overrides show all
});

test('unavailable users with null location show as away in my team tab', function () {
    $manager = User::factory()->create();
    $team = Team::factory()->create(['manager_id' => $manager->id]);

    $teamMember = User::factory()->create(['surname' => 'Smith', 'forenames' => 'John']);
    $team->users()->attach($teamMember->id);

    $monday = now()->startOfWeek();

    PlanEntry::factory()->unavailable()->create([
        'user_id' => $teamMember->id,
        'entry_date' => $monday,
        'note' => 'On holiday',
        'location_id' => null,
    ]);

    actingAs($manager);

    Livewire::test(\App\Livewire\ManagerReport::class)
        ->assertOk()
        ->assertSee('Smith, John')
        ->assertSee('Away');
});

test('unavailable users do not appear in by location view', function () {
    $manager = User::factory()->create();
    $team = Team::factory()->create(['manager_id' => $manager->id]);

    $member1 = User::factory()->create(['surname' => 'Available', 'forenames' => 'User']);
    $member2 = User::factory()->create(['surname' => 'Unavailable', 'forenames' => 'User']);
    $team->users()->attach([$member1->id, $member2->id]);

    $location = Location::factory()->create(['slug' => 'other', 'name' => 'Other']);

    $monday = now()->startOfWeek();

    // Member 1 is at Other
    PlanEntry::factory()->create([
        'user_id' => $member1->id,
        'entry_date' => $monday,
        'location_id' => $location->id,
    ]);

    // Member 2 is unavailable
    PlanEntry::factory()->unavailable()->create([
        'user_id' => $member2->id,
        'entry_date' => $monday,
        'location_id' => null,
    ]);

    actingAs($manager);

    $component = Livewire::test(\App\Livewire\ManagerReport::class);

    $locationDays = $component->viewData('locationDays');

    // Monday is the first day (index 0)
    $mondayData = $locationDays[0];

    // Only member1 should appear in 'Other' location
    expect($mondayData['locations'][$location->id]['members'])->toHaveCount(1);
    expect($mondayData['locations'][$location->id]['members'][0]['name'])->toBe('Available, User');

    // Unavailable member should not appear in any location
    $component->assertSee('Available, User');
});

test('unavailable users do not appear in coverage counts', function () {
    $manager = User::factory()->create();
    $team = Team::factory()->create(['manager_id' => $manager->id]);

    $member1 = User::factory()->create();
    $member2 = User::factory()->create();
    $team->users()->attach([$member1->id, $member2->id]);

    $location = Location::factory()->create(['slug' => 'other', 'name' => 'Other']);

    $monday = now()->startOfWeek();

    // Member1 at Other on Monday
    PlanEntry::factory()->create([
        'user_id' => $member1->id,
        'entry_date' => $monday,
        'location_id' => $location->id,
    ]);

    // Member2 unavailable on Monday
    PlanEntry::factory()->unavailable()->create([
        'user_id' => $member2->id,
        'entry_date' => $monday,
        'location_id' => null,
    ]);

    actingAs($manager);

    $component = Livewire::test(\App\Livewire\ManagerReport::class);
    $coverageMatrix = $component->viewData('coverageMatrix');

    // Find the 'Other' location row
    $otherRow = collect($coverageMatrix)->firstWhere('label', 'Other');

    // Coverage should only count member1, not member2 (Monday is index 0)
    expect($otherRow['entries'][0]['count'])->toBe(1);
});

test('coverage matrix only shows physical locations', function () {
    $manager = User::factory()->create();
    $team = Team::factory()->create(['manager_id' => $manager->id]);

    $member = User::factory()->create();
    $team->users()->attach($member->id);

    $physicalLocation = Location::factory()->create(['name' => 'Office A', 'is_physical' => true]);
    $nonPhysicalLocation = Location::factory()->nonPhysical()->create(['name' => 'Other']);

    $monday = now()->startOfWeek();

    PlanEntry::factory()->create([
        'user_id' => $member->id,
        'entry_date' => $monday,
        'location_id' => $physicalLocation->id,
    ]);

    actingAs($manager);

    $component = Livewire::test(\App\Livewire\ManagerReport::class);
    $coverageMatrix = $component->viewData('coverageMatrix');

    $labels = collect($coverageMatrix)->pluck('label')->toArray();

    expect($labels)->toContain('Office A');
    expect($labels)->not->toContain('Other');
});

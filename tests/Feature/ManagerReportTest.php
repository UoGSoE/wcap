<?php

use App\Enums\Location;
use App\Models\PlanEntry;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        ->assertSee('My Team')
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

    $monday = now()->startOfWeek();

    PlanEntry::factory()->create([
        'user_id' => $teamMember->id,
        'entry_date' => $monday,
        'note' => 'Working on tickets',
        'location' => Location::HOME,
    ]);

    actingAs($manager);

    Livewire::test(\App\Livewire\ManagerReport::class)
        ->assertOk()
        ->assertSee('Smith, John')
        ->assertSee('Home');
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

    $monday = now()->startOfWeek();

    PlanEntry::factory()->create([
        'user_id' => $member1->id,
        'entry_date' => $monday,
        'note' => 'Support tickets',
        'location' => Location::HOME,
    ]);

    PlanEntry::factory()->create([
        'user_id' => $member2->id,
        'entry_date' => $monday,
        'note' => 'Development work',
        'location' => Location::JWS,
    ]);

    actingAs($manager);

    Livewire::test(\App\Livewire\ManagerReport::class)
        ->assertOk()
        ->assertSee('Home')
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

    expect(count($component->viewData('teamMembers')))->toBe(5);
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
    expect(count($component->viewData('teamMembers')))->toBe(1);
});

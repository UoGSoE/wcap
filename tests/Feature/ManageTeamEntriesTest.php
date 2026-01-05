<?php

use App\Models\Location;
use App\Models\PlanEntry;
use App\Models\Team;
use App\Models\User;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

test('non-manager cannot access page', function () {
    $user = User::factory()->create();

    actingAs($user);

    $this->get(route('manager.entries'))->assertForbidden();
});

test('manager can access page', function () {
    $manager = User::factory()->create();
    $team = Team::factory()->create(['manager_id' => $manager->id]);
    $member = User::factory()->create();
    $team->users()->attach($member->id);

    actingAs($manager);

    $this->get(route('manager.entries'))->assertOk();
});

test('manager sees their teams in selector when multiple teams', function () {
    $manager = User::factory()->create();
    $teamA = Team::factory()->create(['manager_id' => $manager->id, 'name' => 'Alpha Team']);
    $teamB = Team::factory()->create(['manager_id' => $manager->id, 'name' => 'Beta Team']);

    $memberA = User::factory()->create();
    $memberB = User::factory()->create();
    $teamA->users()->attach($memberA->id);
    $teamB->users()->attach($memberB->id);

    actingAs($manager);

    Livewire::test(\App\Livewire\ManageTeamEntries::class)
        ->assertSee('Alpha Team')
        ->assertSee('Beta Team');
});

test('team selector always shows with My Plan and real teams', function () {
    $manager = User::factory()->create();
    $team = Team::factory()->create(['manager_id' => $manager->id, 'name' => 'Only Team']);
    $member = User::factory()->create();
    $team->users()->attach($member->id);

    actingAs($manager);

    // With My Plan + one real team = 2 options, selector should show
    Livewire::test(\App\Livewire\ManageTeamEntries::class)
        ->assertSee('My Plan')
        ->assertSee('Only Team');
});

test('manager sees team members as tabs when real team selected', function () {
    $manager = User::factory()->create();
    $team = Team::factory()->create(['manager_id' => $manager->id]);

    $memberA = User::factory()->create(['surname' => 'Adams', 'forenames' => 'Alice']);
    $memberB = User::factory()->create(['surname' => 'Brown', 'forenames' => 'Bob']);
    $team->users()->attach([$memberA->id, $memberB->id]);

    actingAs($manager);

    Livewire::test(\App\Livewire\ManageTeamEntries::class)
        ->set('selectedTeamId', $team->id) // Switch to real team
        ->assertSee('Adams, A')
        ->assertSee('Brown, B');
});

test('real teams are ordered by name in selector', function () {
    $manager = User::factory()->create();
    $teamZ = Team::factory()->create(['manager_id' => $manager->id, 'name' => 'Zebra Team']);
    $teamA = Team::factory()->create(['manager_id' => $manager->id, 'name' => 'Alpha Team']);

    $memberZ = User::factory()->create();
    $memberA = User::factory()->create();
    $teamZ->users()->attach($memberZ->id);
    $teamA->users()->attach($memberA->id);

    actingAs($manager);

    // My Plan appears first, then real teams alphabetically
    Livewire::test(\App\Livewire\ManageTeamEntries::class)
        ->assertSeeInOrder(['My Plan', 'Alpha Team', 'Zebra Team']);
});

test('selecting real team defaults to first user ordered by surname', function () {
    $manager = User::factory()->create();
    $team = Team::factory()->create(['manager_id' => $manager->id]);

    $memberZ = User::factory()->create(['surname' => 'Zulu']);
    $memberA = User::factory()->create(['surname' => 'Alpha']);
    $team->users()->attach([$memberZ->id, $memberA->id]);

    actingAs($manager);

    Livewire::test(\App\Livewire\ManageTeamEntries::class)
        ->set('selectedTeamId', $team->id)
        ->assertSet('selectedUserId', $memberA->id);
});

test('changing team resets to first user of new team', function () {
    $manager = User::factory()->create();
    $teamA = Team::factory()->create(['manager_id' => $manager->id, 'name' => 'Alpha']);
    $teamB = Team::factory()->create(['manager_id' => $manager->id, 'name' => 'Beta']);

    $memberA = User::factory()->create(['surname' => 'Adams']);
    $memberB = User::factory()->create(['surname' => 'Brown']);
    $teamA->users()->attach($memberA->id);
    $teamB->users()->attach($memberB->id);

    actingAs($manager);

    Livewire::test(\App\Livewire\ManageTeamEntries::class)
        ->assertSet('selectedTeamId', 0) // Defaults to My Plan
        ->set('selectedTeamId', $teamA->id)
        ->assertSet('selectedUserId', $memberA->id)
        ->set('selectedTeamId', $teamB->id)
        ->assertSet('selectedUserId', $memberB->id);
});

test('shows warning when team has no members', function () {
    $manager = User::factory()->create();
    $team = Team::factory()->create(['manager_id' => $manager->id]);
    // No members attached

    actingAs($manager);

    Livewire::test(\App\Livewire\ManageTeamEntries::class)
        ->set('selectedTeamId', $team->id) // Switch to the empty team
        ->assertSee('This team has no members');
});

test('cannot select team they do not manage', function () {
    $manager = User::factory()->create();
    $otherManager = User::factory()->create();

    $ownTeam = Team::factory()->create(['manager_id' => $manager->id]);
    $otherTeam = Team::factory()->create(['manager_id' => $otherManager->id]);

    $ownMember = User::factory()->create();
    $otherMember = User::factory()->create();
    $ownTeam->users()->attach($ownMember->id);
    $otherTeam->users()->attach($otherMember->id);

    actingAs($manager);

    Livewire::test(\App\Livewire\ManageTeamEntries::class)
        ->set('selectedTeamId', $otherTeam->id)
        ->assertDontSee($otherMember->surname);
});

test('cannot select user outside their teams', function () {
    $manager = User::factory()->create();
    $otherManager = User::factory()->create();

    $ownTeam = Team::factory()->create(['manager_id' => $manager->id]);
    $otherTeam = Team::factory()->create(['manager_id' => $otherManager->id]);

    $ownMember = User::factory()->create();
    $outsideUser = User::factory()->create();
    $ownTeam->users()->attach($ownMember->id);
    $otherTeam->users()->attach($outsideUser->id);

    actingAs($manager);

    Livewire::test(\App\Livewire\ManageTeamEntries::class)
        ->set('selectedUserId', $outsideUser->id)
        ->assertSet('selectedUserId', null);
});

test('saving via editor sets created_by_manager flag', function () {
    $manager = User::factory()->create();
    $team = Team::factory()->create(['manager_id' => $manager->id]);
    $member = User::factory()->create();
    $team->users()->attach($member->id);
    $location = Location::factory()->create(['slug' => 'jws', 'name' => 'JWS']);

    actingAs($manager);

    $entries = collect(range(0, 13))->map(function ($offset) use ($location) {
        $date = now()->startOfWeek()->addDays($offset);

        return [
            'id' => null,
            'entry_date' => $date->format('Y-m-d'),
            'note' => 'Manager entered',
            'location_id' => $location->id,
            'is_available' => true,
        ];
    })->toArray();

    Livewire::test(\App\Livewire\PlanEntryEditor::class, [
        'user' => $member,
        'readOnly' => false,
        'createdByManager' => true,
    ])
        ->set('entries', $entries)
        ->call('save')
        ->assertOk();

    $entry = PlanEntry::where('user_id', $member->id)->first();
    expect($entry->created_by_manager)->toBeTrue();
});

// Self-team tests

test('manager sees My Plan in team selector', function () {
    $manager = User::factory()->create();
    $team = Team::factory()->create(['manager_id' => $manager->id, 'name' => 'Real Team']);
    $member = User::factory()->create();
    $team->users()->attach($member->id);

    actingAs($manager);

    Livewire::test(\App\Livewire\ManageTeamEntries::class)
        ->assertSee('My Plan')
        ->assertSee('Real Team');
});

test('manager defaults to My Plan on mount', function () {
    $manager = User::factory()->create();
    $team = Team::factory()->create(['manager_id' => $manager->id]);
    $member = User::factory()->create();
    $team->users()->attach($member->id);

    actingAs($manager);

    Livewire::test(\App\Livewire\ManageTeamEntries::class)
        ->assertSet('selectedTeamId', 0)
        ->assertSet('selectedUserId', $manager->id);
});

test('manager can save their own entries via My Plan', function () {
    $manager = User::factory()->create();
    $team = Team::factory()->create(['manager_id' => $manager->id]);
    $member = User::factory()->create();
    $team->users()->attach($member->id);
    $location = Location::factory()->create(['slug' => 'jws', 'name' => 'JWS']);

    actingAs($manager);

    $entries = collect(range(0, 13))->map(function ($offset) use ($location) {
        $date = now()->startOfWeek()->addDays($offset);

        return [
            'id' => null,
            'entry_date' => $date->format('Y-m-d'),
            'note' => 'My own task',
            'location_id' => $location->id,
            'is_available' => true,
        ];
    })->toArray();

    Livewire::test(\App\Livewire\PlanEntryEditor::class, [
        'user' => $manager,
        'readOnly' => false,
        'createdByManager' => false,
    ])
        ->set('entries', $entries)
        ->call('save')
        ->assertOk();

    expect(PlanEntry::where('user_id', $manager->id)->count())->toBe(14);
});

test('own entries have created_by_manager false', function () {
    $manager = User::factory()->create();
    $team = Team::factory()->create(['manager_id' => $manager->id]);
    $member = User::factory()->create();
    $team->users()->attach($member->id);
    $location = Location::factory()->create(['slug' => 'jws', 'name' => 'JWS']);

    actingAs($manager);

    $entries = collect(range(0, 13))->map(function ($offset) use ($location) {
        $date = now()->startOfWeek()->addDays($offset);

        return [
            'id' => null,
            'entry_date' => $date->format('Y-m-d'),
            'note' => 'My own task',
            'location_id' => $location->id,
            'is_available' => true,
        ];
    })->toArray();

    Livewire::test(\App\Livewire\PlanEntryEditor::class, [
        'user' => $manager,
        'readOnly' => false,
        'createdByManager' => false,
    ])
        ->set('entries', $entries)
        ->call('save')
        ->assertOk();

    $entry = PlanEntry::where('user_id', $manager->id)->first();
    expect($entry->created_by_manager)->toBeFalse();
});

test('switching from My Plan to real team selects first team member', function () {
    $manager = User::factory()->create();
    $team = Team::factory()->create(['manager_id' => $manager->id]);
    $member = User::factory()->create(['surname' => 'Adams']);
    $team->users()->attach($member->id);

    actingAs($manager);

    Livewire::test(\App\Livewire\ManageTeamEntries::class)
        ->assertSet('selectedTeamId', 0)
        ->assertSet('selectedUserId', $manager->id)
        ->set('selectedTeamId', $team->id)
        ->assertSet('selectedUserId', $member->id);
});

test('switching back to My Plan selects manager', function () {
    $manager = User::factory()->create();
    $team = Team::factory()->create(['manager_id' => $manager->id]);
    $member = User::factory()->create();
    $team->users()->attach($member->id);

    actingAs($manager);

    Livewire::test(\App\Livewire\ManageTeamEntries::class)
        ->set('selectedTeamId', $team->id)
        ->assertSet('selectedUserId', $member->id)
        ->set('selectedTeamId', 0)
        ->assertSet('selectedUserId', $manager->id);
});

<?php

use App\Enums\Location;
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

test('team selector hidden when manager has only one team', function () {
    $manager = User::factory()->create();
    $team = Team::factory()->create(['manager_id' => $manager->id, 'name' => 'Only Team']);
    $member = User::factory()->create();
    $team->users()->attach($member->id);

    actingAs($manager);

    Livewire::test(\App\Livewire\ManageTeamEntries::class)
        ->assertDontSee('Select a team');
});

test('manager sees team members as tabs', function () {
    $manager = User::factory()->create();
    $team = Team::factory()->create(['manager_id' => $manager->id]);

    $memberA = User::factory()->create(['surname' => 'Adams', 'forenames' => 'Alice']);
    $memberB = User::factory()->create(['surname' => 'Brown', 'forenames' => 'Bob']);
    $team->users()->attach([$memberA->id, $memberB->id]);

    actingAs($manager);

    Livewire::test(\App\Livewire\ManageTeamEntries::class)
        ->assertSee('Adams, A')
        ->assertSee('Brown, B');
});

test('defaults to first team ordered by name', function () {
    $manager = User::factory()->create();
    $teamZ = Team::factory()->create(['manager_id' => $manager->id, 'name' => 'Zebra Team']);
    $teamA = Team::factory()->create(['manager_id' => $manager->id, 'name' => 'Alpha Team']);

    $memberZ = User::factory()->create();
    $memberA = User::factory()->create();
    $teamZ->users()->attach($memberZ->id);
    $teamA->users()->attach($memberA->id);

    actingAs($manager);

    Livewire::test(\App\Livewire\ManageTeamEntries::class)
        ->assertSet('selectedTeamId', $teamA->id);
});

test('defaults to first user ordered by surname', function () {
    $manager = User::factory()->create();
    $team = Team::factory()->create(['manager_id' => $manager->id]);

    $memberZ = User::factory()->create(['surname' => 'Zulu']);
    $memberA = User::factory()->create(['surname' => 'Alpha']);
    $team->users()->attach([$memberZ->id, $memberA->id]);

    actingAs($manager);

    Livewire::test(\App\Livewire\ManageTeamEntries::class)
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
        ->assertSet('selectedTeamId', $teamA->id)
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

    actingAs($manager);

    $entries = collect(range(0, 13))->map(function ($offset) {
        $date = now()->startOfWeek()->addDays($offset);

        return [
            'id' => null,
            'entry_date' => $date->format('Y-m-d'),
            'note' => 'Manager entered',
            'location' => Location::JWS->value,
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

<?php

use App\Livewire\AdminTeams;
use App\Livewire\ManagerReport;
use App\Livewire\ManageTeamEntries;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('can assign a parent team to a team', function () {
    $parentTeam = Team::factory()->create(['name' => 'IT Support']);
    $childTeam = Team::factory()->create([
        'name' => 'Helpdesk',
        'parent_team_id' => $parentTeam->id,
    ]);

    expect($childTeam->parentTeam->id)->toBe($parentTeam->id);
    expect($childTeam->parentTeam->name)->toBe('IT Support');
});

it('can get all descendant team ids across multiple levels', function () {
    $grandparent = Team::factory()->create(['name' => 'IT']);
    $parent = Team::factory()->create(['name' => 'IT Support', 'parent_team_id' => $grandparent->id]);
    $child = Team::factory()->create(['name' => 'Helpdesk', 'parent_team_id' => $parent->id]);
    $unrelated = Team::factory()->create(['name' => 'HR']);

    $descendantIds = $grandparent->descendantIds();

    expect($descendantIds)->toContain($parent->id);
    expect($descendantIds)->toContain($child->id);
    expect($descendantIds)->not->toContain($grandparent->id);
    expect($descendantIds)->not->toContain($unrelated->id);
});

it('can get all ancestor team ids walking up the tree', function () {
    $grandparent = Team::factory()->create(['name' => 'IT']);
    $parent = Team::factory()->create(['name' => 'IT Support', 'parent_team_id' => $grandparent->id]);
    $child = Team::factory()->create(['name' => 'Helpdesk', 'parent_team_id' => $parent->id]);

    $ancestorIds = $child->ancestorIds();

    expect($ancestorIds)->toContain($parent->id);
    expect($ancestorIds)->toContain($grandparent->id);
    expect($ancestorIds)->not->toContain($child->id);
});

it('allManagedTeamIds includes descendant teams', function () {
    $manager = User::factory()->create();
    $parentTeam = Team::factory()->create(['name' => 'IT Support', 'manager_id' => $manager->id]);
    $childTeam = Team::factory()->create(['name' => 'Helpdesk', 'parent_team_id' => $parentTeam->id]);
    $grandchildTeam = Team::factory()->create(['name' => 'Tier 1', 'parent_team_id' => $childTeam->id]);
    $unrelatedTeam = Team::factory()->create(['name' => 'HR']);

    $teamIds = $manager->allManagedTeamIds();

    expect($teamIds)->toContain($parentTeam->id);
    expect($teamIds)->toContain($childTeam->id);
    expect($teamIds)->toContain($grandchildTeam->id);
    expect($teamIds)->not->toContain($unrelatedTeam->id);
});

it('parent team manager can manage plan for descendant team member', function () {
    $parentManager = User::factory()->create();
    $parentTeam = Team::factory()->create(['name' => 'IT Support', 'manager_id' => $parentManager->id]);
    $childTeam = Team::factory()->create(['name' => 'Helpdesk', 'parent_team_id' => $parentTeam->id]);
    $childMember = User::factory()->create();
    $childMember->teams()->attach($childTeam);

    expect($parentManager->canManagePlanFor($childMember))->toBeTrue();
});

it('sub-team manager cannot see parent team members', function () {
    $parentManager = User::factory()->create();
    $parentTeam = Team::factory()->create(['name' => 'IT Support', 'manager_id' => $parentManager->id]);
    $parentMember = User::factory()->create();
    $parentMember->teams()->attach($parentTeam);

    $childManager = User::factory()->create();
    $childTeam = Team::factory()->create(['name' => 'Helpdesk', 'manager_id' => $childManager->id, 'parent_team_id' => $parentTeam->id]);

    expect($childManager->canManagePlanFor($parentMember))->toBeFalse();
});

it('deleting a parent team orphans child teams rather than deleting them', function () {
    $parentTeam = Team::factory()->create(['name' => 'IT Support']);
    $childTeam = Team::factory()->create(['name' => 'Helpdesk', 'parent_team_id' => $parentTeam->id]);

    $parentTeam->delete();

    expect(Team::find($childTeam->id))->not->toBeNull();
    expect(Team::find($childTeam->id)->parent_team_id)->toBeNull();
});

it('parent team manager sees descendant teams in ManageTeamEntries', function () {
    $parentManager = User::factory()->create();
    $parentTeam = Team::factory()->create(['name' => 'IT Support', 'manager_id' => $parentManager->id]);
    $childTeam = Team::factory()->create(['name' => 'Glasgow Helpdesk', 'parent_team_id' => $parentTeam->id]);

    Livewire::actingAs($parentManager)
        ->test(ManageTeamEntries::class)
        ->assertSee('IT Support')
        ->assertSee('Glasgow Helpdesk');
});

it('admin can create a team with a parent team', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $manager = User::factory()->create();
    $parentTeam = Team::factory()->create(['name' => 'IT Support']);

    actingAs($admin);

    Livewire::test(AdminTeams::class)
        ->call('createTeam')
        ->set('teamName', 'Glasgow Helpdesk')
        ->set('managerId', $manager->id)
        ->set('parentTeamId', $parentTeam->id)
        ->call('save')
        ->assertHasNoErrors();

    $team = Team::where('name', 'Glasgow Helpdesk')->first();
    expect($team)->not->toBeNull();
    expect($team->parent_team_id)->toBe($parentTeam->id);
});

it('prevents circular reference when setting parent team', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $manager = User::factory()->create();
    $teamA = Team::factory()->create(['name' => 'Team A', 'manager_id' => $manager->id]);
    $teamB = Team::factory()->create(['name' => 'Team B', 'manager_id' => $manager->id, 'parent_team_id' => $teamA->id]);

    actingAs($admin);

    Livewire::test(AdminTeams::class)
        ->call('editTeam', $teamA->id)
        ->set('parentTeamId', $teamB->id)
        ->call('save')
        ->assertHasErrors('parentTeamId');

    expect(Team::find($teamA->id)->parent_team_id)->toBeNull();
});

it('descendantIds returns empty array for leaf teams', function () {
    $team = Team::factory()->create(['name' => 'Leaf Team']);

    expect($team->descendantIds())->toBeEmpty();
});

it('admin can edit a team to change its parent', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $manager = User::factory()->create();
    $parentA = Team::factory()->create(['name' => 'Parent A']);
    $parentB = Team::factory()->create(['name' => 'Parent B']);
    $child = Team::factory()->create(['name' => 'Child Team', 'manager_id' => $manager->id, 'parent_team_id' => $parentA->id]);

    actingAs($admin);

    Livewire::test(AdminTeams::class)
        ->call('editTeam', $child->id)
        ->assertSet('parentTeamId', $parentA->id)
        ->set('parentTeamId', $parentB->id)
        ->call('save')
        ->assertHasNoErrors();

    expect(Team::find($child->id)->parent_team_id)->toBe($parentB->id);
});

it('admin can remove a parent team making it top-level', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $manager = User::factory()->create();
    $parent = Team::factory()->create(['name' => 'Parent']);
    $child = Team::factory()->create(['name' => 'Child', 'manager_id' => $manager->id, 'parent_team_id' => $parent->id]);

    actingAs($admin);

    Livewire::test(AdminTeams::class)
        ->call('editTeam', $child->id)
        ->set('parentTeamId', null)
        ->call('save')
        ->assertHasNoErrors();

    expect(Team::find($child->id)->parent_team_id)->toBeNull();
});

it('admin team list shows parent team name', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $parent = Team::factory()->create(['name' => 'Glasgow IT']);
    Team::factory()->create(['name' => 'Helpdesk', 'parent_team_id' => $parent->id]);

    actingAs($admin);

    Livewire::test(AdminTeams::class)
        ->assertSee('Glasgow IT');
});

it('admin without managed teams can access manager report', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    actingAs($admin);

    Livewire::test(ManagerReport::class)
        ->assertSuccessful();
});

it('admin without managed teams sees manager nav links', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)
        ->get(route('profile'))
        ->assertSee('Team Report');
});

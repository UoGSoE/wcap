<?php

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

test('non-admin cannot access team management page', function () {
    $user = User::factory()->create(['is_admin' => false]);

    $this->actingAs($user)->get(route('admin.teams'))->assertForbidden();
});

test('admin can view team management page', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $response = $this->actingAs($admin)->get(route('admin.teams'))->assertOk();
    $response->assertSee('Team Management');
    $response->assertSee('Create New Team');
});

test('admin can see all teams in the list', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $manager1 = User::factory()->create(['surname' => 'One', 'forenames' => 'Manager']);
    $manager2 = User::factory()->create(['surname' => 'Two', 'forenames' => 'Manager']);

    $team1 = Team::factory()->create([
        'name' => 'Infrastructure',
        'manager_id' => $manager1->id,
    ]);

    $team2 = Team::factory()->create([
        'name' => 'Support',
        'manager_id' => $manager2->id,
    ]);

    actingAs($admin);

    Livewire::test(\App\Livewire\AdminTeams::class)
        ->assertOk()
        ->assertSee('Infrastructure')
        ->assertSee('Support')
        ->assertSee('One, Manager')
        ->assertSee('Two, Manager');
});

test('admin can create a new team', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $manager = User::factory()->create();
    $member1 = User::factory()->create();
    $member2 = User::factory()->create();

    actingAs($admin);

    Livewire::test(\App\Livewire\AdminTeams::class)
        ->call('createTeam')
        ->set('teamName', 'New Team')
        ->set('managerId', $manager->id)
        ->set('selectedUserIds', [$member1->id, $member2->id])
        ->call('save')
        ->assertSet('editingTeamId', -1);

    $team = Team::where('name', 'New Team')->first();
    expect($team)->not->toBeNull();
    expect($team->manager_id)->toBe($manager->id);
    expect($team->users)->toHaveCount(2);
    expect($team->users->pluck('id')->toArray())->toContain($member1->id, $member2->id);
});

test('team name must be unique when creating', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $manager = User::factory()->create();

    Team::factory()->create(['name' => 'Existing Team', 'manager_id' => $manager->id]);

    actingAs($admin);

    Livewire::test(\App\Livewire\AdminTeams::class)
        ->call('createTeam')
        ->set('teamName', 'Existing Team')
        ->set('managerId', $manager->id)
        ->call('save')
        ->assertHasErrors(['teamName' => 'unique']);

    $this->assertEquals(1, Team::where('name', 'Existing Team')->count());
});

test('admin can edit an existing team', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $oldManager = User::factory()->create();
    $newManager = User::factory()->create();

    $team = Team::factory()->create([
        'name' => 'Original Name',
        'manager_id' => $oldManager->id,
    ]);

    actingAs($admin);

    Livewire::test(\App\Livewire\AdminTeams::class)
        ->call('editTeam', $team->id)
        ->assertSet('editingTeamId', $team->id)
        ->assertSet('teamName', 'Original Name')
        ->assertSet('managerId', $oldManager->id)
        ->set('teamName', 'Updated Name')
        ->set('managerId', $newManager->id)
        ->call('save')
        ->assertSet('editingTeamId', -1);

    $team->refresh();

    expect($team->name)->toBe('Updated Name');
    expect($team->manager_id)->toBe($newManager->id);
});

test('team name must be unique when updating but can keep same name', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $manager = User::factory()->create();

    $team1 = Team::factory()->create(['name' => 'Team One', 'manager_id' => $manager->id]);
    $team2 = Team::factory()->create(['name' => 'Team Two', 'manager_id' => $manager->id]);

    actingAs($admin);

    // Try to change team2's name to team1's name - should fail
    Livewire::test(\App\Livewire\AdminTeams::class)
        ->call('editTeam', $team2->id)
        ->set('teamName', 'Team One')
        ->call('save')
        ->assertHasErrors(['teamName' => 'unique']);

    // Keeping the same name should work
    Livewire::test(\App\Livewire\AdminTeams::class)
        ->call('editTeam', $team2->id)
        ->set('teamName', 'Team Two')
        ->call('save')
        ->assertHasNoErrors();
});

test('admin can update team members', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $manager = User::factory()->create();

    $team = Team::factory()->create(['manager_id' => $manager->id]);

    $oldMember1 = User::factory()->create();
    $oldMember2 = User::factory()->create();
    $newMember1 = User::factory()->create();
    $newMember2 = User::factory()->create();

    $team->users()->attach([$oldMember1->id, $oldMember2->id]);

    actingAs($admin);

    Livewire::test(\App\Livewire\AdminTeams::class)
        ->call('editTeam', $team->id)
        ->set('selectedUserIds', [$newMember1->id, $newMember2->id])
        ->call('save');

    $team->refresh();

    expect($team->users)->toHaveCount(2);
    expect($team->users->pluck('id')->toArray())->toContain($newMember1->id, $newMember2->id);
    expect($team->users->pluck('id')->toArray())->not->toContain($oldMember1->id, $oldMember2->id);
});

test('admin can delete a team without transferring members', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $manager = User::factory()->create();

    $team = Team::factory()->create(['manager_id' => $manager->id]);
    $member1 = User::factory()->create();
    $member2 = User::factory()->create();

    $team->users()->attach([$member1->id, $member2->id]);

    actingAs($admin);

    Livewire::test(\App\Livewire\AdminTeams::class)
        ->call('confirmDelete', $team->id)
        ->assertSet('deletingTeamId', $team->id)
        ->call('deleteTeam');

    expect(Team::find($team->id))->toBeNull();
    expect($team->users()->count())->toBe(0);
});

test('admin can delete a team and transfer members to another team', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $manager = User::factory()->create();

    $teamToDelete = Team::factory()->create(['name' => 'Old Team', 'manager_id' => $manager->id]);
    $targetTeam = Team::factory()->create(['name' => 'New Team', 'manager_id' => $manager->id]);

    $member1 = User::factory()->create();
    $member2 = User::factory()->create();

    $teamToDelete->users()->attach([$member1->id, $member2->id]);

    actingAs($admin);

    Livewire::test(\App\Livewire\AdminTeams::class)
        ->call('confirmDelete', $teamToDelete->id)
        ->set('transferTeamId', $targetTeam->id)
        ->call('deleteTeam');

    expect(Team::find($teamToDelete->id))->toBeNull();

    $targetTeam->refresh();
    expect($targetTeam->users)->toHaveCount(2);
    expect($targetTeam->users->pluck('id')->toArray())->toContain($member1->id, $member2->id);
});

test('validation requires team name', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $manager = User::factory()->create();

    actingAs($admin);

    Livewire::test(\App\Livewire\AdminTeams::class)
        ->call('createTeam')
        ->set('teamName', '')
        ->set('managerId', $manager->id)
        ->call('save')
        ->assertHasErrors(['teamName' => 'required']);
});

test('validation requires manager', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    actingAs($admin);

    Livewire::test(\App\Livewire\AdminTeams::class)
        ->call('createTeam')
        ->set('teamName', 'Test Team')
        ->set('managerId', null)
        ->call('save')
        ->assertHasErrors(['managerId' => 'required']);
});

test('team list shows correct member counts', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $manager = User::factory()->create();

    $team1 = Team::factory()->create(['name' => 'Team One', 'manager_id' => $manager->id]);
    $team2 = Team::factory()->create(['name' => 'Team Two', 'manager_id' => $manager->id]);

    $team1->users()->attach([
        User::factory()->create()->id,
        User::factory()->create()->id,
        User::factory()->create()->id,
    ]);

    $team2->users()->attach([
        User::factory()->create()->id,
    ]);

    actingAs($admin);

    $component = Livewire::test(\App\Livewire\AdminTeams::class);

    $teams = $component->viewData('teams');

    expect($teams->firstWhere('name', 'Team One')->users)->toHaveCount(3);
    expect($teams->firstWhere('name', 'Team Two')->users)->toHaveCount(1);
});

<?php

use App\Enums\AvailabilityStatus;
use App\Models\Location;
use App\Models\PlanEntry;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class)->group('api');

// Authorization Tests

test('unauthenticated request to manager endpoints returns 401', function () {
    $this->getJson('/api/v1/manager/team-members')->assertUnauthorized();
    $this->getJson('/api/v1/manager/team-members/1/plan')->assertUnauthorized();
    $this->postJson('/api/v1/manager/team-members/1/plan')->assertUnauthorized();
    $this->deleteJson('/api/v1/manager/team-members/1/plan/1')->assertUnauthorized();
});

test('token without manage:team-plans ability returns 403', function () {
    $manager = User::factory()->create();
    Team::factory()->create(['manager_id' => $manager->id]);

    // Only view ability, not manage
    Sanctum::actingAs($manager, ['view:team-plans']);

    $this->getJson('/api/v1/manager/team-members')->assertForbidden();
});

test('manager cannot access non-team member plan', function () {
    $manager = User::factory()->create();
    $team = Team::factory()->create(['manager_id' => $manager->id]);

    $nonTeamMember = User::factory()->create();
    // Note: nonTeamMember is NOT attached to the team

    Sanctum::actingAs($manager, ['manage:team-plans']);

    $response = $this->getJson("/api/v1/manager/team-members/{$nonTeamMember->id}/plan");

    $response->assertForbidden();
});

test('manager can access their own team member plan', function () {
    $manager = User::factory()->create();
    $team = Team::factory()->create(['manager_id' => $manager->id]);

    $teamMember = User::factory()->create();
    $team->users()->attach($teamMember);

    Sanctum::actingAs($manager, ['manage:team-plans']);

    $response = $this->getJson("/api/v1/manager/team-members/{$teamMember->id}/plan");

    $response->assertOk();
    $response->assertJsonStructure([
        'user' => ['id', 'name'],
        'date_range' => ['start', 'end'],
        'entries',
    ]);
});

test('admin can access any user plan', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $randomUser = User::factory()->create();

    Sanctum::actingAs($admin, ['manage:team-plans']);

    $response = $this->getJson("/api/v1/manager/team-members/{$randomUser->id}/plan");

    $response->assertOk();
    expect($response->json('user.id'))->toBe($randomUser->id);
});

test('manager can access their own plan via manager endpoint', function () {
    $manager = User::factory()->create();
    Team::factory()->create(['manager_id' => $manager->id]);

    Sanctum::actingAs($manager, ['manage:team-plans']);

    $response = $this->getJson("/api/v1/manager/team-members/{$manager->id}/plan");

    $response->assertOk();
    expect($response->json('user.id'))->toBe($manager->id);
});

// List Team Members Tests

test('manager can list team members', function () {
    $manager = User::factory()->create();
    $team = Team::factory()->create(['manager_id' => $manager->id]);

    $teamMember1 = User::factory()->create(['surname' => 'Adams']);
    $teamMember2 = User::factory()->create(['surname' => 'Brown']);
    $team->users()->attach([$teamMember1->id, $teamMember2->id]);

    Sanctum::actingAs($manager, ['manage:team-plans']);

    $response = $this->getJson('/api/v1/manager/team-members');

    $response->assertOk();
    $response->assertJsonStructure([
        'team_members' => [
            '*' => ['id', 'name', 'email'],
        ],
        'count',
    ]);

    expect($response->json('count'))->toBe(2);
});

test('admin lists all users', function () {
    $admin = User::factory()->create(['is_admin' => true, 'surname' => 'Zebra']);
    User::factory()->count(3)->create();

    Sanctum::actingAs($admin, ['manage:team-plans']);

    $response = $this->getJson('/api/v1/manager/team-members');

    $response->assertOk();
    // Admin + 3 other users = 4 total
    expect($response->json('count'))->toBe(4);
});

// CRUD Tests

test('manager can view team member plan entries', function () {
    $manager = User::factory()->create();
    $team = Team::factory()->create(['manager_id' => $manager->id]);

    $teamMember = User::factory()->create();
    $team->users()->attach($teamMember);

    $location = Location::factory()->create(['slug' => 'jws', 'name' => 'JWS']);

    // Create a plan entry for the team member - use string date for consistent comparison
    $entryDate = now()->startOfWeek()->addDay()->toDateString();
    $entry = PlanEntry::factory()->create([
        'user_id' => $teamMember->id,
        'entry_date' => $entryDate,
        'location_id' => $location->id,
        'note' => 'Working on project',
    ]);

    Sanctum::actingAs($manager, ['manage:team-plans']);

    $response = $this->getJson("/api/v1/manager/team-members/{$teamMember->id}/plan");

    $response->assertOk();

    $entries = $response->json('entries');
    expect($entries)->toHaveCount(1);
    expect($entries[0]['id'])->toBe($entry->id);
    expect($entries[0]['location'])->toBe('jws');
    expect($entries[0]['note'])->toBe('Working on project');
});

test('manager can create entry for team member', function () {
    $manager = User::factory()->create();
    $team = Team::factory()->create(['manager_id' => $manager->id]);

    $teamMember = User::factory()->create();
    $team->users()->attach($teamMember);

    $location = Location::factory()->create(['slug' => 'jws', 'name' => 'JWS']);

    Sanctum::actingAs($manager, ['manage:team-plans']);

    $response = $this->postJson("/api/v1/manager/team-members/{$teamMember->id}/plan", [
        'entries' => [
            [
                'entry_date' => '2025-12-10',
                'location' => 'jws',
                'note' => 'Assigned by manager',
                'availability_status' => AvailabilityStatus::ONSITE->value,
            ],
        ],
    ]);

    $response->assertOk();
    $response->assertJson(['message' => 'Plan entries saved successfully']);

    $entry = $teamMember->planEntries()->first();
    expect($entry)->not->toBeNull();
    expect($entry->location->slug)->toBe('jws');
    expect($entry->note)->toBe('Assigned by manager');
    expect($entry->created_by_manager)->toBeTrue();
});

test('manager can update entry for team member by id', function () {
    $manager = User::factory()->create();
    $team = Team::factory()->create(['manager_id' => $manager->id]);

    $teamMember = User::factory()->create();
    $team->users()->attach($teamMember);

    $locationJws = Location::factory()->create(['slug' => 'jws', 'name' => 'JWS']);
    $locationJwn = Location::factory()->create(['slug' => 'jwn', 'name' => 'JWN']);

    $entry = PlanEntry::factory()->create([
        'user_id' => $teamMember->id,
        'entry_date' => '2025-12-10',
        'location_id' => $locationJws->id,
        'note' => 'Original note',
    ]);

    Sanctum::actingAs($manager, ['manage:team-plans']);

    $response = $this->postJson("/api/v1/manager/team-members/{$teamMember->id}/plan", [
        'entries' => [
            [
                'id' => $entry->id,
                'entry_date' => '2025-12-10',
                'location' => 'jwn',
                'note' => 'Updated by manager',
            ],
        ],
    ]);

    $response->assertOk();

    $entry->refresh();
    expect($entry->location->slug)->toBe('jwn');
    expect($entry->note)->toBe('Updated by manager');
    expect($entry->created_by_manager)->toBeTrue();
});

test('manager can update entry for team member by date', function () {
    $manager = User::factory()->create();
    $team = Team::factory()->create(['manager_id' => $manager->id]);

    $teamMember = User::factory()->create();
    $team->users()->attach($teamMember);

    $locationJws = Location::factory()->create(['slug' => 'jws', 'name' => 'JWS']);
    $locationRankine = Location::factory()->create(['slug' => 'rankine', 'name' => 'Rankine']);

    PlanEntry::factory()->create([
        'user_id' => $teamMember->id,
        'entry_date' => '2025-12-10',
        'location_id' => $locationJws->id,
        'note' => 'Original note',
    ]);

    Sanctum::actingAs($manager, ['manage:team-plans']);

    $response = $this->postJson("/api/v1/manager/team-members/{$teamMember->id}/plan", [
        'entries' => [
            [
                'entry_date' => '2025-12-10',
                'location' => 'rankine',
                'note' => 'Updated via date match',
            ],
        ],
    ]);

    $response->assertOk();

    $entry = $teamMember->planEntries()->whereDate('entry_date', '2025-12-10')->first();
    expect($entry->location->slug)->toBe('rankine');
    expect($entry->note)->toBe('Updated via date match');

    // Should only have one entry for this date
    expect($teamMember->planEntries()->whereDate('entry_date', '2025-12-10')->count())->toBe(1);
});

test('manager can delete entry for team member', function () {
    $manager = User::factory()->create();
    $team = Team::factory()->create(['manager_id' => $manager->id]);

    $teamMember = User::factory()->create();
    $team->users()->attach($teamMember);

    $location = Location::factory()->create(['slug' => 'jws', 'name' => 'JWS']);

    $entry = PlanEntry::factory()->create([
        'user_id' => $teamMember->id,
        'entry_date' => '2025-12-10',
        'location_id' => $location->id,
    ]);

    Sanctum::actingAs($manager, ['manage:team-plans']);

    $response = $this->deleteJson("/api/v1/manager/team-members/{$teamMember->id}/plan/{$entry->id}");

    $response->assertOk();
    $response->assertJson(['message' => 'Plan entry deleted successfully']);

    $this->assertDatabaseMissing('plan_entries', ['id' => $entry->id]);
});

test('created entries have created_by_manager flag set to true', function () {
    $manager = User::factory()->create();
    $team = Team::factory()->create(['manager_id' => $manager->id]);

    $teamMember = User::factory()->create();
    $team->users()->attach($teamMember);

    Location::factory()->create(['slug' => 'jws', 'name' => 'JWS']);

    Sanctum::actingAs($manager, ['manage:team-plans']);

    $this->postJson("/api/v1/manager/team-members/{$teamMember->id}/plan", [
        'entries' => [
            [
                'entry_date' => '2025-12-10',
                'location' => 'jws',
            ],
        ],
    ]);

    $entry = $teamMember->planEntries()->first();
    expect($entry->created_by_manager)->toBeTrue();
});

// Edge Case Tests

test('manager cannot create entry for non-team member', function () {
    $manager = User::factory()->create();
    Team::factory()->create(['manager_id' => $manager->id]);

    $nonTeamMember = User::factory()->create();

    Location::factory()->create(['slug' => 'jws', 'name' => 'JWS']);

    Sanctum::actingAs($manager, ['manage:team-plans']);

    $response = $this->postJson("/api/v1/manager/team-members/{$nonTeamMember->id}/plan", [
        'entries' => [
            [
                'entry_date' => '2025-12-10',
                'location' => 'jws',
            ],
        ],
    ]);

    $response->assertForbidden();

    expect($nonTeamMember->planEntries()->count())->toBe(0);
});

test('manager cannot delete entry for non-team member', function () {
    $manager = User::factory()->create();
    Team::factory()->create(['manager_id' => $manager->id]);

    $nonTeamMember = User::factory()->create();
    $location = Location::factory()->create(['slug' => 'jws', 'name' => 'JWS']);

    $entry = PlanEntry::factory()->create([
        'user_id' => $nonTeamMember->id,
        'entry_date' => '2025-12-10',
        'location_id' => $location->id,
    ]);

    Sanctum::actingAs($manager, ['manage:team-plans']);

    $response = $this->deleteJson("/api/v1/manager/team-members/{$nonTeamMember->id}/plan/{$entry->id}");

    $response->assertForbidden();

    $this->assertDatabaseHas('plan_entries', ['id' => $entry->id]);
});

test('validation rejects invalid location', function () {
    $manager = User::factory()->create();
    $team = Team::factory()->create(['manager_id' => $manager->id]);

    $teamMember = User::factory()->create();
    $team->users()->attach($teamMember);

    Sanctum::actingAs($manager, ['manage:team-plans']);

    $response = $this->postJson("/api/v1/manager/team-members/{$teamMember->id}/plan", [
        'entries' => [
            [
                'entry_date' => '2025-12-10',
                'location' => 'invalid-location',
            ],
        ],
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['entries.0.location']);
});

test('validation rejects entry id belonging to different user', function () {
    $manager = User::factory()->create();
    $team = Team::factory()->create(['manager_id' => $manager->id]);

    $teamMember = User::factory()->create();
    $team->users()->attach($teamMember);

    $otherUser = User::factory()->create();
    $location = Location::factory()->create(['slug' => 'jws', 'name' => 'JWS']);

    $otherEntry = PlanEntry::factory()->create([
        'user_id' => $otherUser->id,
        'entry_date' => '2025-12-10',
        'location_id' => $location->id,
    ]);

    Location::factory()->create(['slug' => 'jwn', 'name' => 'JWN']);

    Sanctum::actingAs($manager, ['manage:team-plans']);

    $response = $this->postJson("/api/v1/manager/team-members/{$teamMember->id}/plan", [
        'entries' => [
            [
                'id' => $otherEntry->id,
                'entry_date' => '2025-12-10',
                'location' => 'jwn',
            ],
        ],
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['entries.0.id']);
});

test('manager can create entries for multiple team members from different teams', function () {
    $manager = User::factory()->create();

    $team1 = Team::factory()->create(['manager_id' => $manager->id]);
    $team2 = Team::factory()->create(['manager_id' => $manager->id]);

    $member1 = User::factory()->create();
    $member2 = User::factory()->create();
    $team1->users()->attach($member1);
    $team2->users()->attach($member2);

    Location::factory()->create(['slug' => 'jws', 'name' => 'JWS']);
    Location::factory()->create(['slug' => 'jwn', 'name' => 'JWN']);

    Sanctum::actingAs($manager, ['manage:team-plans']);

    // Create entry for member in team 1
    $response = $this->postJson("/api/v1/manager/team-members/{$member1->id}/plan", [
        'entries' => [['entry_date' => '2025-12-10', 'location' => 'jws']],
    ]);
    $response->assertOk();

    // Create entry for member in team 2
    $response = $this->postJson("/api/v1/manager/team-members/{$member2->id}/plan", [
        'entries' => [['entry_date' => '2025-12-10', 'location' => 'jwn']],
    ]);
    $response->assertOk();

    expect($member1->planEntries()->count())->toBe(1);
    expect($member2->planEntries()->count())->toBe(1);
});

test('returns 404 for non-existent user', function () {
    $manager = User::factory()->create(['is_admin' => true]);

    Sanctum::actingAs($manager, ['manage:team-plans']);

    $response = $this->getJson('/api/v1/manager/team-members/99999/plan');

    $response->assertNotFound();
});

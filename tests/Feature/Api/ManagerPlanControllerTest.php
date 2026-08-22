<?php

use App\Enums\AvailabilityStatus;
use App\Models\Location;
use App\Models\PlanEntry;
use App\Models\Team;
use App\Models\User;
use Carbon\CarbonImmutable;
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

test('regular staff user cannot access manager endpoints', function () {
    // Non-admin, not managing any team.
    $staff = User::factory()->create(['is_admin' => false]);

    Sanctum::actingAs($staff);

    $this->getJson('/api/v1/manager/team-members')->assertForbidden();
});

test('manager cannot access non-team member plan', function () {
    $manager = User::factory()->create();
    $team = Team::factory()->create(['manager_id' => $manager->id]);

    $nonTeamMember = User::factory()->create();
    // Note: nonTeamMember is NOT attached to the team

    Sanctum::actingAs($manager);

    $response = $this->getJson("/api/v1/manager/team-members/{$nonTeamMember->id}/plan");

    $response->assertForbidden();
});

test('manager can access their own team member plan', function () {
    $manager = User::factory()->create();
    $team = Team::factory()->create(['manager_id' => $manager->id]);

    $teamMember = User::factory()->create();
    $team->users()->attach($teamMember);

    Sanctum::actingAs($manager);

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

    Sanctum::actingAs($admin);

    $response = $this->getJson("/api/v1/manager/team-members/{$randomUser->id}/plan");

    $response->assertOk();
    expect($response->json('user.id'))->toBe($randomUser->id);
});

test('manager can access their own plan via manager endpoint', function () {
    $manager = User::factory()->create();
    Team::factory()->create(['manager_id' => $manager->id]);

    Sanctum::actingAs($manager);

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

    Sanctum::actingAs($manager);

    $response = $this->getJson('/api/v1/manager/team-members');

    $response->assertOk();
    $response->assertJsonStructure([
        'team_members' => [
            '*' => ['id', 'name', 'email'],
        ],
    ]);

    expect($response->json('team_members'))->toHaveCount(2)
        ->and($response->json())->not->toHaveKey('count');
});

test('admin lists all users', function () {
    $admin = User::factory()->create(['is_admin' => true, 'surname' => 'Zebra']);
    User::factory()->count(3)->create();

    Sanctum::actingAs($admin);

    $response = $this->getJson('/api/v1/manager/team-members');

    $response->assertOk();
    // Admin + 3 other users = 4 total
    expect($response->json('team_members'))->toHaveCount(4);
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

    Sanctum::actingAs($manager);

    $response = $this->getJson("/api/v1/manager/team-members/{$teamMember->id}/plan");

    $response->assertOk();

    $entries = $response->json('entries');
    expect($entries)->toHaveCount(1);
    expect($entries[0]['id'])->toBe($entry->id);
    expect($entries[0]['location'])->toBe('jws');
    expect($entries[0]['note'])->toBe('Working on project');
});

test('manager API accepts not-available entry without a location', function () {
    $manager = User::factory()->create();
    $team = Team::factory()->create(['manager_id' => $manager->id]);

    $teamMember = User::factory()->create();
    $team->users()->attach($teamMember);

    Sanctum::actingAs($manager);

    $response = $this->postJson("/api/v1/manager/team-members/{$teamMember->id}/plan", [
        'entries' => [
            [
                'entry_date' => '2025-12-10',
                'availability_status' => AvailabilityStatus::NOT_AVAILABLE->value,
                'note' => 'Annual leave',
            ],
        ],
    ]);

    $response->assertOk();

    $entry = $teamMember->planEntries()->first();
    expect($entry)->not->toBeNull();
    expect($entry->location_id)->toBeNull();
    expect($entry->availability_status)->toBe(AvailabilityStatus::NOT_AVAILABLE);
    expect($entry->created_by_manager)->toBeTrue();
});

test('manager can create entry for team member', function () {
    $manager = User::factory()->create();
    $team = Team::factory()->create(['manager_id' => $manager->id]);

    $teamMember = User::factory()->create();
    $team->users()->attach($teamMember);

    $location = Location::factory()->create(['slug' => 'jws', 'name' => 'JWS']);

    Sanctum::actingAs($manager);

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

    Sanctum::actingAs($manager);

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

    Sanctum::actingAs($manager);

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

    Sanctum::actingAs($manager);

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

    Sanctum::actingAs($manager);

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

    Sanctum::actingAs($manager);

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

    Sanctum::actingAs($manager);

    $response = $this->deleteJson("/api/v1/manager/team-members/{$nonTeamMember->id}/plan/{$entry->id}");

    $response->assertForbidden();

    $this->assertDatabaseHas('plan_entries', ['id' => $entry->id]);
});

test('validation rejects invalid location', function () {
    $manager = User::factory()->create();
    $team = Team::factory()->create(['manager_id' => $manager->id]);

    $teamMember = User::factory()->create();
    $team->users()->attach($teamMember);

    Sanctum::actingAs($manager);

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

    Sanctum::actingAs($manager);

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

    Sanctum::actingAs($manager);

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

test('manager show endpoint accepts filter[from]/filter[to] to widen the date window', function () {
    $this->travelTo(CarbonImmutable::parse('2026-04-20'));

    $manager = User::factory()->create();
    $team = Team::factory()->create(['manager_id' => $manager->id]);
    $teamMember = User::factory()->create();
    $team->users()->attach($teamMember);

    $location = Location::factory()->create(['slug' => 'rankine']);
    $teamMember->planEntries()->create([
        'entry_date' => '2026-05-04', // outside the default 10-weekday window
        'location_id' => $location->id,
    ]);

    Sanctum::actingAs($manager);

    $response = $this->getJson("/api/v1/manager/team-members/{$teamMember->id}/plan?filter[from]=2026-05-04&filter[to]=2026-05-08");

    $response->assertOk();
    expect($response->json('date_range'))->toBe(['start' => '2026-05-04', 'end' => '2026-05-08']);

    $dates = collect($response->json('entries'))->pluck('entry_date')->all();
    expect($dates)->toBe(['2026-05-04']);
});

test('manager show endpoint rejects mismatched filter[from]/filter[to] with a 400', function () {
    $manager = User::factory()->create();
    $team = Team::factory()->create(['manager_id' => $manager->id]);
    $teamMember = User::factory()->create();
    $team->users()->attach($teamMember);

    Sanctum::actingAs($manager);

    $response = $this->getJson("/api/v1/manager/team-members/{$teamMember->id}/plan?filter[from]=2026-05-04");

    $response->assertStatus(400);
});

test('returns 404 for non-existent user', function () {
    $manager = User::factory()->create(['is_admin' => true]);

    Sanctum::actingAs($manager);

    $response = $this->getJson('/api/v1/manager/team-members/99999/plan');

    $response->assertNotFound();
});

// Fill From Defaults Tests

test('manager can fill a team members plan from their defaults', function () {
    $location = Location::factory()->create(['slug' => 'other', 'name' => 'Other']);
    $manager = User::factory()->create();
    $team = Team::factory()->create(['manager_id' => $manager->id]);
    $teamMember = User::factory()->create(['default_location_id' => $location->id]);
    $team->users()->attach($teamMember);

    Sanctum::actingAs($manager);

    $weekStart = CarbonImmutable::now()->startOfWeek();

    $response = $this->postJson("/api/v1/manager/team-members/{$teamMember->id}/plan/fill-defaults", [
        'week_start' => $weekStart->toDateString(),
    ]);

    $response->assertOk();
    $response->assertJson([
        'filled_days' => 10,
        'window' => [
            'from' => $weekStart->toDateString(),
            'to' => $weekStart->addDays(11)->toDateString(),
        ],
        'user' => ['email' => $teamMember->email],
    ]);

    expect($teamMember->planEntries()->count())->toBe(10);
    expect($teamMember->planEntries()->first()->created_by_manager)->toBeTrue();
    expect($teamMember->planEntries()->first()->location_id)->toBe($location->id);
});

test('manager can fill their own plan and entries are not marked as manager-created', function () {
    $location = Location::factory()->create(['slug' => 'other', 'name' => 'Other']);
    $manager = User::factory()->create(['default_location_id' => $location->id]);
    Team::factory()->create(['manager_id' => $manager->id]);

    Sanctum::actingAs($manager);

    $response = $this->postJson("/api/v1/manager/team-members/{$manager->id}/plan/fill-defaults", [
        'week_start' => CarbonImmutable::now()->startOfWeek()->toDateString(),
    ]);

    $response->assertOk();
    $response->assertJson(['filled_days' => 10]);

    expect($manager->planEntries()->count())->toBe(10);
    expect($manager->planEntries()->first()->created_by_manager)->toBeFalse();
});

test('fill leaves existing entries untouched and a second call fills nothing', function () {
    $location = Location::factory()->create(['slug' => 'other', 'name' => 'Other']);
    $manager = User::factory()->create();
    $team = Team::factory()->create(['manager_id' => $manager->id]);
    $teamMember = User::factory()->create(['default_location_id' => $location->id]);
    $team->users()->attach($teamMember);

    $weekStart = CarbonImmutable::now()->startOfWeek();
    $existing = PlanEntry::factory()->create([
        'user_id' => $teamMember->id,
        'entry_date' => $weekStart,
        'note' => 'Hand-written plan',
        'location_id' => $location->id,
        'availability_status' => AvailabilityStatus::REMOTE,
    ]);

    Sanctum::actingAs($manager);

    $firstResponse = $this->postJson("/api/v1/manager/team-members/{$teamMember->id}/plan/fill-defaults", [
        'week_start' => $weekStart->toDateString(),
    ]);

    $firstResponse->assertOk();
    $firstResponse->assertJson(['filled_days' => 9]);

    expect($teamMember->planEntries()->count())->toBe(10);
    expect($existing->fresh()->note)->toBe('Hand-written plan');
    expect($existing->fresh()->availability_status)->toBe(AvailabilityStatus::REMOTE);

    $secondResponse = $this->postJson("/api/v1/manager/team-members/{$teamMember->id}/plan/fill-defaults", [
        'week_start' => $weekStart->toDateString(),
    ]);

    $secondResponse->assertOk();
    $secondResponse->assertJson(['filled_days' => 0]);
    expect($teamMember->planEntries()->count())->toBe(10);
});

test('filling a user with no usable defaults returns 200 with a skipped reason', function () {
    $manager = User::factory()->create();
    $team = Team::factory()->create(['manager_id' => $manager->id]);
    $memberWithoutDefaults = User::factory()->create(['default_location_id' => null]);
    $team->users()->attach($memberWithoutDefaults);

    Sanctum::actingAs($manager);

    $response = $this->postJson("/api/v1/manager/team-members/{$memberWithoutDefaults->id}/plan/fill-defaults", [
        'week_start' => CarbonImmutable::now()->startOfWeek()->toDateString(),
    ]);

    $response->assertOk();
    $response->assertJson([
        'filled_days' => 0,
        'skipped_reason' => 'no_defaults',
    ]);

    expect($memberWithoutDefaults->planEntries()->count())->toBe(0);
});

test('fill-defaults is blocked for non-team members and non-managers', function () {
    $location = Location::factory()->create(['slug' => 'other', 'name' => 'Other']);
    $manager = User::factory()->create();
    Team::factory()->create(['manager_id' => $manager->id]);
    $nonTeamMember = User::factory()->create(['default_location_id' => $location->id]);

    Sanctum::actingAs($manager);

    $this->postJson("/api/v1/manager/team-members/{$nonTeamMember->id}/plan/fill-defaults", [
        'week_start' => CarbonImmutable::now()->startOfWeek()->toDateString(),
    ])->assertForbidden();

    $staff = User::factory()->create(['is_admin' => false]);
    Sanctum::actingAs($staff);

    $this->postJson("/api/v1/manager/team-members/{$nonTeamMember->id}/plan/fill-defaults", [
        'week_start' => CarbonImmutable::now()->startOfWeek()->toDateString(),
    ])->assertForbidden();

    expect($nonTeamMember->planEntries()->count())->toBe(0);
});

test('only_date fills exactly that day and reports 0 for planned days or weekends', function () {
    $location = Location::factory()->create(['slug' => 'other', 'name' => 'Other']);
    $manager = User::factory()->create();
    $team = Team::factory()->create(['manager_id' => $manager->id]);
    $teamMember = User::factory()->create(['default_location_id' => $location->id]);
    $team->users()->attach($teamMember);

    Sanctum::actingAs($manager);

    $weekStart = CarbonImmutable::now()->startOfWeek();

    $this->postJson("/api/v1/manager/team-members/{$teamMember->id}/plan/fill-defaults", [
        'week_start' => $weekStart->toDateString(),
        'only_date' => $weekStart->addDays(1)->toDateString(),
    ])
        ->assertOk()
        ->assertJson([
            'filled_days' => 1,
            'window' => [
                'from' => $weekStart->addDays(1)->toDateString(),
                'to' => $weekStart->addDays(1)->toDateString(),
            ],
        ]);

    expect($teamMember->planEntries()->count())->toBe(1);
    expect($teamMember->planEntries()->first()->entry_date->format('Y-m-d'))->toBe($weekStart->addDays(1)->toDateString());

    // Same day again: already planned, nothing filled.
    $this->postJson("/api/v1/manager/team-members/{$teamMember->id}/plan/fill-defaults", [
        'week_start' => $weekStart->toDateString(),
        'only_date' => $weekStart->addDays(1)->toDateString(),
    ])
        ->assertOk()
        ->assertJson(['filled_days' => 0]);

    // A weekend day: nothing filled.
    $this->postJson("/api/v1/manager/team-members/{$teamMember->id}/plan/fill-defaults", [
        'week_start' => $weekStart->toDateString(),
        'only_date' => $weekStart->addDays(5)->toDateString(),
    ])
        ->assertOk()
        ->assertJson(['filled_days' => 0]);

    expect($teamMember->planEntries()->count())->toBe(1);
});

test('fill-defaults rejects a missing or invalid week_start with a 422', function () {
    $manager = User::factory()->create();
    $team = Team::factory()->create(['manager_id' => $manager->id]);
    $teamMember = User::factory()->create();
    $team->users()->attach($teamMember);

    Sanctum::actingAs($manager);

    $this->postJson("/api/v1/manager/team-members/{$teamMember->id}/plan/fill-defaults", [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['week_start']);

    $this->postJson("/api/v1/manager/team-members/{$teamMember->id}/plan/fill-defaults", [
        'week_start' => 'not-a-date',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['week_start']);

    expect($teamMember->planEntries()->count())->toBe(0);
});

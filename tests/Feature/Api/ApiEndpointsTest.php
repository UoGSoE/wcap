<?php

use App\Enums\AvailabilityStatus;
use App\Models\Location;
use App\Models\PlanEntry;
use App\Models\Service;
use App\Models\Team;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class)->group('api');

//  Personal Plan Endpoint Tests

test('unauthenticated request to plan endpoint returns 401', function () {
    $response = $this->getJson('/api/v1/plan');

    $response->assertUnauthorized();
});

test('staff user with token can access plan endpoint', function () {
    $user = User::factory()->create();

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/v1/plan');

    $response->assertOk();
    $response->assertJsonStructure([
        'user' => ['id', 'name'],
        'date_range' => ['start', 'end'],
        'entries',
    ]);
});

// Report Endpoint Tests - Basic Structure

test('regular staff user cannot access reports endpoints', function () {
    $staff = User::factory()->create(['is_admin' => false]);

    Sanctum::actingAs($staff);

    $this->getJson('/api/v1/reports/team')->assertForbidden();
    $this->getJson('/api/v1/reports/location')->assertForbidden();
    $this->getJson('/api/v1/reports/coverage')->assertForbidden();
});

test('manager report scopes visible users to the manager own team, not everyone', function () {
    $this->travelTo(now()->startOfWeek());

    $manager = User::factory()->create();
    $team = Team::factory()->create(['manager_id' => $manager->id]);
    $ourMember = User::factory()->create(['surname' => 'Ours']);
    $team->users()->attach($ourMember);

    $someoneElsesTeam = Team::factory()->create();
    $theirMember = User::factory()->create(['surname' => 'Theirs']);
    $someoneElsesTeam->users()->attach($theirMember);

    Sanctum::actingAs($manager);

    $response = $this->getJson('/api/v1/reports/team');

    $response->assertOk();

    $memberIds = collect($response->json('team_rows'))->pluck('member_id')->all();
    expect($memberIds)->toContain($ourMember->id)
        ->and($memberIds)->not->toContain($theirMember->id);
});

test('manager with a plain sanctum token (no abilities) can access team report', function () {
    $manager = User::factory()->create();
    Team::factory()->create(['manager_id' => $manager->id]);

    Sanctum::actingAs($manager); // no abilities

    $response = $this->getJson('/api/v1/reports/team');

    $response->assertOk();
});

test('manager can access team report endpoint', function () {
    $manager = User::factory()->create();
    Team::factory()->create(['manager_id' => $manager->id]);

    Sanctum::actingAs($manager);

    $response = $this->getJson('/api/v1/reports/team');

    $response->assertOk();
    $response->assertJsonStructure([
        'scope',
        'days',
        'team_rows',
    ]);
    expect($response->json('scope'))->toBe('team');
});

test('admin can access all report endpoints', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    Sanctum::actingAs($admin);

    // Team report
    $response = $this->getJson('/api/v1/reports/team');
    $response->assertOk();
    expect($response->json('scope'))->toBe('all');

    // Location report
    $response = $this->getJson('/api/v1/reports/location');
    $response->assertOk();

    // Coverage report
    $response = $this->getJson('/api/v1/reports/coverage');
    $response->assertOk();

    // Service availability report (only if services enabled)
    if (config('wcap.services_enabled')) {
        $response = $this->getJson('/api/v1/reports/service-availability');
        $response->assertOk();
    }
});

test('coverage report golden master — empty scenario, Monday 2026-04-20', function () {
    $this->travelTo(CarbonImmutable::parse('2026-04-20 09:00:00'));

    $admin = User::factory()->create(['is_admin' => true]);
    Location::factory()->create([
        'slug' => 'rankine',
        'name' => 'Rankine',
        'short_label' => 'Rank',
        'is_physical' => true,
    ]);

    Sanctum::actingAs($admin);

    $response = $this->getJson('/api/v1/reports/coverage');
    $response->assertOk();

    $fixture = base_path('tests/fixtures/coverage-report-empty.json');
    if (! file_exists($fixture)) {
        file_put_contents($fixture, json_encode($response->json(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
    }
    $response->assertExactJson(json_decode(file_get_contents($fixture), true));
});

test('plan endpoint serialises entry_date as YYYY-MM-DD', function () {
    $this->travelTo(now()->startOfWeek());

    $user = User::factory()->create();
    $location = Location::factory()->create(['slug' => 'rankine']);
    $user->planEntries()->create([
        'location_id' => $location->id,
        'entry_date' => now()->format('Y-m-d'),
    ]);

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/v1/plan');

    $response->assertOk();

    foreach ($response->json('entries') as $entry) {
        expect($entry['entry_date'])->toMatch('/^\d{4}-\d{2}-\d{2}$/');
    }

    expect($response->json('date_range.start'))->toMatch('/^\d{4}-\d{2}-\d{2}$/')
        ->and($response->json('date_range.end'))->toMatch('/^\d{4}-\d{2}-\d{2}$/');
});

test('coverage report serialises all date fields as YYYY-MM-DD', function () {
    $this->travelTo(now()->startOfWeek());

    $admin = User::factory()->create(['is_admin' => true]);
    Location::factory()->create(['slug' => 'rankine']);

    Sanctum::actingAs($admin);

    $response = $this->getJson('/api/v1/reports/coverage');

    $response->assertOk();

    foreach ($response->json('days') as $day) {
        expect($day['date'])->toMatch('/^\d{4}-\d{2}-\d{2}$/');
    }

    foreach ($response->json('coverage_matrix') as $row) {
        foreach ($row['entries'] as $entry) {
            expect($entry['date'])->toMatch('/^\d{4}-\d{2}-\d{2}$/');
        }
    }
});

test('location report serialises all date fields as YYYY-MM-DD', function () {
    $this->travelTo(now()->startOfWeek());

    $admin = User::factory()->create(['is_admin' => true]);
    Location::factory()->create(['slug' => 'rankine']);

    Sanctum::actingAs($admin);

    $response = $this->getJson('/api/v1/reports/location');

    $response->assertOk();

    foreach ($response->json('location_days') as $day) {
        expect($day['date'])->toMatch('/^\d{4}-\d{2}-\d{2}$/');
    }
});

test('team report serialises all date fields as YYYY-MM-DD', function () {
    $this->travelTo(now()->startOfWeek());

    $admin = User::factory()->create(['is_admin' => true]);
    $location = Location::factory()->create(['slug' => 'rankine']);
    $member = User::factory()->create(['surname' => 'Dateholder']);
    $member->planEntries()->create([
        'location_id' => $location->id,
        'entry_date' => now()->format('Y-m-d'),
    ]);

    Sanctum::actingAs($admin);

    $response = $this->getJson('/api/v1/reports/team');

    $response->assertOk();

    foreach ($response->json('days') as $day) {
        expect($day['date'])->toMatch('/^\d{4}-\d{2}-\d{2}$/');
    }

    $memberRow = collect($response->json('team_rows'))->firstWhere('member_id', $member->id);
    foreach ($memberRow['days'] as $day) {
        expect($day['date'])->toMatch('/^\d{4}-\d{2}-\d{2}$/');
    }
});

test('plan endpoint filter[from] and filter[to] narrow the window and return only matching entries', function () {
    $this->travelTo(CarbonImmutable::parse('2026-04-20'));

    $user = User::factory()->create();
    $location = Location::factory()->create(['slug' => 'rankine']);

    $user->planEntries()->create([
        'location_id' => $location->id,
        'entry_date' => '2026-04-21',
    ]);
    $user->planEntries()->create([
        'location_id' => $location->id,
        'entry_date' => '2026-04-28', // outside the requested window
    ]);

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/v1/plan?filter[from]=2026-04-20&filter[to]=2026-04-24');

    $response->assertOk();

    expect($response->json('date_range'))->toBe(['start' => '2026-04-20', 'end' => '2026-04-24']);

    $dates = collect($response->json('entries'))->pluck('entry_date')->all();
    expect($dates)->toBe(['2026-04-21']);
});

test('team report rejects invalid filter[from] / filter[to] values', function (array $query) {
    $admin = User::factory()->create(['is_admin' => true]);
    Sanctum::actingAs($admin);

    $response = $this->getJson('/api/v1/reports/team?'.http_build_query($query));

    $response->assertStatus(400);
})->with([
    'only from' => [['filter' => ['from' => '2026-04-20']]],
    'only to' => [['filter' => ['to' => '2026-04-24']]],
    'malformed from' => [['filter' => ['from' => 'not-a-date', 'to' => '2026-04-24']]],
    'from after to' => [['filter' => ['from' => '2026-04-24', 'to' => '2026-04-20']]],
    'window too large' => [['filter' => ['from' => '2026-01-01', 'to' => '2026-12-31']]],
]);

test('service-availability response does not include the scope field (service matrix is not role-scoped)', function () {
    config(['wcap.services_enabled' => true]);

    $admin = User::factory()->create(['is_admin' => true]);
    Sanctum::actingAs($admin);

    $response = $this->getJson('/api/v1/reports/service-availability');

    $response->assertOk();
    expect($response->json())->not->toHaveKey('scope');
});

test('service-availability filter[manager_only]=true returns only services with a manager-only day', function () {
    config(['wcap.services_enabled' => true]);
    $this->travelTo(CarbonImmutable::parse('2026-04-20'));

    $admin = User::factory()->create(['is_admin' => true]);

    // "At risk" service: has a manager, but the only available person that day is the manager.
    $manager = User::factory()->create();
    $member = User::factory()->create();
    $atRisk = Service::factory()->create(['name' => 'At Risk Service', 'manager_id' => $manager->id]);
    $atRisk->users()->attach($member);

    $manager->planEntries()->create(['entry_date' => '2026-04-20', 'location_id' => null, 'availability_status' => AvailabilityStatus::ONSITE]);
    // member is not_available that day (no entry → no availability)

    // "Safe" service: member available, so no manager_only flag.
    $safeMember = User::factory()->create();
    $location = Location::factory()->create();
    $safe = Service::factory()->create(['name' => 'Safe Service']);
    $safe->users()->attach($safeMember);
    $safeMember->planEntries()->create([
        'entry_date' => '2026-04-20',
        'location_id' => $location->id,
        'availability_status' => AvailabilityStatus::ONSITE,
    ]);

    Sanctum::actingAs($admin);

    $response = $this->getJson('/api/v1/reports/service-availability?filter[from]=2026-04-20&filter[to]=2026-04-20&filter[manager_only]=true');

    $response->assertOk();

    $names = collect($response->json('service_availability_matrix'))->pluck('service')->all();
    expect($names)->toContain('At Risk Service')
        ->and($names)->not->toContain('Safe Service');
});

test('service-availability filter[service_slug] narrows to one service', function () {
    config(['wcap.services_enabled' => true]);

    $admin = User::factory()->create(['is_admin' => true]);
    Service::factory()->create(['name' => 'VPN Service']);
    Service::factory()->create(['name' => 'Email Service']);

    Sanctum::actingAs($admin);

    $response = $this->getJson('/api/v1/reports/service-availability?filter[service_slug]=vpn-service');

    $response->assertOk();

    $names = collect($response->json('service_availability_matrix'))->pluck('service')->all();
    expect($names)->toBe(['VPN Service']);
});

test('service-availability report rejects unknown filter with a Spatie-style message listing allowed filters', function () {
    config(['wcap.services_enabled' => true]);

    $admin = User::factory()->create(['is_admin' => true]);
    Sanctum::actingAs($admin);

    $response = $this->getJson('/api/v1/reports/service-availability?filter[wibble]=foo');

    $response->assertStatus(400);
    $body = $response->json();
    expect($body['message'] ?? '')->toContain('wibble')
        ->and($body['message'] ?? '')->toContain('from')
        ->and($body['message'] ?? '')->toContain('to');
});

test('service-availability report filter[from] and filter[to] narrow the date window', function () {
    config(['wcap.services_enabled' => true]);
    $this->travelTo(CarbonImmutable::parse('2026-04-20'));

    $admin = User::factory()->create(['is_admin' => true]);
    Sanctum::actingAs($admin);

    $response = $this->getJson('/api/v1/reports/service-availability?filter[from]=2026-04-20&filter[to]=2026-04-22');

    $response->assertOk();

    $dates = collect($response->json('days'))->pluck('date')->all();
    expect($dates)->toBe(['2026-04-20', '2026-04-21', '2026-04-22']);
});

test('team report filter[from] and filter[to] narrow the date window', function () {
    $this->travelTo(CarbonImmutable::parse('2026-04-20'));

    $admin = User::factory()->create(['is_admin' => true]);
    Sanctum::actingAs($admin);

    $response = $this->getJson('/api/v1/reports/team?filter[from]=2026-04-20&filter[to]=2026-04-22');

    $response->assertOk();

    $dates = collect($response->json('days'))->pluck('date')->all();
    expect($dates)->toBe(['2026-04-20', '2026-04-21', '2026-04-22']);
});

test('team report filter[location_slug] returns only rows for users at that location', function () {
    $this->travelTo(now()->startOfWeek());

    $admin = User::factory()->create(['is_admin' => true]);
    $rankine = Location::factory()->create(['slug' => 'rankine', 'name' => 'Rankine']);
    $jws = Location::factory()->create(['slug' => 'jws', 'name' => 'James Watt South']);

    $userAtRankine = User::factory()->create(['surname' => 'Rankinefan']);
    $userElsewhere = User::factory()->create(['surname' => 'Elsewhereguy']);

    PlanEntry::factory()->create([
        'user_id' => $userAtRankine->id,
        'location_id' => $rankine->id,
        'entry_date' => now()->toDateString(),
    ]);
    PlanEntry::factory()->create([
        'user_id' => $userElsewhere->id,
        'location_id' => $jws->id,
        'entry_date' => now()->toDateString(),
    ]);

    Sanctum::actingAs($admin);

    $response = $this->getJson('/api/v1/reports/team?filter[location_slug]=rankine');

    $response->assertOk();

    $memberIds = collect($response->json('team_rows'))->pluck('member_id')->all();
    expect($memberIds)->toContain($userAtRankine->id)
        ->and($memberIds)->not->toContain($userElsewhere->id);
});

test('team report filter[state] returns only rows for users with matching entries', function () {
    $this->travelTo(now()->startOfWeek());

    $admin = User::factory()->create(['is_admin' => true]);
    $location = Location::factory()->create(['slug' => 'rankine', 'name' => 'Rankine']);

    $plannedUser = User::factory()->create(['surname' => 'Plannedone']);
    $awayUser = User::factory()->create(['surname' => 'Awayone']);

    PlanEntry::factory()->create([
        'user_id' => $plannedUser->id,
        'location_id' => $location->id,
        'entry_date' => now()->toDateString(),
        'availability_status' => AvailabilityStatus::ONSITE,
    ]);
    PlanEntry::factory()->unavailable()->create([
        'user_id' => $awayUser->id,
        'entry_date' => now()->toDateString(),
    ]);

    Sanctum::actingAs($admin);

    $response = $this->getJson('/api/v1/reports/team?filter[state]=planned');

    $response->assertOk();

    $memberIds = collect($response->json('team_rows'))->pluck('member_id')->all();
    expect($memberIds)->toContain($plannedUser->id)
        ->and($memberIds)->not->toContain($awayUser->id);
});

test('location report filter[is_physical] excludes non-physical locations', function () {
    $this->travelTo(now()->startOfWeek());

    $admin = User::factory()->create(['is_admin' => true]);
    $rankine = Location::factory()->create(['slug' => 'rankine', 'name' => 'Rankine', 'is_physical' => true]);
    $remote = Location::factory()->create(['slug' => 'remote', 'name' => 'Remote', 'is_physical' => false]);

    Sanctum::actingAs($admin);

    $response = $this->getJson('/api/v1/reports/location?filter[is_physical]=true');

    $response->assertOk();

    foreach ($response->json('location_days') as $day) {
        expect(array_keys($day['locations']))
            ->toContain($rankine->id)
            ->not->toContain($remote->id);
    }
});

test('location report filter[location_slug] narrows each day to only that location', function () {
    $this->travelTo(now()->startOfWeek());

    $admin = User::factory()->create(['is_admin' => true]);
    $rankine = Location::factory()->create(['slug' => 'rankine', 'name' => 'Rankine']);
    $jws = Location::factory()->create(['slug' => 'jws', 'name' => 'James Watt South']);

    Sanctum::actingAs($admin);

    $response = $this->getJson('/api/v1/reports/location?filter[location_slug]=rankine');

    $response->assertOk();

    foreach ($response->json('location_days') as $day) {
        expect(array_keys($day['locations']))
            ->toContain($rankine->id)
            ->not->toContain($jws->id);
    }
});

test('plan endpoint fields[entries] returns only the requested keys per entry', function () {
    $this->travelTo(now()->startOfWeek());

    $user = User::factory()->create();
    $rankine = Location::factory()->create(['slug' => 'rankine', 'name' => 'Rankine']);

    $user->planEntries()->create([
        'location_id' => $rankine->id,
        'entry_date' => now()->format('Y-m-d'),
    ]);

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/v1/plan?fields[entries]=entry_date,location,note');

    $response->assertOk();

    $entries = $response->json('entries');
    expect($entries)->toHaveCount(1)
        ->and(array_keys($entries[0]))
        ->toEqualCanonicalizing(['entry_date', 'location', 'note']);
});

test('plan endpoint rejects unknown fields[entries] with a 4xx', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = $this->getJson('/api/v1/plan?fields[entries]=note,not_a_real_field');

    $response->assertStatus(400);
});

test('plan endpoint filter[location_slug] returns only entries at that location', function () {
    $this->travelTo(now()->startOfWeek());

    $user = User::factory()->create();
    $rankine = Location::factory()->create(['slug' => 'rankine', 'name' => 'Rankine']);
    $jws = Location::factory()->create(['slug' => 'jws', 'name' => 'James Watt South']);

    $user->planEntries()->create([
        'location_id' => $rankine->id,
        'entry_date' => now()->format('Y-m-d'),
    ]);
    $user->planEntries()->create([
        'location_id' => $jws->id,
        'entry_date' => now()->addDay()->format('Y-m-d'),
    ]);

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/v1/plan?filter[location_slug]=rankine');

    $response->assertOk();

    $slugs = collect($response->json('entries'))->pluck('location')->all();
    expect($slugs)->toBe(['rankine']);
});

test('coverage report filter[location_slug] returns only that locations coverage row', function () {
    $this->travelTo(now()->startOfWeek());

    $admin = User::factory()->create(['is_admin' => true]);
    $rankine = Location::factory()->create(['slug' => 'rankine', 'name' => 'Rankine']);
    Location::factory()->create(['slug' => 'jws', 'name' => 'James Watt South']);

    Sanctum::actingAs($admin);

    $response = $this->getJson('/api/v1/reports/coverage?filter[location_slug]=rankine');

    $response->assertOk();

    $labels = collect($response->json('coverage_matrix'))->pluck('location')->all();
    expect($labels)->toBe([$rankine->name]);
});

test('team report rejects unknown filter with a 4xx', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    Sanctum::actingAs($admin);

    $response = $this->getJson('/api/v1/reports/team?filter[not_a_real_filter]=anything');

    expect($response->status())->toBeGreaterThanOrEqual(400)
        ->and($response->status())->toBeLessThan(500);
});

// CRUD Operations Tests

test('user can create a new plan entry via API', function () {
    $user = User::factory()->create();
    $location = Location::factory()->create(['slug' => 'jws', 'name' => 'James Watt South']);

    Sanctum::actingAs($user);

    $response = $this->postJson('/api/v1/plan', [
        'entries' => [
            [
                'entry_date' => '2025-11-10',
                'location' => 'jws',
                'note' => 'API testing',
                'availability_status' => AvailabilityStatus::ONSITE->value,
                'is_holiday' => false,
            ],
        ],
    ]);

    $response->assertOk();
    $response->assertJson(['message' => 'Plan entries saved successfully']);

    $entry = $user->planEntries()->first();
    expect($entry)->not->toBeNull();
    expect($entry->location->slug)->toBe('jws');
    expect($entry->note)->toBe('API testing');
    expect($entry->availability_status)->toBe(AvailabilityStatus::ONSITE);
    expect($entry->is_holiday)->toBeFalse();
});

test('user can create multiple plan entries in batch via API', function () {
    $user = User::factory()->create();
    $locationJws = Location::factory()->create(['slug' => 'jws', 'name' => 'James Watt South']);
    $locationJwn = Location::factory()->create(['slug' => 'jwn', 'name' => 'James Watt North']);

    Sanctum::actingAs($user);

    $response = $this->postJson('/api/v1/plan', [
        'entries' => [
            [
                'entry_date' => '2025-11-10',
                'location' => 'jws',
                'note' => 'Day 1',
            ],
            [
                'entry_date' => '2025-11-11',
                'location' => 'jwn',
                'note' => 'Day 2',
            ],
        ],
    ]);

    $response->assertOk();
    $response->assertJson(['message' => 'Plan entries saved successfully']);

    $entries = $user->planEntries()->get();
    expect($entries)->toHaveCount(2);

    $entry1 = $entries->where('location_id', $locationJws->id)->first();
    expect($entry1)->not->toBeNull();
    expect($entry1->note)->toBe('Day 1');

    $entry2 = $entries->where('location_id', $locationJwn->id)->first();
    expect($entry2)->not->toBeNull();
    expect($entry2->note)->toBe('Day 2');
});

test('user can update plan entry by id via API', function () {
    $user = User::factory()->create();
    $locationJws = Location::factory()->create(['slug' => 'jws', 'name' => 'James Watt South']);
    $locationJwn = Location::factory()->create(['slug' => 'jwn', 'name' => 'James Watt North']);

    $entry = $user->planEntries()->create([
        'entry_date' => '2025-11-10',
        'location_id' => $locationJws->id,
        'note' => 'Original note',
    ]);

    Sanctum::actingAs($user);

    $response = $this->postJson('/api/v1/plan', [
        'entries' => [
            [
                'id' => $entry->id,
                'entry_date' => '2025-11-10',
                'location' => 'jwn',
                'note' => 'Updated note',
            ],
        ],
    ]);

    $response->assertOk();
    $response->assertJson(['message' => 'Plan entries saved successfully']);

    $entry->refresh();
    expect($entry->location->slug)->toBe('jwn');
    expect($entry->note)->toBe('Updated note');
});

test('user can update plan entry by entry_date via API', function () {
    $user = User::factory()->create();
    $locationJws = Location::factory()->create(['slug' => 'jws', 'name' => 'James Watt South']);
    $locationRankine = Location::factory()->create(['slug' => 'rankine', 'name' => 'Rankine']);

    $user->planEntries()->create([
        'entry_date' => '2025-11-10',
        'location_id' => $locationJws->id,
        'note' => 'Original note',
    ]);

    Sanctum::actingAs($user);

    $response = $this->postJson('/api/v1/plan', [
        'entries' => [
            [
                'entry_date' => '2025-11-10',
                'location' => 'rankine',
                'note' => 'Updated via date',
            ],
        ],
    ]);

    $response->assertOk();
    $response->assertJson(['message' => 'Plan entries saved successfully']);

    $entry = $user->planEntries()->whereDate('entry_date', '2025-11-10')->first();
    expect($entry)->not->toBeNull();
    expect($entry->location->slug)->toBe('rankine');
    expect($entry->note)->toBe('Updated via date');

    // Should only have one entry for this date
    expect($user->planEntries()->whereDate('entry_date', '2025-11-10')->count())->toBe(1);
});

test('user cannot update another users plan entry', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $location = Location::factory()->create(['slug' => 'jws', 'name' => 'James Watt South']);

    $otherEntry = $otherUser->planEntries()->create([
        'entry_date' => '2025-11-10',
        'location_id' => $location->id,
        'note' => 'Other user entry',
    ]);

    Sanctum::actingAs($user);

    $response = $this->postJson('/api/v1/plan', [
        'entries' => [
            [
                'id' => $otherEntry->id,
                'entry_date' => '2025-11-10',
                'location' => 'jws',
                'note' => 'Trying to hack',
            ],
        ],
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['entries.0.id']);

    // Original entry should be unchanged
    $this->assertDatabaseHas('plan_entries', [
        'id' => $otherEntry->id,
        'location_id' => $location->id,
        'note' => 'Other user entry',
    ]);
});

test('user can delete their own plan entry via API', function () {
    $user = User::factory()->create();
    $location = Location::factory()->create(['slug' => 'jws', 'name' => 'James Watt South']);

    $entry = $user->planEntries()->create([
        'entry_date' => '2025-11-10',
        'location_id' => $location->id,
        'note' => 'To be deleted',
    ]);

    Sanctum::actingAs($user);

    $response = $this->deleteJson("/api/v1/plan/{$entry->id}");

    $response->assertOk();
    $response->assertJson(['message' => 'Plan entry deleted successfully']);

    $this->assertDatabaseMissing('plan_entries', [
        'id' => $entry->id,
    ]);
});

test('user cannot delete another users plan entry', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $location = Location::factory()->create(['slug' => 'jws', 'name' => 'James Watt South']);

    $otherEntry = $otherUser->planEntries()->create([
        'entry_date' => '2025-11-10',
        'location_id' => $location->id,
        'note' => 'Other user entry',
    ]);

    Sanctum::actingAs($user);

    $response = $this->deleteJson("/api/v1/plan/{$otherEntry->id}");

    $response->assertNotFound();

    // Entry should still exist
    $this->assertDatabaseHas('plan_entries', [
        'id' => $otherEntry->id,
    ]);
});

test('API validates location exists in database', function () {
    $user = User::factory()->create();

    Sanctum::actingAs($user);

    $response = $this->postJson('/api/v1/plan', [
        'entries' => [
            [
                'entry_date' => '2025-11-10',
                'location' => 'invalid-location',
                'note' => 'Testing validation',
            ],
        ],
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['entries.0.location']);
});

test('API requires location field', function () {
    $user = User::factory()->create();

    Sanctum::actingAs($user);

    $response = $this->postJson('/api/v1/plan', [
        'entries' => [
            [
                'entry_date' => '2025-11-10',
                'note' => 'Missing location',
            ],
        ],
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['entries.0.location']);
});

test('API allows optional note field', function () {
    $user = User::factory()->create();
    $location = Location::factory()->create(['slug' => 'jws', 'name' => 'James Watt South']);

    Sanctum::actingAs($user);

    $response = $this->postJson('/api/v1/plan', [
        'entries' => [
            [
                'entry_date' => '2025-11-10',
                'location' => 'jws',
            ],
        ],
    ]);

    $response->assertOk();

    $this->assertDatabaseHas('plan_entries', [
        'user_id' => $user->id,
        'location_id' => $location->id,
        'note' => null,
    ]);
});

// Reference Data Tests

test('authenticated user can retrieve locations list', function () {
    $user = User::factory()->create();
    Location::factory()->create(['slug' => 'jws', 'name' => 'JWS', 'short_label' => 'JWS']);

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/v1/locations');

    $response->assertOk();
    $response->assertJsonStructure([
        'locations' => [
            '*' => ['value', 'label', 'short_label'],
        ],
    ]);

    $locations = $response->json('locations');
    expect($locations)->toBeArray();
    expect(count($locations))->toBeGreaterThan(0);

    // Check that JWS location exists with expected structure
    $jws = collect($locations)->firstWhere('value', 'jws');
    expect($jws)->not->toBeNull();
    expect($jws['label'])->toBe('JWS');
    expect($jws['short_label'])->toBe('JWS');
});

test('unauthenticated request to locations endpoint returns 401', function () {
    $response = $this->getJson('/api/v1/locations');

    $response->assertUnauthorized();
});

<?php

use App\Enums\AvailabilityStatus;
use App\Models\Location;
use App\Models\Team;
use App\Models\User;
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

    Sanctum::actingAs($user, ['view:own-plan']);

    $response = $this->getJson('/api/v1/plan');

    $response->assertOk();
    $response->assertJsonStructure([
        'user' => ['id', 'name'],
        'date_range' => ['start', 'end'],
        'entries',
    ]);
});

// Report Endpoint Tests - Basic Structure

test('manager can access team report endpoint', function () {
    $manager = User::factory()->create();
    Team::factory()->create(['manager_id' => $manager->id]);

    Sanctum::actingAs($manager, ['view:team-plans']);

    $response = $this->getJson('/api/v1/reports/team');

    $response->assertOk();
    $response->assertJsonStructure([
        'scope',
        'days',
        'team_rows',
    ]);
    expect($response->json('scope'))->toBe('view:team-plans');
});

test('admin can access all report endpoints', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    Sanctum::actingAs($admin, ['view:all-plans']);

    // Team report
    $response = $this->getJson('/api/v1/reports/team');
    $response->assertOk();
    expect($response->json('scope'))->toBe('view:all-plans');

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

// CRUD Operations Tests

test('user can create a new plan entry via API', function () {
    $user = User::factory()->create();
    $location = Location::factory()->create(['slug' => 'jws', 'name' => 'James Watt South']);

    Sanctum::actingAs($user, ['view:own-plan']);

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

    Sanctum::actingAs($user, ['view:own-plan']);

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

    Sanctum::actingAs($user, ['view:own-plan']);

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

    Sanctum::actingAs($user, ['view:own-plan']);

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

    Sanctum::actingAs($user, ['view:own-plan']);

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

    Sanctum::actingAs($user, ['view:own-plan']);

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

    Sanctum::actingAs($user, ['view:own-plan']);

    $response = $this->deleteJson("/api/v1/plan/{$otherEntry->id}");

    $response->assertNotFound();

    // Entry should still exist
    $this->assertDatabaseHas('plan_entries', [
        'id' => $otherEntry->id,
    ]);
});

test('API validates location exists in database', function () {
    $user = User::factory()->create();

    Sanctum::actingAs($user, ['view:own-plan']);

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

    Sanctum::actingAs($user, ['view:own-plan']);

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

    Sanctum::actingAs($user, ['view:own-plan']);

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

    Sanctum::actingAs($user, ['view:own-plan']);

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

<?php

use App\Enums\Location;
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

    // Service availability report
    $response = $this->getJson('/api/v1/reports/service-availability');
    $response->assertOk();
});

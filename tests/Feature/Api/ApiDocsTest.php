<?php

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class)->group('api');

test('the generated OpenAPI spec covers the v1 API surface', function () {
    Gate::define('viewApiDocs', fn (?User $user = null) => true);

    $response = $this->getJson('/docs/api.json');

    $response->assertOk();

    $spec = $response->json();

    expect($spec['openapi'] ?? null)->toStartWith('3.')
        ->and(array_keys($spec['paths'] ?? []))
        ->toContain('/v1/plan')
        ->toContain('/v1/locations')
        ->toContain('/v1/reports/team')
        ->toContain('/v1/reports/location')
        ->toContain('/v1/reports/coverage');
});

test('users who are neither admin nor manager cannot view the docs', function () {
    $staff = User::factory()->create(['is_admin' => false]); // not on any managedTeams either

    $response = $this->actingAs($staff)->getJson('/docs/api.json');

    $response->assertForbidden();
});

test('admins can view the docs', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $response = $this->actingAs($admin)->getJson('/docs/api.json');

    $response->assertOk();
});

test('managers can view the docs', function () {
    $manager = User::factory()->create(['is_admin' => false]);
    Team::factory()->create(['manager_id' => $manager->id]);

    $response = $this->actingAs($manager)->getJson('/docs/api.json');

    $response->assertOk();
});

test('each endpoint documents its filter query parameters', function (string $path, array $expected) {
    Gate::define('viewApiDocs', fn (?User $user = null) => true);

    $spec = $this->getJson('/docs/api.json')->json();
    $params = $spec['paths'][$path]['get']['parameters'] ?? [];
    $names = collect($params)->pluck('name')->all();

    foreach ($expected as $name) {
        expect($names)->toContain($name);
    }
})->with([
    'team' => ['/v1/reports/team', ['filter[location_slug]', 'filter[state]', 'filter[from]', 'filter[to]']],
    'location' => ['/v1/reports/location', ['filter[location_slug]', 'filter[is_physical]', 'filter[from]', 'filter[to]']],
    'coverage' => ['/v1/reports/coverage', ['filter[location_slug]', 'filter[is_physical]', 'filter[from]', 'filter[to]']],
    'plan' => ['/v1/plan', ['filter[location_slug]', 'filter[is_holiday]', 'filter[availability_status]', 'filter[from]', 'filter[to]', 'fields[entries]']],
]);

test('service-availability endpoint documents its filter query parameters when services are enabled', function () {
    config(['wcap.services_enabled' => true]);
    Gate::define('viewApiDocs', fn (?User $user = null) => true);

    $spec = $this->getJson('/docs/api.json')->json();
    $params = $spec['paths']['/v1/reports/service-availability']['get']['parameters'] ?? [];
    $names = collect($params)->pluck('name')->all();

    expect($names)->toContain('filter[from]')
        ->toContain('filter[to]')
        ->toContain('filter[service_slug]')
        ->toContain('filter[manager_only]');
});

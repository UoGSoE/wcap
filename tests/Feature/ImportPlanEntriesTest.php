<?php

use App\Enums\Location;
use App\Models\PlanEntry;
use App\Models\Team;
use App\Models\User;
use App\Services\PlanEntryImport;
use App\Services\PlanEntryRowValidator;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

// Authorization tests

test('non-manager cannot access import page', function () {
    $user = User::factory()->create();

    actingAs($user);

    $this->get(route('manager.import'))->assertForbidden();
});

test('manager can access import page', function () {
    $manager = User::factory()->create();
    $team = Team::factory()->create(['manager_id' => $manager->id]);

    actingAs($manager);

    $this->get(route('manager.import'))->assertOk();
});

test('import page shows file upload form', function () {
    $manager = User::factory()->create();
    Team::factory()->create(['manager_id' => $manager->id]);

    actingAs($manager);

    Livewire::test(\App\Livewire\ImportPlanEntries::class)
        ->assertSee('Upload Excel File')
        ->assertSee('Drop file here or click to browse');
});

// PlanEntryRowValidator tests

test('validator passes for valid row', function () {
    $user = User::factory()->create(['email' => 'test@example.com']);

    $row = ['test@example.com', '15/12/2025', 'jws', 'Some note', 'Y'];

    $validator = new PlanEntryRowValidator;
    $result = $validator->validate($row);

    expect($result->fails())->toBeFalse();
});

test('validator fails for unknown email', function () {
    $row = ['unknown@example.com', '15/12/2025', 'jws', 'Some note', 'Y'];

    $validator = new PlanEntryRowValidator;
    $result = $validator->validate($row);

    expect($result->fails())->toBeTrue();
    expect($result->errors()->has('email'))->toBeTrue();
});

test('validator fails for invalid date format', function () {
    User::factory()->create(['email' => 'test@example.com']);

    $row = ['test@example.com', '2025-12-15', 'jws', 'Some note', 'Y'];

    $validator = new PlanEntryRowValidator;
    $result = $validator->validate($row);

    expect($result->fails())->toBeTrue();
    expect($result->errors()->has('date'))->toBeTrue();
});

test('validator fails for invalid location', function () {
    User::factory()->create(['email' => 'test@example.com']);

    $row = ['test@example.com', '15/12/2025', 'invalid-location', 'Some note', 'Y'];

    $validator = new PlanEntryRowValidator;
    $result = $validator->validate($row);

    expect($result->fails())->toBeTrue();
    expect($result->errors()->has('location'))->toBeTrue();
});

test('validator fails for invalid availability value', function () {
    User::factory()->create(['email' => 'test@example.com']);

    $row = ['test@example.com', '15/12/2025', 'jws', 'Some note', 'maybe'];

    $validator = new PlanEntryRowValidator;
    $result = $validator->validate($row);

    expect($result->fails())->toBeTrue();
    expect($result->errors()->has('is_available'))->toBeTrue();
});

test('validator accepts all valid locations', function () {
    $user = User::factory()->create(['email' => 'test@example.com']);
    $validator = new PlanEntryRowValidator;

    foreach (Location::cases() as $location) {
        $row = ['test@example.com', '15/12/2025', $location->value, 'Note', 'Y'];
        $result = $validator->validate($row);

        expect($result->fails())->toBeFalse("Location {$location->value} should be valid");
    }
});

// PlanEntryImport tests

test('import creates entries for valid rows', function () {
    $user = User::factory()->create(['email' => 'test@example.com']);

    $rows = [
        ['email', 'date', 'location', 'note', 'is_available'], // Header
        ['test@example.com', '15/12/2025', 'jws', 'Task one', 'Y'],
        ['test@example.com', '16/12/2025', 'jwn', 'Task two', 'N'],
    ];

    $importer = new PlanEntryImport($rows);
    $errors = $importer->import();

    expect($errors)->toBeEmpty();
    expect(PlanEntry::count())->toBe(2);

    $entries = PlanEntry::where('user_id', $user->id)->orderBy('entry_date')->get();

    expect($entries[0]->location->value)->toBe('jws');
    expect($entries[0]->note)->toBe('Task one');
    expect($entries[0]->is_available)->toBeTrue();
    expect($entries[0]->created_by_manager)->toBeTrue();

    expect($entries[1]->location->value)->toBe('jwn');
    expect($entries[1]->is_available)->toBeFalse();
});

test('import skips header row', function () {
    $user = User::factory()->create(['email' => 'test@example.com']);

    $rows = [
        ['email', 'date', 'location', 'note', 'is_available'],
        ['test@example.com', '15/12/2025', 'jws', 'Real data', 'Y'],
    ];

    $importer = new PlanEntryImport($rows);
    $errors = $importer->import();

    expect($errors)->toBeEmpty();
    expect(PlanEntry::count())->toBe(1);
});

test('import returns errors for invalid rows', function () {
    $user = User::factory()->create(['email' => 'test@example.com']);

    $rows = [
        ['email', 'date', 'location', 'note', 'is_available'],
        ['test@example.com', '15/12/2025', 'jws', 'Valid row', 'Y'],
        ['unknown@example.com', '16/12/2025', 'jws', 'Invalid email', 'Y'],
        ['test@example.com', 'bad-date', 'jws', 'Invalid date', 'Y'],
    ];

    $importer = new PlanEntryImport($rows);
    $errors = $importer->import();

    expect($errors)->toHaveCount(2);
    expect(PlanEntry::count())->toBe(1); // Only valid row imported
});

test('import updates existing entries with same user and date', function () {
    $user = User::factory()->create(['email' => 'test@example.com']);
    $targetDate = \Illuminate\Support\Carbon::createFromFormat('d/m/Y', '15/12/2025');

    PlanEntry::factory()->create([
        'user_id' => $user->id,
        'entry_date' => $targetDate,
        'note' => 'Original note',
        'location' => Location::JWS,
    ]);

    expect(PlanEntry::count())->toBe(1);

    $rows = [
        ['email', 'date', 'location', 'note', 'is_available'],
        ['test@example.com', '15/12/2025', 'jwn', 'Updated note', 'Y'],
    ];

    $importer = new PlanEntryImport($rows);
    $importer->import();

    expect(PlanEntry::count())->toBe(1);

    $entry = PlanEntry::first();
    expect($entry->note)->toBe('Updated note');
    expect($entry->location->value)->toBe('jwn');
});

<?php

use App\Enums\AvailabilityStatus;
use App\Models\Location;
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
    Location::factory()->create(['slug' => 'jws', 'name' => 'JWS']);

    $row = ['test@example.com', '15/12/2025', 'jws', 'Some note', 'O'];

    $validator = new PlanEntryRowValidator;
    $result = $validator->validate($row);

    expect($result->fails())->toBeFalse();
});

test('validator fails for unknown email', function () {
    Location::factory()->create(['slug' => 'jws', 'name' => 'JWS']);

    $row = ['unknown@example.com', '15/12/2025', 'jws', 'Some note', 'O'];

    $validator = new PlanEntryRowValidator;
    $result = $validator->validate($row);

    expect($result->fails())->toBeTrue();
    expect($result->errors()->has('email'))->toBeTrue();
});

test('validator fails for invalid date format', function () {
    User::factory()->create(['email' => 'test@example.com']);
    Location::factory()->create(['slug' => 'jws', 'name' => 'JWS']);

    $row = ['test@example.com', '2025-12-15', 'jws', 'Some note', 'O'];

    $validator = new PlanEntryRowValidator;
    $result = $validator->validate($row);

    expect($result->fails())->toBeTrue();
    expect($result->errors()->has('date'))->toBeTrue();
});

test('validator fails for invalid location', function () {
    User::factory()->create(['email' => 'test@example.com']);

    $row = ['test@example.com', '15/12/2025', 'invalid-location', 'Some note', 'O'];

    $validator = new PlanEntryRowValidator;
    $result = $validator->validate($row);

    expect($result->fails())->toBeTrue();
    expect($result->errors()->has('location'))->toBeTrue();
});

test('validator fails for invalid availability value', function () {
    User::factory()->create(['email' => 'test@example.com']);
    Location::factory()->create(['slug' => 'jws', 'name' => 'JWS']);

    $row = ['test@example.com', '15/12/2025', 'jws', 'Some note', 'maybe'];

    $validator = new PlanEntryRowValidator;
    $result = $validator->validate($row);

    expect($result->fails())->toBeTrue();
    expect($result->errors()->has('availability_status'))->toBeTrue();
});

test('validator accepts all valid locations', function () {
    $user = User::factory()->create(['email' => 'test@example.com']);
    $validator = new PlanEntryRowValidator;

    $locations = Location::factory()->count(3)->create();

    foreach ($locations as $location) {
        $row = ['test@example.com', '15/12/2025', $location->slug, 'Note', 'O'];
        $result = $validator->validate($row);

        expect($result->fails())->toBeFalse("Location {$location->slug} should be valid");
    }
});

// PlanEntryImport tests

test('import creates entries for valid rows', function () {
    $user = User::factory()->create(['email' => 'test@example.com']);
    $locationJws = Location::factory()->create(['slug' => 'jws', 'name' => 'JWS']);
    $locationJwn = Location::factory()->create(['slug' => 'jwn', 'name' => 'JWN']);

    $rows = [
        ['email', 'date', 'location', 'note', 'availability_status'], // Header
        ['test@example.com', '15/12/2025', 'jws', 'Task one', 'O'],
        ['test@example.com', '16/12/2025', 'jwn', 'Task two', 'N'],
    ];

    $importer = new PlanEntryImport($rows);
    $errors = $importer->import();

    expect($errors)->toBeEmpty();
    expect(PlanEntry::count())->toBe(2);

    $entries = PlanEntry::where('user_id', $user->id)->orderBy('entry_date')->get();

    expect($entries[0]->location->slug)->toBe('jws');
    expect($entries[0]->note)->toBe('Task one');
    expect($entries[0]->availability_status)->toBe(AvailabilityStatus::ONSITE);
    expect($entries[0]->created_by_manager)->toBeTrue();

    expect($entries[1]->location->slug)->toBe('jwn');
    expect($entries[1]->availability_status)->toBe(AvailabilityStatus::NOT_AVAILABLE);
});

test('import skips header row', function () {
    $user = User::factory()->create(['email' => 'test@example.com']);
    Location::factory()->create(['slug' => 'jws', 'name' => 'JWS']);

    $rows = [
        ['email', 'date', 'location', 'note', 'availability_status'],
        ['test@example.com', '15/12/2025', 'jws', 'Real data', 'O'],
    ];

    $importer = new PlanEntryImport($rows);
    $errors = $importer->import();

    expect($errors)->toBeEmpty();
    expect(PlanEntry::count())->toBe(1);
});

test('import returns errors for invalid rows', function () {
    $user = User::factory()->create(['email' => 'test@example.com']);
    Location::factory()->create(['slug' => 'jws', 'name' => 'JWS']);

    $rows = [
        ['email', 'date', 'location', 'note', 'availability_status'],
        ['test@example.com', '15/12/2025', 'jws', 'Valid row', 'O'],
        ['unknown@example.com', '16/12/2025', 'jws', 'Invalid email', 'O'],
        ['test@example.com', 'bad-date', 'jws', 'Invalid date', 'O'],
    ];

    $importer = new PlanEntryImport($rows);
    $errors = $importer->import();

    expect($errors)->toHaveCount(2);
    expect(PlanEntry::count())->toBe(1); // Only valid row imported
});

test('import updates existing entries with same user and date', function () {
    $user = User::factory()->create(['email' => 'test@example.com']);
    $locationJws = Location::factory()->create(['slug' => 'jws', 'name' => 'JWS']);
    $locationJwn = Location::factory()->create(['slug' => 'jwn', 'name' => 'JWN']);
    $targetDate = \Illuminate\Support\Carbon::createFromFormat('d/m/Y', '15/12/2025');

    PlanEntry::factory()->create([
        'user_id' => $user->id,
        'entry_date' => $targetDate,
        'note' => 'Original note',
        'location_id' => $locationJws->id,
    ]);

    expect(PlanEntry::count())->toBe(1);

    $rows = [
        ['email', 'date', 'location', 'note', 'availability_status'],
        ['test@example.com', '15/12/2025', 'jwn', 'Updated note', 'O'],
    ];

    $importer = new PlanEntryImport($rows);
    $importer->import();

    expect(PlanEntry::count())->toBe(1);

    $entry = PlanEntry::first();
    expect($entry->note)->toBe('Updated note');
    expect($entry->location->slug)->toBe('jwn');
});

// Quick-create user tests

test('isEmailNotFoundError returns true for email not found error', function () {
    $manager = User::factory()->create();
    Team::factory()->create(['manager_id' => $manager->id]);

    actingAs($manager);

    Livewire::test(\App\Livewire\ImportPlanEntries::class)
        ->call('isEmailNotFoundError', 'The selected email is invalid.')
        ->assertReturned(true);
});

test('isEmailNotFoundError returns false for other email errors', function () {
    $manager = User::factory()->create();
    Team::factory()->create(['manager_id' => $manager->id]);

    actingAs($manager);

    Livewire::test(\App\Livewire\ImportPlanEntries::class)
        ->call('isEmailNotFoundError', 'The email field must be a valid email address.')
        ->assertReturned(false);
});

test('openCreateUserModal sets email and pre-selects team', function () {
    $manager = User::factory()->create();
    $team = Team::factory()->create(['manager_id' => $manager->id]);

    actingAs($manager);

    Livewire::test(\App\Livewire\ImportPlanEntries::class)
        ->call('openCreateUserModal', 0, 'NewUser@Example.com')
        ->assertSet('newUserEmail', 'newuser@example.com')
        ->assertSet('creatingForRowIndex', 0)
        ->assertSet('newUserTeamId', $team->id);
});

test('saveNewUser creates user and attaches to team', function () {
    $manager = User::factory()->create();
    $team = Team::factory()->create(['manager_id' => $manager->id]);
    Location::factory()->create(['slug' => 'jws', 'name' => 'JWS']);

    // Create a real Excel file with unknown email
    $data = [['john.smith@example.com', '15/12/2025', 'jws', 'Note', 'O']];
    $tempPath = (new \Ohffs\SimpleSpout\ExcelSheet)->generate($data);
    $file = (new \Illuminate\Http\Testing\FileFactory)->createWithContent('import.xlsx', file_get_contents($tempPath));

    actingAs($manager);

    Livewire::test(\App\Livewire\ImportPlanEntries::class)
        ->set('file', $file)
        ->call('parseFile')
        ->set('newUserForenames', 'John')
        ->set('newUserSurname', 'Smith')
        ->set('newUserEmail', 'john.smith@example.com')
        ->set('newUserUsername', 'jsmith')
        ->set('newUserTeamId', $team->id)
        ->call('saveNewUser')
        ->assertHasNoErrors();

    $newUser = User::where('email', 'john.smith@example.com')->first();

    expect($newUser)->not->toBeNull();
    expect($newUser->forenames)->toBe('John');
    expect($newUser->surname)->toBe('Smith');
    expect($newUser->username)->toBe('jsmith');
    expect($newUser->is_staff)->toBeTrue();
    expect($newUser->is_admin)->toBeFalse();
    expect($team->fresh()->users->pluck('id'))->toContain($newUser->id);
});

test('saveNewUser lowercases the email', function () {
    $manager = User::factory()->create();
    $team = Team::factory()->create(['manager_id' => $manager->id]);
    Location::factory()->create(['slug' => 'jws', 'name' => 'JWS']);

    // Create a real Excel file with unknown email (uppercase)
    $data = [['Jane.Doe@EXAMPLE.COM', '15/12/2025', 'jws', 'Note', 'O']];
    $tempPath = (new \Ohffs\SimpleSpout\ExcelSheet)->generate($data);
    $file = (new \Illuminate\Http\Testing\FileFactory)->createWithContent('import.xlsx', file_get_contents($tempPath));

    actingAs($manager);

    Livewire::test(\App\Livewire\ImportPlanEntries::class)
        ->set('file', $file)
        ->call('parseFile')
        ->set('newUserForenames', 'Jane')
        ->set('newUserSurname', 'Doe')
        ->set('newUserEmail', 'Jane.Doe@EXAMPLE.COM')
        ->set('newUserUsername', 'jdoe')
        ->set('newUserTeamId', $team->id)
        ->call('saveNewUser');

    expect(User::where('email', 'jane.doe@example.com')->exists())->toBeTrue();
});

test('saveNewUser validates required fields', function () {
    $manager = User::factory()->create();
    Team::factory()->create(['manager_id' => $manager->id]);

    actingAs($manager);

    Livewire::test(\App\Livewire\ImportPlanEntries::class)
        ->set('newUserForenames', '')
        ->set('newUserSurname', '')
        ->set('newUserEmail', '')
        ->set('newUserUsername', '')
        ->set('newUserTeamId', null)
        ->call('saveNewUser')
        ->assertHasErrors(['newUserForenames', 'newUserSurname', 'newUserEmail', 'newUserUsername', 'newUserTeamId']);
});

test('saveNewUser validates unique email and username', function () {
    $manager = User::factory()->create();
    $team = Team::factory()->create(['manager_id' => $manager->id]);
    User::factory()->create([
        'email' => 'existing@example.com',
        'username' => 'existinguser',
    ]);

    actingAs($manager);

    Livewire::test(\App\Livewire\ImportPlanEntries::class)
        ->set('newUserForenames', 'Test')
        ->set('newUserSurname', 'User')
        ->set('newUserEmail', 'existing@example.com')
        ->set('newUserUsername', 'existinguser')
        ->set('newUserTeamId', $team->id)
        ->call('saveNewUser')
        ->assertHasErrors(['newUserEmail', 'newUserUsername']);
});

// Empty row handling tests - fallback to user defaults

test('import uses user default availability when availability is empty', function () {
    $defaultLocation = Location::factory()->create(['slug' => 'jws', 'name' => 'JWS']);
    User::factory()->create([
        'email' => 'test@example.com',
        'default_location_id' => $defaultLocation->id,
        'default_availability_status' => AvailabilityStatus::REMOTE,
    ]);

    $rows = [
        ['email', 'date', 'location', 'note', 'availability_status'],
        ['test@example.com', '15/12/2025', 'jws', 'Has availability', 'O'],
        ['test@example.com', '16/12/2025', 'jws', 'No availability', ''],
    ];

    $importer = new PlanEntryImport($rows);
    $errors = $importer->import();

    expect($errors)->toBeEmpty();
    expect(PlanEntry::count())->toBe(2);

    $entries = PlanEntry::orderBy('entry_date')->get();
    expect($entries[0]->availability_status)->toBe(AvailabilityStatus::ONSITE);
    expect($entries[1]->availability_status)->toBe(AvailabilityStatus::REMOTE);
});

// Note: default_availability_status always has a DB default (2=ONSITE), so the "no default" error case cannot occur

test('import uses user default location when location is empty', function () {
    $defaultLocation = Location::factory()->create(['slug' => 'default', 'name' => 'Default']);
    User::factory()->create([
        'email' => 'test@example.com',
        'default_location_id' => $defaultLocation->id,
        'default_availability_status' => AvailabilityStatus::ONSITE,
    ]);

    $rows = [
        ['email', 'date', 'location', 'note', 'availability_status'],
        ['test@example.com', '15/12/2025', '', 'No location specified', 'O'],
    ];

    $importer = new PlanEntryImport($rows);
    $errors = $importer->import();

    expect($errors)->toBeEmpty();
    expect(PlanEntry::count())->toBe(1);
    expect(PlanEntry::first()->location_id)->toBe($defaultLocation->id);
});

test('import errors when location empty and user has no default', function () {
    User::factory()->create([
        'email' => 'test@example.com',
        'default_location_id' => null,
        'default_availability_status' => AvailabilityStatus::ONSITE,
    ]);

    $rows = [
        ['email', 'date', 'location', 'note', 'availability_status'],
        ['test@example.com', '15/12/2025', '', 'No location', 'O'],
    ];

    $importer = new PlanEntryImport($rows);
    $errors = $importer->import();

    expect($errors)->toHaveCount(1);
    expect($errors[0])->toContain('No location provided and user has no default location');
    expect(PlanEntry::count())->toBe(0);
});

test('import uses user default_category for note when note is empty', function () {
    $location = Location::factory()->create(['slug' => 'jws', 'name' => 'JWS']);
    User::factory()->create([
        'email' => 'test@example.com',
        'default_location_id' => $location->id,
        'default_availability_status' => AvailabilityStatus::ONSITE,
        'default_category' => 'Patching servers',
    ]);

    $rows = [
        ['email', 'date', 'location', 'note', 'availability_status'],
        ['test@example.com', '15/12/2025', 'jws', '', 'O'],
    ];

    $importer = new PlanEntryImport($rows);
    $errors = $importer->import();

    expect($errors)->toBeEmpty();
    expect(PlanEntry::count())->toBe(1);
    expect(PlanEntry::first()->note)->toBe('Patching servers');
});

test('import allows empty note when user has empty default_category', function () {
    $location = Location::factory()->create(['slug' => 'jws', 'name' => 'JWS']);
    // default_category defaults to '' in the database schema
    User::factory()->create([
        'email' => 'test@example.com',
        'default_location_id' => $location->id,
        'default_category' => '',
    ]);

    $rows = [
        ['email', 'date', 'location', 'note', 'availability_status'],
        ['test@example.com', '15/12/2025', 'jws', '', 'O'],
    ];

    $importer = new PlanEntryImport($rows);
    $errors = $importer->import();

    expect($errors)->toBeEmpty();
    expect(PlanEntry::count())->toBe(1);
    expect(PlanEntry::first()->note)->toBe('');
});

test('validator passes for empty location', function () {
    User::factory()->create(['email' => 'test@example.com']);

    $row = ['test@example.com', '15/12/2025', '', 'Note', 'O'];

    $validator = new PlanEntryRowValidator;
    $result = $validator->validate($row);

    expect($result->fails())->toBeFalse();
});

test('validator passes for empty availability', function () {
    User::factory()->create(['email' => 'test@example.com']);

    $row = ['test@example.com', '15/12/2025', '', 'Note', ''];

    $validator = new PlanEntryRowValidator;
    $result = $validator->validate($row);

    expect($result->fails())->toBeFalse();
});

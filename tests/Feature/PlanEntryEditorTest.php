<?php

use App\Enums\AvailabilityStatus;
use App\Livewire\PlanEntryEditor;
use App\Models\Location;
use App\Models\PlanEntry;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->manager = User::factory()->create();
    $this->team = Team::factory()->create(['manager_id' => $this->manager->id]);
    $this->user = User::factory()->create();
    $this->team->users()->attach($this->user->id);
});

test('home page renders with 14 days starting from monday', function () {

    actingAs($this->manager);

    Livewire::test(PlanEntryEditor::class, ['user' => $this->user])
        ->assertOk()
        ->assertSee('Monday')
        ->assertSee('Friday')
        ->assertDontSee('Saturday')
        ->assertDontSee('Sunday');
});

test('filling in a day creates exactly one database record', function () {
    $user = User::factory()->create();
    $location = Location::factory()->create(['slug' => 'other', 'name' => 'Other']);

    actingAs($user);

    Livewire::test(PlanEntryEditor::class, ['user' => $user])
        ->set('entries.0.availability_status', AvailabilityStatus::ONSITE->value)
        ->set('entries.0.location_id', $location->id)
        ->set('entries.0.note', 'Test note')
        ->assertHasNoErrors();

    expect(PlanEntry::where('user_id', $user->id)->count())->toBe(1);

    $entry = PlanEntry::where('user_id', $user->id)->first();
    expect($entry->note)->toBe('Test note');
    expect($entry->location_id)->toBe($location->id);
    expect($entry->entry_date->format('Y-m-d'))->toBe(now()->startOfWeek()->format('Y-m-d'));
});

test('editing a field on a saved entry updates it without creating a duplicate', function () {
    $user = User::factory()->create();
    $location = Location::factory()->create(['slug' => 'other', 'name' => 'Other']);
    $date = now()->startOfWeek();

    $created = PlanEntry::factory()->create([
        'user_id' => $user->id,
        'entry_date' => $date,
        'note' => 'Original note',
        'location_id' => $location->id,
        'availability_status' => AvailabilityStatus::ONSITE,
    ]);

    actingAs($user);

    Livewire::test(PlanEntryEditor::class, ['user' => $user])
        ->set('entries.0.note', 'Updated note')
        ->assertHasNoErrors();

    expect(PlanEntry::where('user_id', $user->id)->count())->toBe(1);

    $updatedEntry = $created->fresh();
    expect($updatedEntry->note)->toBe('Updated note');
    expect($updatedEntry->location_id)->toBe($location->id);
});

test('copy next copies entry to next day only', function () {
    $user = User::factory()->create();
    $location = Location::factory()->create(['slug' => 'other', 'name' => 'Other']);

    actingAs($user);

    $entries = collect(range(0, 13))->map(function ($offset) {
        $date = now()->startOfWeek()->addDays($offset);

        return [
            'id' => null,
            'entry_date' => $date->format('Y-m-d'),
            'note' => '',
            'location_id' => null,
            'availability_status' => AvailabilityStatus::ONSITE->value,
        ];
    })->toArray();

    $entries[0]['note'] = 'First day task';
    $entries[0]['location_id'] = $location->id;

    Livewire::test(PlanEntryEditor::class, ['user' => $user])
        ->set('entries', $entries)
        ->call('copyNext', 0)
        ->assertSet('entries.1.note', 'First day task')
        ->assertSet('entries.1.location_id', $location->id)
        ->assertSet('entries.2.note', '');
});

test('copy rest copies entry to all remaining days', function () {
    $user = User::factory()->create();
    $location = Location::factory()->create(['slug' => 'rankine', 'name' => 'Rankine']);

    actingAs($user);

    $entries = collect(range(0, 13))->map(function ($offset) {
        $date = now()->startOfWeek()->addDays($offset);

        return [
            'id' => null,
            'entry_date' => $date->format('Y-m-d'),
            'note' => '',
            'location_id' => null,
            'availability_status' => AvailabilityStatus::ONSITE->value,
        ];
    })->toArray();

    $entries[0]['note'] = 'Same task all week';
    $entries[0]['location_id'] = $location->id;

    $component = Livewire::test(PlanEntryEditor::class, ['user' => $user])
        ->set('entries', $entries)
        ->call('copyRest', 0);

    // Check all remaining days were copied
    for ($i = 1; $i < 14; $i++) {
        $component->assertSet("entries.{$i}.note", 'Same task all week')
            ->assertSet("entries.{$i}.location_id", $location->id);
    }
});

test('setting availability alone on an empty day writes nothing and shows no error', function () {
    $user = User::factory()->create();

    actingAs($user);

    Livewire::test(PlanEntryEditor::class, ['user' => $user])
        ->set('entries.0.availability_status', AvailabilityStatus::ONSITE->value)
        ->assertHasNoErrors();

    expect(PlanEntry::where('user_id', $user->id)->count())->toBe(0);
});

test('setting availability to not available saves immediately with null location', function () {
    $user = User::factory()->create();

    actingAs($user);

    Livewire::test(PlanEntryEditor::class, ['user' => $user])
        ->set('entries.0.availability_status', AvailabilityStatus::NOT_AVAILABLE->value)
        ->assertHasNoErrors();

    $entry = PlanEntry::where('user_id', $user->id)
        ->whereDate('entry_date', now()->startOfWeek())
        ->first();

    expect($entry)->not->toBeNull();
    expect($entry->availability_status)->toBe(AvailabilityStatus::NOT_AVAILABLE);
    expect($entry->location_id)->toBeNull();
});

test('a row saves without a note', function () {
    $user = User::factory()->create();
    $location = Location::factory()->create(['slug' => 'other', 'name' => 'Other']);

    actingAs($user);

    Livewire::test(PlanEntryEditor::class, ['user' => $user])
        ->set('entries.0.availability_status', AvailabilityStatus::ONSITE->value)
        ->set('entries.0.location_id', $location->id)
        ->assertHasNoErrors();

    $entry = PlanEntry::where('user_id', $user->id)
        ->whereDate('entry_date', now()->startOfWeek())
        ->first();

    expect($entry)->not->toBeNull();
    expect($entry->note)->toBeNull();
});

test('existing entries are loaded on mount', function () {
    $user = User::factory()->create();
    $location = Location::factory()->create(['slug' => 'bo', 'name' => 'Boyd Orr']);
    $date = now()->startOfWeek();

    PlanEntry::factory()->create([
        'user_id' => $user->id,
        'entry_date' => $date,
        'note' => 'Existing task',
        'location_id' => $location->id,
    ]);

    actingAs($user);

    Livewire::test(PlanEntryEditor::class, ['user' => $user])
        ->assertSet('entries.0.note', 'Existing task')
        ->assertSet('entries.0.location_id', $location->id);
});

test('empty days do not prefill from user defaults', function () {
    $location = Location::factory()->create(['slug' => 'other', 'name' => 'Other']);
    $user = User::factory()->create([
        'default_location_id' => $location->id,
        'default_category' => 'Support Tickets',
    ]);

    actingAs($user);

    // Selects bind '' (not null) so the placeholder option stays selected
    // through Livewire's client-side sync.
    Livewire::test(PlanEntryEditor::class, ['user' => $user])
        ->assertSet('entries.0.note', null)
        ->assertSet('entries.0.location_id', '', strict: true)
        ->assertSet('entries.0.availability_status', '', strict: true)
        ->assertSet('entries.13.note', null)
        ->assertSet('entries.13.location_id', '', strict: true);

    expect(PlanEntry::where('user_id', $user->id)->count())->toBe(0);
});

test('existing entries load while empty days beside them stay empty', function () {
    $locationOther = Location::factory()->create(['slug' => 'other', 'name' => 'Other']);
    $locationJws = Location::factory()->create(['slug' => 'jws', 'name' => 'JWS']);
    $user = User::factory()->create([
        'default_location_id' => $locationOther->id,
        'default_category' => 'Support Tickets',
    ]);

    $date = now()->startOfWeek();

    PlanEntry::factory()->create([
        'user_id' => $user->id,
        'entry_date' => $date,
        'note' => 'Specific task',
        'location_id' => $locationJws->id,
    ]);

    actingAs($user);

    Livewire::test(PlanEntryEditor::class, ['user' => $user])
        ->assertSet('entries.0.note', 'Specific task')
        ->assertSet('entries.0.location_id', $locationJws->id)
        ->assertSet('entries.1.note', null)
        ->assertSet('entries.1.location_id', '', strict: true);
});

test('availability_status saves correctly per day', function () {
    $user = User::factory()->create();
    $location = Location::factory()->create(['slug' => 'other', 'name' => 'Other']);

    actingAs($user);

    Livewire::test(PlanEntryEditor::class, ['user' => $user])
        ->set('entries.0.availability_status', AvailabilityStatus::ONSITE->value)
        ->set('entries.0.location_id', $location->id)
        ->set('entries.1.availability_status', AvailabilityStatus::NOT_AVAILABLE->value)
        ->assertHasNoErrors();

    expect(PlanEntry::where('user_id', $user->id)->count())->toBe(2);

    $firstEntry = PlanEntry::where('user_id', $user->id)->whereDate('entry_date', now()->startOfWeek())->first();
    expect($firstEntry->availability_status)->toBe(AvailabilityStatus::ONSITE);

    $secondEntry = PlanEntry::where('user_id', $user->id)->whereDate('entry_date', now()->startOfWeek()->addDay())->first();
    expect($secondEntry->availability_status)->toBe(AvailabilityStatus::NOT_AVAILABLE);
});

test('copy next includes availability_status', function () {
    $user = User::factory()->create();
    $location = Location::factory()->create(['slug' => 'other', 'name' => 'Other']);

    actingAs($user);

    $entries = collect(range(0, 13))->map(function ($offset) {
        $date = now()->startOfWeek()->addDays($offset);

        return [
            'id' => null,
            'entry_date' => $date->format('Y-m-d'),
            'note' => '',
            'location_id' => null,
            'availability_status' => AvailabilityStatus::ONSITE->value,
        ];
    })->toArray();

    $entries[0]['note'] = 'First day task';
    $entries[0]['location_id'] = $location->id;
    $entries[0]['availability_status'] = AvailabilityStatus::NOT_AVAILABLE->value;

    Livewire::test(PlanEntryEditor::class, ['user' => $user])
        ->set('entries', $entries)
        ->call('copyNext', 0)
        ->assertSet('entries.1.note', 'First day task')
        ->assertSet('entries.1.location_id', $location->id)
        ->assertSet('entries.1.availability_status', AvailabilityStatus::NOT_AVAILABLE->value)
        ->assertSet('entries.2.availability_status', AvailabilityStatus::ONSITE->value);
});

test('copy rest includes availability_status', function () {
    $user = User::factory()->create();
    $location = Location::factory()->create(['slug' => 'rankine', 'name' => 'Rankine']);

    actingAs($user);

    $entries = collect(range(0, 13))->map(function ($offset) {
        $date = now()->startOfWeek()->addDays($offset);

        return [
            'id' => null,
            'entry_date' => $date->format('Y-m-d'),
            'note' => '',
            'location_id' => null,
            'availability_status' => AvailabilityStatus::ONSITE->value,
        ];
    })->toArray();

    $entries[0]['note'] = 'Same task all week';
    $entries[0]['location_id'] = $location->id;
    $entries[0]['availability_status'] = AvailabilityStatus::NOT_AVAILABLE->value;

    $component = Livewire::test(PlanEntryEditor::class, ['user' => $user])
        ->set('entries', $entries)
        ->call('copyRest', 0);

    for ($i = 1; $i < 14; $i++) {
        $component->assertSet("entries.{$i}.note", 'Same task all week')
            ->assertSet("entries.{$i}.location_id", $location->id)
            ->assertSet("entries.{$i}.availability_status", AvailabilityStatus::NOT_AVAILABLE->value);
    }
});

test('existing entries load availability_status value', function () {
    $user = User::factory()->create();
    $location = Location::factory()->create(['slug' => 'other', 'name' => 'Other']);
    $date = now()->startOfWeek();

    PlanEntry::factory()->unavailable()->create([
        'user_id' => $user->id,
        'entry_date' => $date,
        'note' => 'Unavailable task',
        'location_id' => $location->id,
    ]);

    actingAs($user);

    Livewire::test(PlanEntryEditor::class, ['user' => $user])
        ->assertSet('entries.0.availability_status', AvailabilityStatus::NOT_AVAILABLE->value)
        ->assertSet('entries.1.availability_status', '', strict: true);
});

test('copy next works for remote availability with non-physical location', function () {
    $user = User::factory()->create();
    $remoteLocation = Location::factory()->nonPhysical()->create(['slug' => 'remote', 'name' => 'Remote']);

    actingAs($user);

    $entries = collect(range(0, 13))->map(function ($offset) {
        $date = now()->startOfWeek()->addDays($offset);

        return [
            'id' => null,
            'entry_date' => $date->format('Y-m-d'),
            'note' => '',
            'location_id' => null,
            'availability_status' => AvailabilityStatus::ONSITE->value,
        ];
    })->toArray();

    $entries[0]['note'] = 'Working from home';
    $entries[0]['location_id'] = $remoteLocation->id;
    $entries[0]['availability_status'] = AvailabilityStatus::REMOTE->value;

    Livewire::test(PlanEntryEditor::class, ['user' => $user])
        ->set('entries', $entries)
        ->call('copyNext', 0)
        ->assertSet('entries.1.note', 'Working from home')
        ->assertSet('entries.1.location_id', $remoteLocation->id)
        ->assertSet('entries.1.availability_status', AvailabilityStatus::REMOTE->value)
        ->assertSet('entries.2.note', '')
        ->assertSet('entries.2.location_id', null)
        ->assertSet('entries.2.availability_status', AvailabilityStatus::ONSITE->value);
});

test('copy rest works for remote availability with non-physical location', function () {
    $user = User::factory()->create();
    $remoteLocation = Location::factory()->nonPhysical()->create(['slug' => 'remote', 'name' => 'Remote']);

    actingAs($user);

    $entries = collect(range(0, 13))->map(function ($offset) {
        $date = now()->startOfWeek()->addDays($offset);

        return [
            'id' => null,
            'entry_date' => $date->format('Y-m-d'),
            'note' => '',
            'location_id' => null,
            'availability_status' => AvailabilityStatus::ONSITE->value,
        ];
    })->toArray();

    $entries[0]['note'] = 'Working from home';
    $entries[0]['location_id'] = $remoteLocation->id;
    $entries[0]['availability_status'] = AvailabilityStatus::REMOTE->value;

    $component = Livewire::test(PlanEntryEditor::class, ['user' => $user])
        ->set('entries', $entries)
        ->call('copyRest', 0);

    for ($i = 1; $i < 14; $i++) {
        $component->assertSet("entries.{$i}.note", 'Working from home')
            ->assertSet("entries.{$i}.location_id", $remoteLocation->id)
            ->assertSet("entries.{$i}.availability_status", AvailabilityStatus::REMOTE->value);
    }
});

test('the availability select shows a placeholder on empty days', function () {
    $user = User::factory()->create();

    actingAs($user);

    Livewire::test(PlanEntryEditor::class, ['user' => $user])
        ->assertSee('Availability');
});

test('unsaved day cards rely on the soft card styling with no badge', function () {
    $userWithNoEntries = User::factory()->create();

    actingAs($userWithNoEntries);

    Livewire::test(PlanEntryEditor::class, ['user' => $userWithNoEntries])
        ->assertDontSee('Not saved');
});

test('copy buttons render disabled when their row is not savable', function () {
    $location = Location::factory()->create(['slug' => 'other', 'name' => 'Other']);
    $user = User::factory()->create();
    PlanEntry::factory()->create([
        'user_id' => $user->id,
        'entry_date' => now()->startOfWeek(),
        'location_id' => $location->id,
        'availability_status' => AvailabilityStatus::ONSITE,
    ]);

    actingAs($user);

    $html = Livewire::test(PlanEntryEditor::class, ['user' => $user])->html();

    preg_match('/<button[^>]*copyNext\(0\)[^>]*>/', $html, $savedDayButton);
    preg_match('/<button[^>]*copyNext\(1\)[^>]*>/', $html, $emptyDayButton);

    // A disabled attribute, not the disabled: tailwind classes flux buttons carry.
    expect(preg_match('/\sdisabled[\s>=]/', $savedDayButton[0]))->toBe(0);
    expect(preg_match('/\sdisabled[\s>=]/', $emptyDayButton[0]))->toBe(1);
});

test('the bookmark save-all button and its action are gone', function () {
    $user = User::factory()->create();

    actingAs($user);

    Livewire::test(PlanEntryEditor::class, ['user' => $user])
        ->assertDontSeeHtml('wire:click="save"');

    expect(method_exists(PlanEntryEditor::class, 'save'))->toBeFalse();
});

test('read-only mode prevents saving', function () {
    $user = User::factory()->create();
    $location = Location::factory()->create(['slug' => 'other', 'name' => 'Other']);

    actingAs($user);

    Livewire::test(PlanEntryEditor::class, ['user' => $user, 'readOnly' => true])
        ->set('entries.0.availability_status', AvailabilityStatus::ONSITE->value)
        ->set('entries.0.location_id', $location->id)
        ->set('entries.0.note', 'Should not save')
        ->assertOk();

    expect(PlanEntry::where('user_id', $user->id)->count())->toBe(0);
});

test('read-only mode prevents copy next', function () {
    $user = User::factory()->create();
    $location = Location::factory()->create(['slug' => 'jws', 'name' => 'JWS']);

    actingAs($user);

    Livewire::test(PlanEntryEditor::class, ['user' => $user, 'readOnly' => true])
        ->set('entries.0.note', 'First day')
        ->set('entries.0.location_id', $location->id)
        ->set('entries.1.note', 'Original')
        ->call('copyNext', 0)
        ->assertSet('entries.1.note', 'Original');
});

test('read-only mode prevents copy rest', function () {
    $user = User::factory()->create();
    $location = Location::factory()->create(['slug' => 'jws', 'name' => 'JWS']);

    actingAs($user);

    Livewire::test(PlanEntryEditor::class, ['user' => $user, 'readOnly' => true])
        ->set('entries.0.note', 'First day')
        ->set('entries.0.location_id', $location->id)
        ->set('entries.5.note', 'Original')
        ->call('copyRest', 0)
        ->assertSet('entries.5.note', 'Original');
});

test('completing a row persists it without clicking save', function () {
    $location = Location::factory()->create(['slug' => 'jws', 'name' => 'JWS']);
    $user = User::factory()->create();

    actingAs($user);

    Livewire::test(PlanEntryEditor::class, ['user' => $user])
        ->set('entries.0.availability_status', AvailabilityStatus::ONSITE->value)
        ->set('entries.0.location_id', $location->id)
        ->assertHasNoErrors();

    $entry = PlanEntry::where('user_id', $user->id)
        ->where('entry_date', now()->startOfWeek())
        ->first();

    expect($entry)->not->toBeNull();
    expect($entry->availability_status)->toBe(AvailabilityStatus::ONSITE);
    expect($entry->location_id)->toBe($location->id);
});

test('setting a field on an incomplete row writes nothing and shows no error', function () {
    $user = User::factory()->create(); // no default location

    actingAs($user);

    Livewire::test(PlanEntryEditor::class, ['user' => $user])
        ->set('entries.0.note', 'Half-entered day')
        ->assertHasNoErrors();

    expect(PlanEntry::where('user_id', $user->id)->count())->toBe(0);
});

test('copy next persists the copied row without clicking save', function () {
    $location = Location::factory()->create(['slug' => 'jws', 'name' => 'JWS']);
    $user = User::factory()->create();

    PlanEntry::factory()->create([
        'user_id' => $user->id,
        'entry_date' => now()->startOfWeek(),
        'note' => 'Source note',
        'location_id' => $location->id,
        'availability_status' => AvailabilityStatus::ONSITE,
    ]);

    actingAs($user);

    Livewire::test(PlanEntryEditor::class, ['user' => $user])
        ->call('copyNext', 0);

    $copied = PlanEntry::where('user_id', $user->id)
        ->whereDate('entry_date', now()->startOfWeek()->addDay())
        ->first();

    expect($copied)->not->toBeNull();
    expect($copied->note)->toBe('Source note');
    expect($copied->location_id)->toBe($location->id);
});

test('copy next does nothing when the source row is incomplete', function () {
    $location = Location::factory()->create(['slug' => 'jws', 'name' => 'JWS']);
    $user = User::factory()->create();

    $existingEntry = PlanEntry::factory()->create([
        'user_id' => $user->id,
        'entry_date' => now()->startOfWeek()->addDay(),
        'note' => 'Original',
        'location_id' => $location->id,
        'availability_status' => AvailabilityStatus::ONSITE,
    ]);

    actingAs($user);

    Livewire::test(PlanEntryEditor::class, ['user' => $user])
        ->call('copyNext', 0)
        ->assertSet('entries.1.note', 'Original')
        ->assertSet('entries.1.location_id', $location->id);

    expect($user->planEntries()->count())->toBe(1);
    expect($existingEntry->fresh()->note)->toBe('Original');
});

test('copy rest does nothing when the source row is incomplete', function () {
    $location = Location::factory()->create(['slug' => 'jws', 'name' => 'JWS']);
    $user = User::factory()->create();

    $existingEntry = PlanEntry::factory()->create([
        'user_id' => $user->id,
        'entry_date' => now()->startOfWeek()->addDays(3),
        'note' => 'Original',
        'location_id' => $location->id,
        'availability_status' => AvailabilityStatus::ONSITE,
    ]);

    actingAs($user);

    Livewire::test(PlanEntryEditor::class, ['user' => $user])
        ->call('copyRest', 0)
        ->assertSet('entries.3.note', 'Original')
        ->assertSet('entries.3.location_id', $location->id);

    expect($user->planEntries()->count())->toBe(1);
    expect($existingEntry->fresh()->note)->toBe('Original');
});

test('cannot update another users entry', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $location = Location::factory()->create(['slug' => 'jws', 'name' => 'JWS']);
    $locationOther = Location::factory()->create(['slug' => 'other', 'name' => 'Other']);
    $date = now()->startOfWeek();

    $userAEntry = PlanEntry::factory()->create([
        'user_id' => $userA->id,
        'entry_date' => $date,
        'note' => 'User A task',
        'location_id' => $location->id,
    ]);

    actingAs($userB);

    // Attempt to hijack user A's entry: craft a complete row carrying
    // user A's entry id, then trigger the row save with a field change.
    Livewire::test(PlanEntryEditor::class, ['user' => $userB])
        ->set('entries.0.id', $userAEntry->id)
        ->set('entries.0.location_id', $locationOther->id)
        ->set('entries.0.availability_status', AvailabilityStatus::ONSITE->value)
        ->assertHasErrors(['entries.0.id']);

    // Verify user A's entry was not modified
    $userAEntry->refresh();
    expect($userAEntry->user_id)->toBe($userA->id);
    expect($userAEntry->note)->toBe('User A task');
});

test('fill from defaults fills the empty weekdays of the fortnight shown', function () {
    $location = Location::factory()->create(['slug' => 'other', 'name' => 'Other']);
    $user = User::factory()->create([
        'default_location_id' => $location->id,
        'default_availability_status' => AvailabilityStatus::REMOTE,
        'default_category' => 'Support tickets',
    ]);
    PlanEntry::factory()->unavailable()->create([
        'user_id' => $user->id,
        'entry_date' => now()->startOfWeek()->addDays(2),
        'note' => 'On holiday',
    ]);

    actingAs($user);

    Livewire::test(PlanEntryEditor::class, ['user' => $user])
        ->call('fillFromDefaults')
        ->assertSet('entries.0.location_id', $location->id)
        ->assertSet('entries.0.availability_status', AvailabilityStatus::REMOTE->value);

    expect($user->planEntries()->count())->toBe(10);
    $filledDay = $user->planEntries()->whereDate('entry_date', now()->startOfWeek())->first();
    expect($filledDay->location_id)->toBe($location->id);
    expect($filledDay->note)->toBe('Support tickets');

    $untouched = $user->planEntries()->whereDate('entry_date', now()->startOfWeek()->addDays(2))->first();
    expect($untouched->note)->toBe('On holiday');
});

test('fill from defaults fills a future fortnight when mounted with a startDate', function () {
    $location = Location::factory()->create(['slug' => 'other', 'name' => 'Other']);
    $user = User::factory()->create(['default_location_id' => $location->id]);
    $futureMonday = now()->startOfWeek()->addWeeks(4);

    actingAs($user);

    Livewire::test(PlanEntryEditor::class, [
        'user' => $user,
        'startDate' => $futureMonday->toDateString(),
    ])
        ->call('fillFromDefaults');

    expect($user->planEntries()->count())->toBe(10);
    expect($user->planEntries()->whereDate('entry_date', $futureMonday)->exists())->toBeTrue();
    expect($user->planEntries()->whereDate('entry_date', now()->startOfWeek())->exists())->toBeFalse();
});

test('a second fill from defaults creates nothing more', function () {
    $location = Location::factory()->create(['slug' => 'other', 'name' => 'Other']);
    $user = User::factory()->create(['default_location_id' => $location->id]);

    actingAs($user);

    $component = Livewire::test(PlanEntryEditor::class, ['user' => $user])
        ->call('fillFromDefaults');
    expect($user->planEntries()->count())->toBe(10);

    $component->call('fillFromDefaults');
    expect($user->planEntries()->count())->toBe(10);
});

test('fill from defaults sets created_by_manager when a manager fills someone elses plan', function () {
    $location = Location::factory()->create(['slug' => 'other', 'name' => 'Other']);
    $manager = User::factory()->create();
    $member = User::factory()->create(['default_location_id' => $location->id]);

    actingAs($manager);

    Livewire::test(PlanEntryEditor::class, [
        'user' => $member,
        'createdByManager' => true,
    ])
        ->call('fillFromDefaults');

    expect($member->planEntries()->count())->toBe(10);
    expect($member->planEntries()->first()->created_by_manager)->toBeTrue();
});

test('fill from defaults creates nothing for a user with no usable defaults', function () {
    $userWithoutDefaults = User::factory()->create([
        'default_location_id' => null,
        'default_availability_status' => AvailabilityStatus::ONSITE,
    ]);

    actingAs($userWithoutDefaults);

    Livewire::test(PlanEntryEditor::class, ['user' => $userWithoutDefaults])
        ->call('fillFromDefaults');

    expect($userWithoutDefaults->planEntries()->count())->toBe(0);
});

test('fill from defaults button shows on the editor but not in read-only mode where the action is inert', function () {
    $location = Location::factory()->create(['slug' => 'other', 'name' => 'Other']);
    $user = User::factory()->create(['default_location_id' => $location->id]);

    actingAs($user);

    Livewire::test(PlanEntryEditor::class, ['user' => $user])
        ->assertSee('Fill unsaved days from defaults');

    Livewire::test(PlanEntryEditor::class, ['user' => $user, 'readOnly' => true])
        ->assertDontSee('Fill unsaved days from defaults')
        ->call('fillFromDefaults');

    expect($user->planEntries()->count())->toBe(0);
});

test('editor renders the fortnight starting from a given startDate', function () {
    actingAs($this->manager);

    $futureMonday = now()->startOfWeek()->addWeeks(8);

    Livewire::test(PlanEntryEditor::class, [
        'user' => $this->user,
        'startDate' => $futureMonday->toDateString(),
    ])
        ->assertOk()
        ->assertSet('entries.0.entry_date', $futureMonday->toDateString())
        ->assertSet('entries.13.entry_date', $futureMonday->copy()->addDays(13)->toDateString());
});

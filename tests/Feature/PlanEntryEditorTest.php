<?php

use App\Enums\AvailabilityStatus;
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

    Livewire::test(\App\Livewire\PlanEntryEditor::class, ['user' => $this->user])
        ->assertOk()
        ->assertSee('Monday')
        ->assertSee('Friday')
        ->assertDontSee('Saturday')
        ->assertDontSee('Sunday');
});

test('saving new entries creates database records', function () {
    $user = User::factory()->create();
    $location = Location::factory()->create(['slug' => 'other', 'name' => 'Other']);

    actingAs($user);

    $entries = collect(range(0, 13))->map(function ($offset) use ($location) {
        $date = now()->startOfWeek()->addDays($offset);

        return [
            'id' => null,
            'entry_date' => $date->format('Y-m-d'),
            'note' => 'Test note '.$offset,
            'location_id' => $location->id,
            'availability_status' => AvailabilityStatus::ONSITE->value,
        ];
    })->toArray();

    Livewire::test(\App\Livewire\PlanEntryEditor::class, ['user' => $user])
        ->set('entries', $entries)
        ->call('save')
        ->assertOk();

    expect(PlanEntry::where('user_id', $user->id)->count())->toBe(14);

    $firstEntry = PlanEntry::where('user_id', $user->id)->first();
    expect($firstEntry->note)->toBe('Test note 0');
    expect($firstEntry->location_id)->toBe($location->id);
});

test('saving updates existing entries', function () {
    $user = User::factory()->create();
    $locationOther = Location::factory()->create(['slug' => 'other', 'name' => 'Other']);
    $locationJws = Location::factory()->create(['slug' => 'jws', 'name' => 'JWS']);
    $date = now()->startOfWeek();

    $created = PlanEntry::factory()->create([
        'user_id' => $user->id,
        'entry_date' => $date,
        'note' => 'Original note',
        'location_id' => $locationOther->id,
    ]);

    actingAs($user);

    $entries = collect(range(0, 13))->map(function ($offset) use ($date, $locationJws) {
        return [
            'id' => null,
            'entry_date' => $date->copy()->addDays($offset)->format('Y-m-d'),
            'note' => 'Updated note '.$offset,
            'location_id' => $locationJws->id,
            'availability_status' => AvailabilityStatus::ONSITE->value,
        ];
    })->toArray();

    // Set the id for the first entry (the one we created above)
    $entries[0]['id'] = $created->id;

    Livewire::test(\App\Livewire\PlanEntryEditor::class, ['user' => $user])
        ->set('entries', $entries)
        ->call('save')
        ->assertOk();

    // Should still only have 14 entries (not duplicates)
    expect(PlanEntry::where('user_id', $user->id)->count())->toBe(14);

    $updatedEntry = PlanEntry::where('user_id', $user->id)
        ->where('entry_date', $date)
        ->first();

    expect($updatedEntry->note)->toBe('Updated note 0');
    expect($updatedEntry->location_id)->toBe($locationJws->id);
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

    Livewire::test(\App\Livewire\PlanEntryEditor::class, ['user' => $user])
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

    $component = Livewire::test(\App\Livewire\PlanEntryEditor::class, ['user' => $user])
        ->set('entries', $entries)
        ->call('copyRest', 0);

    // Check all remaining days were copied
    for ($i = 1; $i < 14; $i++) {
        $component->assertSet("entries.{$i}.note", 'Same task all week')
            ->assertSet("entries.{$i}.location_id", $location->id);
    }
});

test('validation requires location field when available', function () {
    $user = User::factory()->create();

    actingAs($user);

    $entries = collect(range(0, 13))->map(function ($offset) {
        $date = now()->startOfWeek()->addDays($offset);

        return [
            'id' => null,
            'entry_date' => $date->format('Y-m-d'),
            'note' => 'Test note',
            'location_id' => null, // Empty location should fail when available
            'availability_status' => AvailabilityStatus::ONSITE->value,
        ];
    })->toArray();

    Livewire::test(\App\Livewire\PlanEntryEditor::class, ['user' => $user])
        ->set('entries', $entries)
        ->call('save')
        ->assertHasErrors(['entries.0.location_id']);
});

test('validation skips location when unavailable', function () {
    $user = User::factory()->create();

    actingAs($user);

    $entries = collect(range(0, 13))->map(function ($offset) {
        $date = now()->startOfWeek()->addDays($offset);

        return [
            'id' => null,
            'entry_date' => $date->format('Y-m-d'),
            'note' => 'Test note',
            'location_id' => null, // Empty location is OK when unavailable
            'availability_status' => AvailabilityStatus::NOT_AVAILABLE->value,
        ];
    })->toArray();

    Livewire::test(\App\Livewire\PlanEntryEditor::class, ['user' => $user])
        ->set('entries', $entries)
        ->call('save')
        ->assertHasNoErrors();

    expect(PlanEntry::where('user_id', $user->id)->count())->toBe(14);

    $firstEntry = PlanEntry::where('user_id', $user->id)->first();
    expect($firstEntry->availability_status)->toBe(AvailabilityStatus::NOT_AVAILABLE);
    expect($firstEntry->location_id)->toBeNull();
});

test('validation allows empty note field', function () {
    $user = User::factory()->create();
    $location = Location::factory()->create(['slug' => 'other', 'name' => 'Other']);

    actingAs($user);

    $entries = collect(range(0, 13))->map(function ($offset) use ($location) {
        $date = now()->startOfWeek()->addDays($offset);

        return [
            'id' => null,
            'entry_date' => $date->format('Y-m-d'),
            'note' => '', // Empty note should be allowed
            'location_id' => $location->id,
            'availability_status' => AvailabilityStatus::ONSITE->value,
        ];
    })->toArray();

    Livewire::test(\App\Livewire\PlanEntryEditor::class, ['user' => $user])
        ->set('entries', $entries)
        ->call('save')
        ->assertHasNoErrors();

    expect(PlanEntry::where('user_id', $user->id)->count())->toBe(14);
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

    Livewire::test(\App\Livewire\PlanEntryEditor::class, ['user' => $user])
        ->assertSet('entries.0.note', 'Existing task')
        ->assertSet('entries.0.location_id', $location->id);
});

test('new entries use user defaults', function () {
    $location = Location::factory()->create(['slug' => 'other', 'name' => 'Other']);
    $user = User::factory()->create([
        'default_location_id' => $location->id,
        'default_category' => 'Support Tickets',
    ]);

    actingAs($user);

    Livewire::test(\App\Livewire\PlanEntryEditor::class, ['user' => $user])
        ->assertSet('entries.0.note', 'Support Tickets')
        ->assertSet('entries.0.location_id', $location->id)
        ->assertSet('entries.13.note', 'Support Tickets')
        ->assertSet('entries.13.location_id', $location->id);
});

test('existing entries override user defaults', function () {
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

    Livewire::test(\App\Livewire\PlanEntryEditor::class, ['user' => $user])
        ->assertSet('entries.0.note', 'Specific task')
        ->assertSet('entries.0.location_id', $locationJws->id)
        ->assertSet('entries.1.note', 'Support Tickets')
        ->assertSet('entries.1.location_id', $locationOther->id);
});

test('availability_status saves correctly', function () {
    $user = User::factory()->create();
    $location = Location::factory()->create(['slug' => 'other', 'name' => 'Other']);

    actingAs($user);

    $entries = collect(range(0, 13))->map(function ($offset) use ($location) {
        $date = now()->startOfWeek()->addDays($offset);

        return [
            'id' => null,
            'entry_date' => $date->format('Y-m-d'),
            'note' => 'Test note',
            'location_id' => $location->id,
            'availability_status' => $offset % 2 === 0 ? AvailabilityStatus::ONSITE->value : AvailabilityStatus::NOT_AVAILABLE->value,
        ];
    })->toArray();

    Livewire::test(\App\Livewire\PlanEntryEditor::class, ['user' => $user])
        ->set('entries', $entries)
        ->call('save')
        ->assertOk();

    expect(PlanEntry::where('user_id', $user->id)->count())->toBe(14);

    $firstEntry = PlanEntry::where('user_id', $user->id)->where('entry_date', now()->startOfWeek())->first();
    expect($firstEntry->availability_status)->toBe(AvailabilityStatus::ONSITE);

    $secondEntry = PlanEntry::where('user_id', $user->id)->where('entry_date', now()->startOfWeek()->addDay())->first();
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

    Livewire::test(\App\Livewire\PlanEntryEditor::class, ['user' => $user])
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

    $component = Livewire::test(\App\Livewire\PlanEntryEditor::class, ['user' => $user])
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

    Livewire::test(\App\Livewire\PlanEntryEditor::class, ['user' => $user])
        ->assertSet('entries.0.availability_status', AvailabilityStatus::NOT_AVAILABLE->value)
        ->assertSet('entries.1.availability_status', AvailabilityStatus::ONSITE->value);
});

test('read-only mode prevents saving', function () {
    $user = User::factory()->create();
    $location = Location::factory()->create(['slug' => 'other', 'name' => 'Other']);

    actingAs($user);

    $entries = collect(range(0, 13))->map(function ($offset) use ($location) {
        $date = now()->startOfWeek()->addDays($offset);

        return [
            'id' => null,
            'entry_date' => $date->format('Y-m-d'),
            'note' => 'Should not save',
            'location_id' => $location->id,
            'availability_status' => AvailabilityStatus::ONSITE->value,
        ];
    })->toArray();

    Livewire::test(\App\Livewire\PlanEntryEditor::class, ['user' => $user, 'readOnly' => true])
        ->set('entries', $entries)
        ->call('save')
        ->assertOk();

    expect(PlanEntry::where('user_id', $user->id)->count())->toBe(0);
});

test('read-only mode prevents copy next', function () {
    $user = User::factory()->create();
    $location = Location::factory()->create(['slug' => 'jws', 'name' => 'JWS']);

    actingAs($user);

    Livewire::test(\App\Livewire\PlanEntryEditor::class, ['user' => $user, 'readOnly' => true])
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

    Livewire::test(\App\Livewire\PlanEntryEditor::class, ['user' => $user, 'readOnly' => true])
        ->set('entries.0.note', 'First day')
        ->set('entries.0.location_id', $location->id)
        ->set('entries.5.note', 'Original')
        ->call('copyRest', 0)
        ->assertSet('entries.5.note', 'Original');
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

    $entries = collect(range(0, 13))->map(function ($offset) use ($date, $locationOther) {
        return [
            'id' => null,
            'entry_date' => $date->copy()->addDays($offset)->format('Y-m-d'),
            'note' => 'User B task',
            'location_id' => $locationOther->id,
            'availability_status' => AvailabilityStatus::ONSITE->value,
        ];
    })->toArray();

    // Attempt to hijack user A's entry
    $entries[0]['id'] = $userAEntry->id;

    Livewire::test(\App\Livewire\PlanEntryEditor::class, ['user' => $userB])
        ->set('entries', $entries)
        ->call('save')
        ->assertHasErrors(['entries.0.id']);

    // Verify user A's entry was not modified
    $userAEntry->refresh();
    expect($userAEntry->user_id)->toBe($userA->id);
    expect($userAEntry->note)->toBe('User A task');
});

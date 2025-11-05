<?php

use App\Enums\Location;
use App\Models\PlanEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

test('home page renders with 14 days starting from monday', function () {
    $user = User::factory()->create();

    actingAs($user);

    Livewire::test(\App\Livewire\HomePage::class)
        ->assertOk()
        ->assertSee('What are you working on?')
        ->assertSee('Monday')
        ->assertSee('Friday')
        ->assertDontSee('Saturday')
        ->assertDontSee('Sunday');
});

test('saving new entries creates database records', function () {
    $user = User::factory()->create();

    actingAs($user);

    $entries = collect(range(0, 13))->map(function ($offset) {
        $date = now()->startOfWeek()->addDays($offset);

        return [
            'id' => null,
            'entry_date' => $date->format('Y-m-d'),
            'note' => 'Test note '.$offset,
            'location' => Location::OTHER->value,
            'is_available' => true,
        ];
    })->toArray();

    Livewire::test(\App\Livewire\HomePage::class)
        ->set('entries', $entries)
        ->call('save')
        ->assertOk();

    expect(PlanEntry::where('user_id', $user->id)->count())->toBe(14);

    $firstEntry = PlanEntry::where('user_id', $user->id)->first();
    expect($firstEntry->note)->toBe('Test note 0');
    expect($firstEntry->location)->toBe(Location::OTHER);
});

test('saving updates existing entries', function () {
    $user = User::factory()->create();
    $date = now()->startOfWeek();

    $created = PlanEntry::factory()->create([
        'user_id' => $user->id,
        'entry_date' => $date,
        'note' => 'Original note',
        'location' => Location::OTHER,
    ]);

    actingAs($user);

    $entries = collect(range(0, 13))->map(function ($offset) use ($date) {
        return [
            'id' => null,
            'entry_date' => $date->copy()->addDays($offset)->format('Y-m-d'),
            'note' => 'Updated note '.$offset,
            'location' => Location::JWS->value,
            'is_available' => true,
        ];
    })->toArray();

    // Set the id for the first entry (the one we created above)
    $entries[0]['id'] = $created->id;

    Livewire::test(\App\Livewire\HomePage::class)
        ->set('entries', $entries)
        ->call('save')
        ->assertOk();

    // Should still only have 14 entries (not duplicates)
    expect(PlanEntry::where('user_id', $user->id)->count())->toBe(14);

    $updatedEntry = PlanEntry::where('user_id', $user->id)
        ->where('entry_date', $date)
        ->first();

    expect($updatedEntry->note)->toBe('Updated note 0');
    expect($updatedEntry->location)->toBe(Location::JWS);
});

test('copy next copies entry to next day only', function () {
    $user = User::factory()->create();

    actingAs($user);

    $entries = collect(range(0, 13))->map(function ($offset) {
        $date = now()->startOfWeek()->addDays($offset);

        return [
            'id' => null,
            'entry_date' => $date->format('Y-m-d'),
            'note' => '',
            'location' => '',
            'is_available' => true,
        ];
    })->toArray();

    $entries[0]['note'] = 'First day task';
    $entries[0]['location'] = Location::OTHER->value;

    Livewire::test(\App\Livewire\HomePage::class)
        ->set('entries', $entries)
        ->call('copyNext', 0)
        ->assertSet('entries.1.note', 'First day task')
        ->assertSet('entries.1.location', Location::OTHER->value)
        ->assertSet('entries.2.note', '');
});

test('copy rest copies entry to all remaining days', function () {
    $user = User::factory()->create();

    actingAs($user);

    $entries = collect(range(0, 13))->map(function ($offset) {
        $date = now()->startOfWeek()->addDays($offset);

        return [
            'id' => null,
            'entry_date' => $date->format('Y-m-d'),
            'note' => '',
            'location' => '',
            'is_available' => true,
        ];
    })->toArray();

    $entries[0]['note'] = 'Same task all week';
    $entries[0]['location'] = Location::RANKINE->value;

    $component = Livewire::test(\App\Livewire\HomePage::class)
        ->set('entries', $entries)
        ->call('copyRest', 0);

    // Check all remaining days were copied
    for ($i = 1; $i < 14; $i++) {
        $component->assertSet("entries.{$i}.note", 'Same task all week')
            ->assertSet("entries.{$i}.location", Location::RANKINE->value);
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
            'location' => '', // Empty location should fail when available
            'is_available' => true,
        ];
    })->toArray();

    Livewire::test(\App\Livewire\HomePage::class)
        ->set('entries', $entries)
        ->call('save')
        ->assertHasErrors(['entries.0.location']);
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
            'location' => '', // Empty location is OK when unavailable
            'is_available' => false,
        ];
    })->toArray();

    Livewire::test(\App\Livewire\HomePage::class)
        ->set('entries', $entries)
        ->call('save')
        ->assertHasNoErrors();

    expect(PlanEntry::where('user_id', $user->id)->count())->toBe(14);

    $firstEntry = PlanEntry::where('user_id', $user->id)->first();
    expect($firstEntry->is_available)->toBeFalse();
    expect($firstEntry->location)->toBeNull();
});

test('validation allows empty note field', function () {
    $user = User::factory()->create();

    actingAs($user);

    $entries = collect(range(0, 13))->map(function ($offset) {
        $date = now()->startOfWeek()->addDays($offset);

        return [
            'id' => null,
            'entry_date' => $date->format('Y-m-d'),
            'note' => '', // Empty note should be allowed
            'location' => Location::OTHER->value,
            'is_available' => true,
        ];
    })->toArray();

    Livewire::test(\App\Livewire\HomePage::class)
        ->set('entries', $entries)
        ->call('save')
        ->assertHasNoErrors();

    expect(PlanEntry::where('user_id', $user->id)->count())->toBe(14);
});

test('existing entries are loaded on mount', function () {
    $user = User::factory()->create();
    $date = now()->startOfWeek();

    PlanEntry::factory()->create([
        'user_id' => $user->id,
        'entry_date' => $date,
        'note' => 'Existing task',
        'location' => Location::BO,
    ]);

    actingAs($user);

    Livewire::test(\App\Livewire\HomePage::class)
        ->assertSet('entries.0.note', 'Existing task')
        ->assertSet('entries.0.location', Location::BO->value);
});

test('new entries use user defaults', function () {
    $user = User::factory()->create([
        'default_location' => Location::OTHER->value,
        'default_category' => 'Support Tickets',
    ]);

    actingAs($user);

    Livewire::test(\App\Livewire\HomePage::class)
        ->assertSet('entries.0.note', 'Support Tickets')
        ->assertSet('entries.0.location', Location::OTHER->value)
        ->assertSet('entries.13.note', 'Support Tickets')
        ->assertSet('entries.13.location', Location::OTHER->value);
});

test('existing entries override user defaults', function () {
    $user = User::factory()->create([
        'default_location' => Location::OTHER->value,
        'default_category' => 'Support Tickets',
    ]);

    $date = now()->startOfWeek();

    PlanEntry::factory()->create([
        'user_id' => $user->id,
        'entry_date' => $date,
        'note' => 'Specific task',
        'location' => Location::JWS,
    ]);

    actingAs($user);

    Livewire::test(\App\Livewire\HomePage::class)
        ->assertSet('entries.0.note', 'Specific task')
        ->assertSet('entries.0.location', Location::JWS->value)
        ->assertSet('entries.1.note', 'Support Tickets')
        ->assertSet('entries.1.location', Location::OTHER->value);
});

test('is_available checkbox saves correctly', function () {
    $user = User::factory()->create();

    actingAs($user);

    $entries = collect(range(0, 13))->map(function ($offset) {
        $date = now()->startOfWeek()->addDays($offset);

        return [
            'id' => null,
            'entry_date' => $date->format('Y-m-d'),
            'note' => 'Test note',
            'location' => Location::OTHER->value,
            'is_available' => $offset % 2 === 0, // Alternate true/false
        ];
    })->toArray();

    Livewire::test(\App\Livewire\HomePage::class)
        ->set('entries', $entries)
        ->call('save')
        ->assertOk();

    expect(PlanEntry::where('user_id', $user->id)->count())->toBe(14);

    $firstEntry = PlanEntry::where('user_id', $user->id)->where('entry_date', now()->startOfWeek())->first();
    expect($firstEntry->is_available)->toBeTrue();

    $secondEntry = PlanEntry::where('user_id', $user->id)->where('entry_date', now()->startOfWeek()->addDay())->first();
    expect($secondEntry->is_available)->toBeFalse();
});

test('copy next includes is_available', function () {
    $user = User::factory()->create();

    actingAs($user);

    $entries = collect(range(0, 13))->map(function ($offset) {
        $date = now()->startOfWeek()->addDays($offset);

        return [
            'id' => null,
            'entry_date' => $date->format('Y-m-d'),
            'note' => '',
            'location' => '',
            'is_available' => true,
        ];
    })->toArray();

    $entries[0]['note'] = 'First day task';
    $entries[0]['location'] = Location::OTHER->value;
    $entries[0]['is_available'] = false;

    Livewire::test(\App\Livewire\HomePage::class)
        ->set('entries', $entries)
        ->call('copyNext', 0)
        ->assertSet('entries.1.note', 'First day task')
        ->assertSet('entries.1.location', Location::OTHER->value)
        ->assertSet('entries.1.is_available', false)
        ->assertSet('entries.2.is_available', true);
});

test('copy rest includes is_available', function () {
    $user = User::factory()->create();

    actingAs($user);

    $entries = collect(range(0, 13))->map(function ($offset) {
        $date = now()->startOfWeek()->addDays($offset);

        return [
            'id' => null,
            'entry_date' => $date->format('Y-m-d'),
            'note' => '',
            'location' => '',
            'is_available' => true,
        ];
    })->toArray();

    $entries[0]['note'] = 'Same task all week';
    $entries[0]['location'] = Location::RANKINE->value;
    $entries[0]['is_available'] = false;

    $component = Livewire::test(\App\Livewire\HomePage::class)
        ->set('entries', $entries)
        ->call('copyRest', 0);

    for ($i = 1; $i < 14; $i++) {
        $component->assertSet("entries.{$i}.note", 'Same task all week')
            ->assertSet("entries.{$i}.location", Location::RANKINE->value)
            ->assertSet("entries.{$i}.is_available", false);
    }
});

test('existing entries load is_available value', function () {
    $user = User::factory()->create();
    $date = now()->startOfWeek();

    PlanEntry::factory()->create([
        'user_id' => $user->id,
        'entry_date' => $date,
        'note' => 'Unavailable task',
        'location' => Location::OTHER,
        'is_available' => false,
    ]);

    actingAs($user);

    Livewire::test(\App\Livewire\HomePage::class)
        ->assertSet('entries.0.is_available', false)
        ->assertSet('entries.1.is_available', true);
});

<?php

use App\Enums\AvailabilityStatus;
use App\Models\Location;
use App\Models\PlanEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('fills every empty weekday in the fortnight with the users own defaults', function () {
    $location = Location::factory()->create();
    $user = User::factory()->create([
        'default_location_id' => $location->id,
        'default_availability_status' => AvailabilityStatus::REMOTE,
        'default_category' => 'Support tickets',
    ]);
    $monday = now()->startOfWeek();

    $filledCount = $user->fillPlanFromDefaults($monday);

    expect($filledCount)->toBe(10);
    $entries = $user->planEntries()->get();
    expect($entries)->toHaveCount(10);
    foreach ($entries as $entry) {
        expect($entry->entry_date->isWeekday())->toBeTrue();
        expect($entry->location_id)->toBe($location->id);
        expect($entry->availability_status)->toBe(AvailabilityStatus::REMOTE);
        expect($entry->note)->toBe('Support tickets');
    }
});

test('existing entries in the window are left untouched', function () {
    $location = Location::factory()->create();
    $user = User::factory()->create([
        'default_location_id' => $location->id,
        'default_availability_status' => AvailabilityStatus::ONSITE,
    ]);
    $monday = now()->startOfWeek();
    $handEnteredEntry = PlanEntry::factory()->unavailable()->create([
        'user_id' => $user->id,
        'entry_date' => $monday->copy()->addDays(2),
        'note' => 'On holiday',
    ]);

    $filledCount = $user->fillPlanFromDefaults($monday);

    expect($filledCount)->toBe(9);
    expect($user->planEntries()->count())->toBe(10);
    $untouched = $handEnteredEntry->fresh();
    expect($untouched->note)->toBe('On holiday');
    expect($untouched->availability_status)->toBe(AvailabilityStatus::NOT_AVAILABLE);
    expect($untouched->location_id)->toBeNull();
});

test('a dry run reports the count without creating anything', function () {
    $location = Location::factory()->create();
    $user = User::factory()->create([
        'default_location_id' => $location->id,
    ]);
    $monday = now()->startOfWeek();
    PlanEntry::factory()->create([
        'user_id' => $user->id,
        'entry_date' => $monday->copy()->addDays(1),
    ]);

    $wouldFillCount = $user->fillPlanFromDefaults($monday, dryRun: true);

    expect($wouldFillCount)->toBe(9);
    expect($user->planEntries()->count())->toBe(1);
});

test('a user with no default location and an available default status has nothing usable to fill with', function () {
    $userWithoutDefaults = User::factory()->create([
        'default_location_id' => null,
        'default_availability_status' => AvailabilityStatus::ONSITE,
    ]);

    $filledCount = $userWithoutDefaults->fillPlanFromDefaults(now()->startOfWeek());

    expect($userWithoutDefaults->hasUsableDefaults())->toBeFalse();
    expect($filledCount)->toBe(0);
    expect($userWithoutDefaults->planEntries()->count())->toBe(0);
});

test('a not-available default with no location fills days as not available', function () {
    $unavailableUser = User::factory()->create([
        'default_location_id' => null,
        'default_availability_status' => AvailabilityStatus::NOT_AVAILABLE,
    ]);

    $filledCount = $unavailableUser->fillPlanFromDefaults(now()->startOfWeek());

    expect($filledCount)->toBe(10);
    $entries = $unavailableUser->planEntries()->get();
    expect($entries)->toHaveCount(10);
    foreach ($entries as $entry) {
        expect($entry->availability_status)->toBe(AvailabilityStatus::NOT_AVAILABLE);
        expect($entry->location_id)->toBeNull();
    }
});

test('created by manager is stored as passed and category and holiday keep their defaults', function () {
    $location = Location::factory()->create();
    $user = User::factory()->create(['default_location_id' => $location->id]);
    $monday = now()->startOfWeek();

    $user->fillPlanFromDefaults($monday, createdByManager: true);

    $entry = $user->planEntries()->first();
    expect($entry->created_by_manager)->toBeTrue();
    expect($entry->category)->toBeNull();
    expect($entry->is_holiday)->toBeFalse();

    $selfFillUser = User::factory()->create(['default_location_id' => $location->id]);
    $selfFillUser->fillPlanFromDefaults($monday);

    expect($selfFillUser->planEntries()->first()->created_by_manager)->toBeFalse();
});

test('the single day helper fills an empty weekday and refuses weekends, planned days and unusable defaults', function () {
    $location = Location::factory()->create();
    $user = User::factory()->create(['default_location_id' => $location->id]);
    $monday = now()->startOfWeek();
    $saturday = $monday->copy()->addDays(5);

    expect($user->fillPlanDayFromDefaults($monday))->toBeTrue();
    $entry = $user->planEntries()->first();
    expect($entry->entry_date->format('Y-m-d'))->toBe($monday->format('Y-m-d'));
    expect($entry->location_id)->toBe($location->id);

    expect($user->fillPlanDayFromDefaults($monday))->toBeFalse();
    expect($user->fillPlanDayFromDefaults($saturday))->toBeFalse();
    expect($user->planEntries()->count())->toBe(1);

    $userWithoutDefaults = User::factory()->create(['default_location_id' => null]);
    expect($userWithoutDefaults->fillPlanDayFromDefaults($monday))->toBeFalse();
    expect($userWithoutDefaults->planEntries()->count())->toBe(0);
});

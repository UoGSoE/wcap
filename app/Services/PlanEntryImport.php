<?php

namespace App\Services;

use App\Enums\AvailabilityStatus;
use App\Models\Location;
use App\Models\PlanEntry;
use App\Models\User;
use Illuminate\Support\Carbon;

class PlanEntryImport
{
    public function __construct(protected array $rows) {}

    public function import(): array
    {
        $errors = [];
        $validator = new PlanEntryRowValidator;

        foreach ($this->rows as $index => $row) {
            $result = $validator->validate($row);

            if ($result->fails()) {
                if ($index === 0) {
                    continue;
                }
                $errors[] = "Row {$index}: ".$result->errors()->first();

                continue;
            }

            $validated = $result->safe();
            $user = User::where('email', $validated['email'])->first();
            $entryDate = Carbon::createFromFormat('d/m/Y', $validated['date']);

            // Use provided availability, or fall back to user's default
            $availabilityStatus = null;
            if (! empty($validated['availability_status'])) {
                $availabilityStatus = match ($validated['availability_status']) {
                    'O' => AvailabilityStatus::ONSITE,
                    'R' => AvailabilityStatus::REMOTE,
                    'N' => AvailabilityStatus::NOT_AVAILABLE,
                };
            } else {
                $availabilityStatus = $user->default_availability_status;
                if (! $availabilityStatus) {
                    $errors[] = "Row {$index}: No availability provided and user has no default availability set.";

                    continue;
                }
            }

            // Use provided location, or fall back to user's default
            $location = null;
            if (! empty($validated['location'])) {
                $location = Location::where('slug', $validated['location'])->first();
            } else {
                $location = $user->defaultLocation;
                if (! $location) {
                    $errors[] = "Row {$index}: No location provided and user has no default location set.";

                    continue;
                }
            }

            // Use provided note, or fall back to user's default category
            $note = ! empty($validated['note']) ? $validated['note'] : ($user->default_category ?? '');

            PlanEntry::updateOrCreate(
                ['user_id' => $user->id, 'entry_date' => $entryDate],
                [
                    'note' => $note,
                    'location_id' => $location->id,
                    'availability_status' => $availabilityStatus,
                    'created_by_manager' => true,
                ]
            );
        }

        return $errors;
    }
}

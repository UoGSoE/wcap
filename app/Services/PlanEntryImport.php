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
            $location = Location::where('slug', $validated['location'])->first();

            $availabilityStatus = match ($validated['availability_status']) {
                'O' => AvailabilityStatus::ONSITE,
                'R' => AvailabilityStatus::REMOTE,
                'N' => AvailabilityStatus::NOT_AVAILABLE,
                default => AvailabilityStatus::ONSITE,
            };

            PlanEntry::updateOrCreate(
                ['user_id' => $user->id, 'entry_date' => $entryDate],
                [
                    'note' => $validated['note'],
                    'location_id' => $location?->id,
                    'availability_status' => $availabilityStatus,
                    'created_by_manager' => true,
                ]
            );
        }

        return $errors;
    }
}

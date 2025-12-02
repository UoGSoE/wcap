<?php

namespace App\Services;

use App\Models\User;
use App\Enums\Location;
use App\Models\PlanEntry;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;

class PlanEntryImport
{
    /**
     * Create a new class instance.
     */
    public function __construct(protected array $rows)
    {
    }

    public function import(): array
    {
        $errors = [];

        foreach ($this->rows as $index => $row) {
            $rowData = [
                'email' => $row[0] ?? '',
                'date' => $row[1] ?? '',
                'location' => $row[2] ?? '',
                'note' => $row[3] ?? '',
                'is_available' => $row[4] ?? '',
            ];
            if ($rowData['date'] instanceof Carbon) {
                $rowData['date'] = $rowData['date']->format('d/m/Y');
            }

            $validator = Validator::make(
                $rowData,
                [
                    'email' => 'required|email|exists:users,email',
                    'date' => 'required|date_format:d/m/Y',
                    'location' => ['required', Rule::enum(Location::class)],
                    'note' => 'nullable',
                    'is_available' => 'required|in:Y,N',
                ],
            );

            if ($validator->fails()) {
                if ($index === 0) {
                    continue;
                }
                $errors[] = "Row {$index}: " . $validator->errors()->first();
                continue;
            }

            $validated = $validator->safe();

            $user = User::where('email', $validated['email'])->first();

            PlanEntry::updateOrCreate(
                ['user_id' => $user->id, 'entry_date' => $validated['date']],
                [
                    'note' => $validated['note'],
                    'location' => $validated['location'],
                    'is_available' => $validated['is_available'] === 'Y',
                    'created_by_manager' => true,
                ]
            );
        }

        return $errors;
    }
}

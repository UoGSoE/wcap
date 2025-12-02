<?php

namespace App\Services;

use App\Enums\Location;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class PlanEntryRowValidator
{
    public function validate(array $row): \Illuminate\Validation\Validator
    {
        $date = $row[1] ?? '';
        if ($date instanceof Carbon) {
            $date = $date->format('d/m/Y');
        }

        $rowData = [
            'email' => $row[0] ?? '',
            'date' => $date,
            'location' => $row[2] ?? '',
            'note' => $row[3] ?? '',
            'is_available' => $row[4] ?? '',
        ];

        return Validator::make($rowData, [
            'email' => 'required|email|exists:users,email',
            'date' => 'required|date_format:d/m/Y',
            'location' => ['required', Rule::enum(Location::class)],
            'note' => 'nullable',
            'is_available' => 'required|in:Y,N',
        ]);
    }
}

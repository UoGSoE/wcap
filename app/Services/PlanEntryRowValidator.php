<?php

namespace App\Services;

use DateTimeImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class PlanEntryRowValidator
{
    public function validate(array $row): \Illuminate\Validation\Validator
    {
        $date = $row[1] ?? '';
        if ($date instanceof Carbon || $date instanceof DateTimeImmutable) {
            $date = $date->format('d/m/Y');
        }

        $rowData = [
            'email' => $row[0] ?? '',
            'date' => $date,
            'location' => $row[2] ?? '',
            'note' => $row[3] ?? '',
            'availability_status' => strtoupper($row[4] ?? ''),
        ];

        $rules = [
            'email' => 'required|email|exists:users,email',
            'date' => 'required|date_format:d/m/Y',
            'location' => 'nullable',
            'note' => 'nullable',
            'availability_status' => 'nullable|in:O,R,N,', // O=Onsite, R=Remote, N=Not available, empty=skip row
        ];

        // Only validate location exists if one is provided
        if ($rowData['location'] !== '') {
            $rules['location'] = ['nullable', Rule::exists('locations', 'slug')];
        }

        return Validator::make($rowData, $rules);
    }
}

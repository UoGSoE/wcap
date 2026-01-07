<?php

namespace App\Http\Requests;

use App\Enums\AvailabilityStatus;
use App\Enums\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ManagerUpsertPlanEntriesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled in controller
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $targetUserId = $this->route('userId');

        return [
            'entries' => ['required', 'array'],
            'entries.*.id' => [
                'nullable',
                'integer',
                Rule::exists('plan_entries', 'id')->where('user_id', $targetUserId),
            ],
            'entries.*.entry_date' => ['required', 'date'],
            'entries.*.location' => ['required', 'string', Rule::exists('locations', 'slug')],
            'entries.*.note' => ['nullable', 'string'],
            'entries.*.availability_status' => ['nullable', 'integer', Rule::enum(AvailabilityStatus::class)],
            'entries.*.is_holiday' => ['nullable', 'boolean'],
            'entries.*.category' => ['nullable', 'string', Rule::enum(Category::class)],
        ];
    }
}

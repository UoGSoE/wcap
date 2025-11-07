<?php

namespace App\Http\Requests;

use App\Enums\Category;
use App\Enums\Location;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertPlanEntriesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $user = $this->user();

        return [
            'entries' => ['required', 'array'],
            'entries.*.id' => [
                'nullable',
                'integer',
                Rule::exists('plan_entries', 'id')->where('user_id', $user->id),
            ],
            'entries.*.entry_date' => ['required', 'date'],
            'entries.*.location' => ['required', 'string', Rule::enum(Location::class)],
            'entries.*.note' => ['nullable', 'string'],
            'entries.*.is_available' => ['nullable', 'boolean'],
            'entries.*.is_holiday' => ['nullable', 'boolean'],
            'entries.*.category' => ['nullable', 'string', Rule::enum(Category::class)],
        ];
    }
}

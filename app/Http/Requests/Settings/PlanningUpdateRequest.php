<?php

namespace App\Http\Requests\Settings;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validate the planning settings: the first day of the week, from 0
 * (Sunday) to 6 (Saturday).
 */
class PlanningUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'week_start' => ['required', 'integer', 'between:0,6'],
        ];
    }
}

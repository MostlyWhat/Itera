<?php

namespace App\Http\Requests\Areas;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validate a new priority order of the current team's Areas: a list of
 * distinct Area ids of that team, most important first.
 */
class OrderAreasRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'area_ids' => ['required', 'array', 'max:500'],
            'area_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('areas', 'id')->where('team_id', $this->user()->currentTeam->id),
            ],
        ];
    }

    /**
     * Get the custom messages for the validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'area_ids.*.exists' => __('One of the areas is not in this team. Reload the page and try again.'),
        ];
    }
}

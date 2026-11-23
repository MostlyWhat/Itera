<?php

namespace App\Http\Requests\Areas;

use App\Models\Area;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validate a new priority order of the Projects in one Area: a list of
 * distinct Project ids of that Area, most important first.
 */
class OrderProjectsRequest extends FormRequest
{
    /**
     * Only members of the team that owns the Area can change its order. This
     * runs before the validation, so another user gets 403.
     */
    public function authorize(): bool
    {
        $area = $this->route('area');

        return $area instanceof Area && $this->user()->can('update', $area);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Area $area */
        $area = $this->route('area');

        return [
            'project_ids' => ['required', 'array', 'max:500'],
            'project_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('projects', 'id')->where('area_id', $area->id),
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
            'project_ids.*.exists' => __('One of the projects is not in this area. Reload the page and try again.'),
        ];
    }
}

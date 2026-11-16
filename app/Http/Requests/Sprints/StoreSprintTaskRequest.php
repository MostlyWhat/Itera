<?php

namespace App\Http\Requests\Sprints;

use App\Http\Requests\Tasks\SaveTaskRequest;
use App\Models\Project;
use App\Models\Sprint;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validate a new Task that the planning page creates directly in a Sprint.
 * It uses the normal task rules, plus an active Project of the Sprint's team.
 */
class StoreSprintTaskRequest extends SaveTaskRequest
{
    /**
     * Only team members can change an open Sprint whose week has not ended.
     * This runs before the validation, so another user gets 403.
     */
    public function authorize(): bool
    {
        $sprint = $this->route('sprint');

        return $sprint instanceof Sprint && $this->user()->can('update', $sprint);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = parent::rules();
        unset($rules['status']);

        return [
            ...$rules,
            'project_id' => [
                'required',
                'integer',
                function (string $attribute, mixed $value, Closure $fail) {
                    $sprint = $this->route('sprint');

                    $belongs = $sprint instanceof Sprint && Project::query()
                        ->whereKey($value)
                        ->active()
                        ->whereHas('area', fn ($areas) => $areas->active()->where('team_id', $sprint->team_id))
                        ->exists();

                    if (! $belongs) {
                        $fail(__('Choose one of your active projects.'));
                    }
                },
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
            ...parent::messages(),
            'project_id.required' => __('Choose a project for the task.'),
        ];
    }
}

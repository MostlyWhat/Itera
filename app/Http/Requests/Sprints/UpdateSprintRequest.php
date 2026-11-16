<?php

namespace App\Http\Requests\Sprints;

use App\Models\Sprint;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Validate a change to the goal, the target, or the chosen Tasks of a Sprint.
 * The week of a Sprint does not change.
 */
class UpdateSprintRequest extends FormRequest
{
    use SprintTaskRules;

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
        return $this->planRules();
    }

    /**
     * Get the "after" validation callables for the request.
     *
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            $sprint = $this->route('sprint');

            if ($sprint instanceof Sprint) {
                $this->checkTasks($validator, $sprint->team, $sprint);
            }
        }];
    }
}

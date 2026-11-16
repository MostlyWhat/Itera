<?php

namespace App\Http\Requests\Sprints;

use App\Models\Sprint;
use App\Models\Team;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Validate a new Sprint: a Monday in the current week or later that the team
 * has not planned yet, plus the goal, the target, and the chosen Tasks.
 */
class StoreSprintRequest extends FormRequest
{
    use SprintTaskRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'week_start' => [
                'required',
                'date_format:Y-m-d',
                function (string $attribute, mixed $value, Closure $fail) {
                    if (! CarbonImmutable::parse($value)->isMonday()) {
                        $fail(__('A sprint starts on a Monday.'));
                    }
                },
                'after_or_equal:'.today()->startOfWeek()->toDateString(),
                // Compare the date only. SQLite stores the date with a time,
                // so a plain unique rule would not see the existing week.
                function (string $attribute, mixed $value, Closure $fail) {
                    $planned = Sprint::query()
                        ->where('team_id', $this->team()->id)
                        ->whereDate('week_start', $value)
                        ->exists();

                    if ($planned) {
                        $fail(__('You already have a sprint for this week.'));
                    }
                },
            ],
            ...$this->planRules(),
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
            'week_start.after_or_equal' => __('Plan this week or a later week.'),
        ];
    }

    /**
     * Get the "after" validation callables for the request.
     *
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [fn (Validator $validator) => $this->checkTasks($validator, $this->team(), null)];
    }

    /**
     * The current team of the user, from the team URL.
     */
    private function team(): Team
    {
        return $this->user()->currentTeam;
    }
}

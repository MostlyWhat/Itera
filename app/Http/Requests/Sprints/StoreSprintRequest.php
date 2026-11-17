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
 * Validate a new Sprint: the first day of a week (by the user's week start),
 * in the current week or later, that the team
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
                // A sprint starts on the first day of the user's week.
                function (string $attribute, mixed $value, Closure $fail) {
                    $day = CarbonImmutable::parse($value);

                    if ($day->dayOfWeek !== ($this->user()->week_start ?? 1)) {
                        $fail(__('A sprint starts on a :day. You can change the first day of the week in Settings.', [
                            'day' => $this->user()->startOfWeek()->englishDayOfWeek,
                        ]));
                    }
                },
                'after_or_equal:'.$this->user()->startOfWeek()->toDateString(),
                // No two sprints of a team share a day. Compare dates only:
                // SQLite stores the date with a time.
                function (string $attribute, mixed $value, Closure $fail) {
                    $start = CarbonImmutable::parse($value);

                    $overlaps = Sprint::query()
                        ->where('team_id', $this->team()->id)
                        ->whereDate('week_start', '<=', $start->addDays(6)->toDateString())
                        ->whereDate('week_end', '>=', $start->toDateString())
                        ->exists();

                    if ($overlaps) {
                        $fail(__('You already have a sprint in this week.'));
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

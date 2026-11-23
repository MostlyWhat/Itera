<?php

namespace App\Http\Requests\Tasks;

use App\Enums\StoryPoints;
use App\Enums\TaskStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validate a new or edited Task. Story points must be a Fibonacci value from 1 to 21.
 * The description, the deadline, and the story points are optional. A request
 * without a description keeps the current one.
 */
class SaveTaskRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'story_points' => ['nullable', 'integer', Rule::enum(StoryPoints::class)],
            'due_date' => ['nullable', 'date_format:Y-m-d'],
            'status' => ['sometimes', 'required', Rule::enum(TaskStatus::class)],
            // A Task can repeat each week on one weekday (ITERA-09).
            'repeat_weekday' => ['nullable', 'integer', 'between:0,6'],
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
            'story_points.enum' => __('Choose 1, 2, 3, 5, 8, 13, or 21 story points.'),
            'due_date.date_format' => __('Enter the deadline as a date.'),
            'repeat_weekday.between' => __('Choose a day from Sunday to Saturday.'),
        ];
    }

    /**
     * Prepare the data for validation. Empty form fields mean "no value".
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'title' => trim((string) $this->input('title')),
            'story_points' => $this->filled('story_points') ? $this->input('story_points') : null,
            'due_date' => $this->filled('due_date') ? $this->input('due_date') : null,
            'repeat_weekday' => $this->filled('repeat_weekday') ? $this->input('repeat_weekday') : null,
        ]);

        if ($this->has('description')) {
            $description = trim((string) $this->input('description'));
            $this->merge(['description' => $description === '' ? null : $description]);
        }
    }
}

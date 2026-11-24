<?php

namespace App\Http\Requests\Sprints;

use App\Enums\ClosingStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validate a Sprint review (ITERA-10): the three retrospective answers, all
 * optional, a choice for each unfinished Task, and an optional target for
 * the next Sprint.
 */
class ReviewSprintRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'went_well' => ['nullable', 'string', 'max:2000'],
            'blockers' => ['nullable', 'string', 'max:2000'],
            'improve_next' => ['nullable', 'string', 'max:2000'],
            'next_target' => ['nullable', 'integer', 'min:1', 'max:999'],
            'decisions' => ['array'],
            'decisions.*' => [Rule::in([ClosingStatus::CarriedOver->value, ClosingStatus::Dropped->value])],
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
            'decisions.*.in' => __('Choose Plan again or Back to backlog.'),
            'next_target.min' => __('Enter a target of 1 point or more.'),
            'next_target.max' => __('Enter a target of 999 points or less.'),
        ];
    }

    /**
     * Prepare the data for validation. Empty answers mean "no answer".
     */
    protected function prepareForValidation(): void
    {
        foreach (['went_well', 'blockers', 'improve_next'] as $field) {
            $value = trim((string) $this->input($field));
            $this->merge([$field => $value === '' ? null : $value]);
        }

        $this->merge(['next_target' => $this->filled('next_target') ? $this->input('next_target') : null]);
    }
}

<?php

namespace App\Http\Requests\Subtasks;

use App\Models\Subtask;
use App\Models\Task;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validate a new or edited Subtask. A new Subtask needs a title. An edit can
 * change the title, the done state, or both.
 */
class SaveSubtaskRequest extends FormRequest
{
    /**
     * Only members of the team that owns the parent Task can change its
     * Subtasks. This runs before the validation, so another user gets 403.
     */
    public function authorize(): bool
    {
        $subtask = $this->route('subtask');
        $task = $subtask instanceof Subtask ? $subtask->task : $this->route('task');

        return $task instanceof Task && $this->user()->can('update', $task);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $creating = $this->route('subtask') === null;

        return [
            'title' => [$creating ? 'required' : 'sometimes', 'string', 'max:200'],
            'completed' => ['sometimes', 'boolean'],
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
            'title.required' => __('Enter a name for the subtask.'),
        ];
    }

    /**
     * Prepare the data for validation. Remove the spaces around the title.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('title')) {
            $this->merge(['title' => trim((string) $this->input('title'))]);
        }
    }
}

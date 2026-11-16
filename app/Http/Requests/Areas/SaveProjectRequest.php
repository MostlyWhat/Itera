<?php

namespace App\Http\Requests\Areas;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validate the name and the optional description of a Project. A form that
 * sends only the name keeps the current description.
 */
class SaveProjectRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge(['name' => trim((string) $this->input('name'))]);

        if ($this->has('description')) {
            $description = trim((string) $this->input('description'));
            $this->merge(['description' => $description === '' ? null : $description]);
        }
    }
}

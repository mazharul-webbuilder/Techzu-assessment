<?php

namespace App\Http\Requests;

use App\Enums\Priority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangePriorityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'priority' => ['required', Rule::enum(Priority::class)],
        ];
    }

    public function priorityEnum(): Priority
    {
        return Priority::from($this->validated('priority'));
    }
}

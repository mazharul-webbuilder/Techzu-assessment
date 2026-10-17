<?php

namespace App\Http\Requests\Customer;

use App\Enums\Priority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StoreTicketRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth('customer')->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $maxFiles = config('helpdesk.attachments.max_files', 5);
        $maxKb = config('helpdesk.attachments.max_kb', 5120);
        $mimes = implode(',', config('helpdesk.attachments.mimes', ['pdf', 'png', 'jpg', 'jpeg', 'txt', 'docx']));

        return [
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'priority' => ['required', new Enum(Priority::class)],
            'category_id' => [
                'required',
                'integer',
                Rule::exists('categories', 'id')->where('is_active', true),
            ],
            'attachments' => ['nullable', 'array', "max:{$maxFiles}"],
            'attachments.*' => ['file', "max:{$maxKb}", "mimes:{$mimes}"],
        ];
    }

    /**
     * Custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        $maxFiles = config('helpdesk.attachments.max_files', 5);
        $maxKb = config('helpdesk.attachments.max_kb', 5120);
        $maxMb = round($maxKb / 1024, 1);
        $mimes = implode(', ', config('helpdesk.attachments.mimes', ['pdf', 'png', 'jpg', 'jpeg', 'txt', 'docx']));

        return [
            'category_id.exists' => 'The selected category is invalid or currently inactive.',
            'priority.Illuminate\Validation\Rules\Enum' => 'Please select a valid ticket priority level.',
            'attachments.max' => "You may not upload more than {$maxFiles} attachments.",
            'attachments.*.max' => "Each attachment file size must not exceed {$maxMb}MB.",
            'attachments.*.mimes' => "Allowed attachment file formats: {$mimes}.",
        ];
    }
}

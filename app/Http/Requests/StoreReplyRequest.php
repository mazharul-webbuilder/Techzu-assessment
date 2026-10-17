<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreReplyRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $maxKb = config('helpdesk.attachments.max_kb', 5120);
        $mimes = implode(',', config('helpdesk.attachments.mimes', ['pdf', 'png', 'jpg', 'jpeg', 'txt', 'docx']));
        $maxFiles = config('helpdesk.attachments.max_files', 5);

        return [
            'body' => ['required', 'string', 'max:10000'],
            'is_internal' => ['sometimes', 'boolean'],
            'attachments' => ['sometimes', 'array', "max:{$maxFiles}"],
            'attachments.*' => ['file', "max:{$maxKb}", "mimes:{$mimes}"],
        ];
    }

    /**
     * Determine whether the reply is flagged as internal note.
     * Enforced strictly server-side: customers CANNOT author internal notes.
     */
    public function isInternal(): bool
    {
        // Only agents and admins may post internal notes
        if ($this->user('agent') || $this->user('admin')) {
            return $this->boolean('is_internal');
        }

        return false;
    }
}

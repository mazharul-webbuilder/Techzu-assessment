<?php

namespace App\Http\Requests;

use App\Enums\Priority;
use App\Enums\TicketStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TicketIndexRequest extends FormRequest
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
        $statusValues = array_merge(
            TicketStatus::values(),
            ['active', 'resolved_closed']
        );

        $priorityValues = array_map(fn ($case) => $case->value, Priority::cases());

        return [
            'ticket_number' => ['nullable', 'string', 'max:50'],
            'search' => ['nullable', 'string', 'max:100'],
            'keyword' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', Rule::in($statusValues)],
            'priority' => ['nullable', 'string', Rule::in($priorityValues)],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'category' => ['nullable'],
            'agent_id' => ['nullable', 'string'],
            'assigned_agent' => ['nullable', 'string'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'sla_status' => ['nullable', 'string', Rule::in(['within', 'approaching', 'breached'])],
            'escalated' => ['nullable'],
            'tab' => ['nullable', 'string', Rule::in(['all', 'my', 'unassigned', 'active', 'resolved'])],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ];
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PublishNotificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Assume all authenticated users can publish; adjust as needed.
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id'      => ['required', 'integer', 'exists:users,id'],
            'type'         => ['required', 'string', Rule::in(['email', 'sms', 'push'])],
            'payload'      => ['required', 'array'],
            'scheduled_at' => ['nullable', 'date', 'after_or_equal:now'],
        ];
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
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

    public function messages(): array
    {
        return [
            'user_id.required'      => 'The user ID is required.',
            'user_id.integer'       => 'The user ID must be an integer.',
            'user_id.exists'        => 'The specified user does not exist.',

            'type.required'         => 'The notification type is required.',
            'type.string'           => 'The notification type must be a string.',
            'type.in'               => 'The notification type must be one of: email, sms, or push.',

            'payload.required'      => 'The payload is required.',
            'payload.array'         => 'The payload must be a valid JSON object.',

            'scheduled_at.date'     => 'The scheduled time must be a valid date.',
            'scheduled_at.after_or_equal' => 'The scheduled time must be now or in the future.',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            response()->json([
                'status'  => 'error',
                'message' => 'Validation failed',
                'errors'  => $validator->errors(),
            ], 422)
        );
    }

    protected function prepareForValidation()
    {
        $this->merge([
            'user_id' => (int) $this->input('user_id'),
        ]);
    }

}

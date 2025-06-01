<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class FetchRecentNotificationsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // You can add more sophisticated authorization logic here
        // For example: return $this->user()->id == $this->input('user_id');
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'user_id'  => [
                'required',
                'integer',
                'exists:users,id',
                // Uncomment if you want to ensure users can only request their own notifications:
                // Rule::exists('users', 'id')->where(function ($query) {
                //     $query->where('id', $this->user()->id);
                // })
            ],
            'page'     => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * Custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'user_id.required' => 'The user ID is required.',
            'user_id.integer'  => 'The user ID must be an integer.',
            'user_id.exists'   => 'The specified user does not exist.',
            'page.integer'     => 'The page must be an integer.',
            'page.min'         => 'The page must be at least 1.',
            'per_page.integer' => 'The per_page must be an integer.',
            'per_page.min'     => 'The per_page must be at least 1.',
            'per_page.max'     => 'The per_page may not be greater than 100.',
        ];
    }

    /**
     * Handle a failed validation attempt.
     */
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

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation()
    {
        // Convert string numbers to integers if needed
        $this->merge([
            'user_id'  => (int) $this->input('user_id'),
            'page'     => $this->input('page') ? (int) $this->input('page') : 1,
            'per_page' => $this->input('per_page') ? (int) $this->input('per_page') : 15,
        ]);
    }
}

<?php

namespace App\Http\Requests\Registration;

use Illuminate\Foundation\Http\FormRequest;

class RegisterForEventRequest extends FormRequest
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
        $rules = [
            'ticket_type_id' => 'required|exists:ticket_types,id',
            'quantity' => 'nullable|integer|min:1|max:10',
            'custom_fields' => 'nullable|array',
            'guest_phone' => 'nullable|string|max:50',
        ];

        // Guest details required only for unauthenticated requests
        if (!$this->user()) {
            $rules['guest_name'] = 'required|string|max:255';
            $rules['guest_email'] = 'required|email|max:255';
        }

        return $rules;
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'guest_name.required_without' => 'Name is required for guest registrations.',
            'guest_email.required_without' => 'Email is required for guest registrations.',
        ];
    }
}

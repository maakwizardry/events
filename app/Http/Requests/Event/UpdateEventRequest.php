<?php

namespace App\Http\Requests\Event;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEventRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled by policy
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'organization_id' => 'sometimes|exists:organizations,id',
            'name' => 'sometimes|string|max:255',
            'slug' => 'nullable|string|max:255|alpha_dash',
            'description' => 'nullable|string|max:10000',
            'cover_image_url' => 'nullable|url|max:500',
            'visibility' => 'sometimes|in:public,private',
            'location_type' => 'sometimes|in:physical,online,hybrid',
            'location_address' => 'nullable|string|max:500',
            'location_city' => 'nullable|string|max:100',
            'location_state' => 'nullable|string|max:100',
            'location_country' => 'nullable|string|max:100',
            'location_zip' => 'nullable|string|max:20',
            'location_latitude' => 'nullable|numeric|between:-90,90',
            'location_longitude' => 'nullable|numeric|between:-180,180',
            'online_meeting_url' => 'nullable|url|max:500',
            'starts_at' => 'sometimes|date',
            'ends_at' => 'sometimes|date|after:starts_at',
            'timezone' => 'nullable|string|max:50',
            'capacity' => 'nullable|integer|min:1',
            'enable_waitlist' => 'nullable|boolean',
            'auto_approve_registrations' => 'nullable|boolean',
            'require_approval' => 'nullable|boolean',
            'registration_opens_at' => 'nullable|date',
            'registration_closes_at' => 'nullable|date|before:starts_at',
            'category' => 'nullable|string|in:conference,workshop,meetup,webinar,networking',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
            'status' => 'nullable|in:draft,published,cancelled,completed',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'ends_at.after' => 'The event must end after it starts.',
            'registration_closes_at.before' => 'Registration must close before the event starts.',
        ];
    }
}

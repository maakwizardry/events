<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'organization_id' => $this->organization_id,
            'organization' => new OrganizationResource($this->whenLoaded('organization')),
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'cover_image_url' => $this->cover_image_url,
            'visibility' => $this->visibility,
            'location_type' => $this->location_type,
            'location_address' => $this->location_address,
            'location_city' => $this->location_city,
            'location_state' => $this->location_state,
            'location_country' => $this->location_country,
            'location_zip' => $this->location_zip,
            'location_latitude' => $this->location_latitude,
            'location_longitude' => $this->location_longitude,
            'online_meeting_url' => $this->online_meeting_url,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'timezone' => $this->timezone,
            'capacity' => $this->capacity,
            'total_registered' => $this->total_registered,
            'available_spots' => $this->availableSpots(),
            'is_full' => $this->isFull(),
            'enable_waitlist' => $this->enable_waitlist,
            'auto_approve_registrations' => $this->auto_approve_registrations,
            'require_approval' => $this->require_approval,
            'registration_opens_at' => $this->registration_opens_at?->toIso8601String(),
            'registration_closes_at' => $this->registration_closes_at?->toIso8601String(),
            'is_registration_open' => $this->isRegistrationOpen(),
            'category' => $this->category,
            'tags' => $this->tags,
            'status' => $this->status,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),

            // Conditional relationships
            'ticket_types' => TicketTypeResource::collection($this->whenLoaded('ticketTypes')),
            'registrations_count' => $this->whenCounted('registrations'),
        ];
    }
}

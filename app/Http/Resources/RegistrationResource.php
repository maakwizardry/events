<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RegistrationResource extends JsonResource
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
            'event' => new EventResource($this->whenLoaded('event')),
            'ticket_type' => new TicketTypeResource($this->whenLoaded('ticketType')),
            'user_id' => $this->user_id,
            'attendee_name' => $this->attendeeName,
            'attendee_email' => $this->attendeeEmail,
            'status' => $this->status,
            'quantity' => $this->quantity,
            'total_price' => $this->total_price,
            'qr_code_data' => $this->qr_code_data,
            'qr_code_url' => $this->qr_code_path ? url('storage/' . $this->qr_code_path) : null,
            'is_checked_in' => $this->is_checked_in,
            'checked_in_at' => $this->checked_in_at?->toIso8601String(),
            'custom_fields' => $this->custom_fields,
            'registered_at' => $this->registered_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}

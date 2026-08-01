<?php

namespace App\Http\Controllers\API\V1\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Registration\RegisterForEventRequest;
use App\Http\Resources\RegistrationResource;
use App\Models\Event;
use App\Models\Registration;
use App\Models\TicketType;
use Illuminate\Support\Facades\DB;

class PublicRegistrationController extends Controller
{
    /**
     * Register for a public event (guest registration).
     */
    public function register(RegisterForEventRequest $request, Event $event)
    {
        // Only allow registration for public events
        if ($event->visibility !== 'public' || $event->status !== 'published') {
            return response()->json([
                'message' => 'Event is not available for public registration',
            ], 404);
        }

        // Check if event allows registration
        if (!$event->isRegistrationOpen()) {
            return response()->json([
                'message' => 'Registration is not open for this event',
            ], 422);
        }

        $ticketType = TicketType::findOrFail($request->ticket_type_id);

        // Verify ticket type belongs to this event
        if ($ticketType->event_id !== $event->id) {
            return response()->json([
                'message' => 'Ticket type does not belong to this event',
            ], 422);
        }

        // Check if ticket is on sale
        if (!$ticketType->isOnSale()) {
            return response()->json([
                'message' => 'This ticket type is not currently on sale',
            ], 422);
        }

        $quantity = $request->quantity ?? 1;

        // Use database transaction for capacity checking
        $registration = DB::transaction(function () use ($request, $event, $ticketType, $quantity) {
            // Refresh models to get latest data
            $event->refresh();
            $ticketType->refresh();

            $status = 'confirmed';

            // Check event capacity
            if ($event->capacity && ($event->total_registered + $quantity) > $event->capacity) {
                if (!$event->enable_waitlist) {
                    throw new \Exception('Event is full and waitlist is not enabled');
                }
                $status = 'waitlisted';
            }

            // Check ticket type capacity
            if ($ticketType->quantity && ($ticketType->quantity_sold + $quantity) > $ticketType->quantity) {
                if (!$event->enable_waitlist) {
                    throw new \Exception('Ticket type is sold out and waitlist is not enabled');
                }
                $status = 'waitlisted';
            }

            // Create registration (guest)
            $registration = Registration::create([
                'event_id' => $event->id,
                'ticket_type_id' => $ticketType->id,
                'user_id' => null, // Guest registration
                'guest_name' => $request->guest_name,
                'guest_email' => $request->guest_email,
                'guest_phone' => $request->guest_phone,
                'status' => $status,
                'quantity' => $quantity,
                'total_price' => $ticketType->price * $quantity,
                'custom_fields' => $request->custom_fields,
                'registered_at' => now(),
            ]);

            // Update counters only if confirmed
            if ($status === 'confirmed') {
                $event->increment('total_registered', $quantity);
                $ticketType->increment('quantity_sold', $quantity);
            }

            return $registration;
        });

        // Generate QR code
        $registration->generateQrCode();

        return new RegistrationResource($registration->load(['event', 'ticketType']));
    }

    /**
     * View registration by UUID (for guests).
     */
    public function show(Registration $registration)
    {
        return new RegistrationResource($registration->load(['event', 'ticketType']));
    }
}

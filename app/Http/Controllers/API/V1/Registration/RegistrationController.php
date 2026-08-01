<?php

namespace App\Http\Controllers\API\V1\Registration;

use App\Http\Controllers\Controller;
use App\Http\Requests\Registration\RegisterForEventRequest;
use App\Http\Resources\RegistrationResource;
use App\Models\Event;
use App\Models\Registration;
use App\Models\TicketType;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RegistrationController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display a listing of user's registrations.
     */
    public function index(Request $request)
    {
        $registrations = $request->user()
            ->registrations()
            ->with(['event', 'ticketType'])
            ->latest()
            ->paginate(20);

        return RegistrationResource::collection($registrations);
    }

    /**
     * Register for an event (authenticated user).
     */
    public function store(RegisterForEventRequest $request, Event $event)
    {
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

            // Create registration
            $registration = Registration::create([
                'event_id' => $event->id,
                'ticket_type_id' => $ticketType->id,
                'user_id' => $request->user()->id,
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
     * Display the specified registration.
     */
    public function show(Request $request, Registration $registration)
    {
        // User can only view their own registrations
        if ($registration->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 403);
        }

        return new RegistrationResource($registration->load(['event', 'ticketType']));
    }

    /**
     * Cancel a registration.
     */
    public function cancel(Request $request, Registration $registration)
    {
        // User can only cancel their own registrations
        if ($registration->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 403);
        }

        if ($registration->status === 'cancelled') {
            return response()->json([
                'message' => 'Registration is already cancelled',
            ], 422);
        }

        DB::transaction(function () use ($registration) {
            $event = $registration->event;
            $ticketType = $registration->ticketType;

            // Update status
            $registration->update(['status' => 'cancelled']);

            // Decrement counters if registration was confirmed
            if ($registration->status === 'confirmed') {
                $event->decrement('total_registered', $registration->quantity);
                $ticketType->decrement('quantity_sold', $registration->quantity);

                // TODO: Promote waitlisted registrations
            }
        });

        return response()->json([
            'message' => 'Registration cancelled successfully',
        ]);
    }
}

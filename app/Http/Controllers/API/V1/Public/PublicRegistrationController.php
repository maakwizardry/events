<?php

namespace App\Http\Controllers\API\V1\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Registration\RegisterForEventRequest;
use App\Http\Resources\RegistrationResource;
use App\Models\Event;
use App\Models\Registration;
use App\Models\TicketType;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class PublicRegistrationController extends Controller
{
    #[OA\Post(
        path: '/public/events/{event}/register',
        tags: ['Public Events'],
        summary: 'Register for public event (guest)',
        description: 'Register for a public event as a guest without authentication. Creates registration with guest information.',
        parameters: [
            new OA\Parameter(
                name: 'event',
                in: 'path',
                required: true,
                description: 'Event UUID',
                schema: new OA\Schema(type: 'string', format: 'uuid')
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['ticket_type_id', 'guest_name', 'guest_email'],
                properties: [
                    new OA\Property(property: 'ticket_type_id', type: 'integer', example: 1),
                    new OA\Property(property: 'guest_name', type: 'string', example: 'John Doe'),
                    new OA\Property(property: 'guest_email', type: 'string', format: 'email', example: 'john@example.com'),
                    new OA\Property(property: 'guest_phone', type: 'string', example: '+1234567890', nullable: true),
                    new OA\Property(property: 'quantity', type: 'integer', example: 1),
                    new OA\Property(
                        property: 'custom_fields',
                        type: 'object',
                        example: ['dietary_restrictions' => 'vegetarian', 'company' => 'Acme Corp'],
                        nullable: true
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Registration successful',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'uuid', type: 'string', example: '550e8400-e29b-41d4-a716-446655440000'),
                                new OA\Property(property: 'status', type: 'string', example: 'confirmed'),
                                new OA\Property(property: 'quantity', type: 'integer', example: 1),
                                new OA\Property(property: 'total_price', type: 'number', format: 'float', example: 50.00),
                                new OA\Property(property: 'qr_code_data', type: 'string'),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Event not found or not available for public registration'),
            new OA\Response(response: 422, description: 'Validation error or registration not open')
        ]
    )]
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

        // Check capacity before transaction
        $event->refresh();
        $ticketType->refresh();

        $status = 'confirmed';

        // Check event capacity
        if ($event->capacity && ($event->total_registered + $quantity) > $event->capacity) {
            if (!$event->enable_waitlist) {
                return response()->json([
                    'message' => 'Event is full and waitlist is not enabled',
                ], 422);
            }
            $status = 'waitlisted';
        }

        // Check ticket type capacity
        if ($ticketType->quantity && ($ticketType->quantity_sold + $quantity) > $ticketType->quantity) {
            if (!$event->enable_waitlist) {
                return response()->json([
                    'message' => 'Ticket type is sold out and waitlist is not enabled',
                ], 422);
            }
            $status = 'waitlisted';
        }

        // Use database transaction for creating registration
        $registration = DB::transaction(function () use ($request, $event, $ticketType, $quantity, $status) {
            // Refresh models again to ensure latest data
            $event->refresh();
            $ticketType->refresh();

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

    #[OA\Get(
        path: '/public/registrations/{registration}',
        tags: ['Public Events'],
        summary: 'View registration details',
        description: 'View registration details by UUID. No authentication required - useful for guests to view their registration.',
        parameters: [
            new OA\Parameter(
                name: 'registration',
                in: 'path',
                required: true,
                description: 'Registration UUID',
                schema: new OA\Schema(type: 'string', format: 'uuid')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Registration details',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'uuid', type: 'string', example: '550e8400-e29b-41d4-a716-446655440000'),
                                new OA\Property(property: 'status', type: 'string', example: 'confirmed'),
                                new OA\Property(property: 'quantity', type: 'integer', example: 1),
                                new OA\Property(property: 'total_price', type: 'number', format: 'float', example: 50.00),
                                new OA\Property(property: 'qr_code_data', type: 'string'),
                                new OA\Property(property: 'is_checked_in', type: 'boolean', example: false),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Registration not found')
        ]
    )]
    public function show(Registration $registration)
    {
        return new RegistrationResource($registration->load(['event', 'ticketType']));
    }
}

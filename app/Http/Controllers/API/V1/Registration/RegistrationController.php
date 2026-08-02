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
use OpenApi\Attributes as OA;

class RegistrationController extends Controller
{
    use AuthorizesRequests;

    #[OA\Get(
        path: '/registrations',
        tags: ['Registrations'],
        summary: 'List my registrations',
        description: 'Get all registrations for the authenticated user',
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(
                name: 'page',
                in: 'query',
                description: 'Page number for pagination',
                required: false,
                schema: new OA\Schema(type: 'integer', example: 1)
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'List of user registrations',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'uuid', type: 'string', example: '550e8400-e29b-41d4-a716-446655440000'),
                                    new OA\Property(property: 'status', type: 'string', example: 'confirmed'),
                                    new OA\Property(property: 'quantity', type: 'integer', example: 1),
                                    new OA\Property(property: 'total_price', type: 'number', format: 'float', example: 50.00),
                                    new OA\Property(property: 'is_checked_in', type: 'boolean', example: false),
                                    new OA\Property(property: 'registered_at', type: 'string', format: 'date-time'),
                                ]
                            )
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated')
        ]
    )]
    public function index(Request $request)
    {
        $registrations = $request->user()
            ->registrations()
            ->with(['event', 'ticketType'])
            ->latest()
            ->paginate(20);

        return RegistrationResource::collection($registrations);
    }

    #[OA\Post(
        path: '/events/{event}/register',
        tags: ['Registrations'],
        summary: 'Register for event (authenticated)',
        description: 'Register for an event as an authenticated user. User information is taken from the authenticated account.',
        security: [['sanctum' => []]],
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
                required: ['ticket_type_id'],
                properties: [
                    new OA\Property(property: 'ticket_type_id', type: 'integer', example: 1),
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
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 422, description: 'Validation error or registration not open')
        ]
    )]
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

    #[OA\Get(
        path: '/registrations/{registration}',
        tags: ['Registrations'],
        summary: 'Get my registration details',
        description: 'Get details of a specific registration. Users can only view their own registrations.',
        security: [['sanctum' => []]],
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
                        new OA\Property(property: 'data', type: 'object'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Unauthorized - not your registration'),
            new OA\Response(response: 404, description: 'Registration not found')
        ]
    )]
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

    #[OA\Post(
        path: '/registrations/{registration}/cancel',
        tags: ['Registrations'],
        summary: 'Cancel my registration',
        description: 'Cancel a registration. Users can only cancel their own registrations. Frees up capacity for others.',
        security: [['sanctum' => []]],
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
                description: 'Registration cancelled successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Registration cancelled successfully'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Unauthorized - not your registration'),
            new OA\Response(response: 422, description: 'Registration is already cancelled')
        ]
    )]
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

            // Store original status before updating
            $originalStatus = $registration->status;

            // Update status
            $registration->update(['status' => 'cancelled']);

            // Decrement counters if registration was confirmed
            if ($originalStatus === 'confirmed') {
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

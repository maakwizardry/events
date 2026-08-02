<?php

namespace App\Http\Controllers\API\V1\Event;

use App\Http\Controllers\Controller;
use App\Http\Requests\TicketType\StoreTicketTypeRequest;
use App\Http\Requests\TicketType\UpdateTicketTypeRequest;
use App\Http\Resources\TicketTypeResource;
use App\Models\Event;
use App\Models\TicketType;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use OpenApi\Attributes as OA;

class TicketTypeController extends Controller
{
    use AuthorizesRequests;

    #[OA\Get(
        path: '/public/events/{event}/ticket-types',
        tags: ['Ticket Types'],
        summary: 'List event ticket types',
        description: 'Get all ticket types for an event. Public endpoint for public events, requires authentication for private events.',
        parameters: [
            new OA\Parameter(
                name: 'event',
                in: 'path',
                required: true,
                description: 'Event UUID',
                schema: new OA\Schema(type: 'string', format: 'uuid')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'List of ticket types',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'id', type: 'integer', example: 1),
                                    new OA\Property(property: 'name', type: 'string', example: 'General Admission'),
                                    new OA\Property(property: 'description', type: 'string'),
                                    new OA\Property(property: 'price', type: 'number', format: 'float', example: 50.00),
                                    new OA\Property(property: 'quantity', type: 'integer', example: 100, nullable: true),
                                    new OA\Property(property: 'quantity_sold', type: 'integer', example: 45),
                                    new OA\Property(property: 'sales_start_at', type: 'string', format: 'date-time', nullable: true),
                                    new OA\Property(property: 'sales_end_at', type: 'string', format: 'date-time', nullable: true),
                                ]
                            )
                        ),
                    ]
                )
            ),
            new OA\Response(response: 403, description: 'Not authorized to view ticket types for private event'),
            new OA\Response(response: 404, description: 'Event not found')
        ]
    )]
    public function index(Event $event)
    {
        // Public endpoint - no authorization required for public events
        if ($event->visibility !== 'public' && $event->status !== 'published') {
            // For non-public events, check authorization
            $this->authorize('view', $event);
        }

        $ticketTypes = $event->ticketTypes()
            ->orderBy('order')
            ->orderBy('created_at')
            ->get();

        return TicketTypeResource::collection($ticketTypes);
    }

    #[OA\Post(
        path: '/events/{event}/ticket-types',
        tags: ['Ticket Types'],
        summary: 'Create ticket type',
        description: 'Create a new ticket type for an event. Requires event update permissions.',
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
                required: ['name', 'price'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'General Admission'),
                    new OA\Property(property: 'description', type: 'string', example: 'Standard entry ticket', nullable: true),
                    new OA\Property(property: 'price', type: 'number', format: 'float', example: 50.00),
                    new OA\Property(property: 'quantity', type: 'integer', example: 100, nullable: true),
                    new OA\Property(property: 'sales_start_at', type: 'string', format: 'date-time', nullable: true),
                    new OA\Property(property: 'sales_end_at', type: 'string', format: 'date-time', nullable: true),
                    new OA\Property(property: 'order', type: 'integer', example: 0, nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Ticket type created successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'object'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Not authorized to update this event'),
            new OA\Response(response: 422, description: 'Validation error')
        ]
    )]
    public function store(StoreTicketTypeRequest $request, Event $event)
    {
        $this->authorize('update', $event);

        $ticketType = $event->ticketTypes()->create($request->validated());

        return new TicketTypeResource($ticketType);
    }

    #[OA\Get(
        path: '/public/events/{event}/ticket-types/{ticketType}',
        tags: ['Ticket Types'],
        summary: 'Get ticket type details',
        description: 'Get detailed information about a specific ticket type. Public endpoint for public events.',
        parameters: [
            new OA\Parameter(
                name: 'event',
                in: 'path',
                required: true,
                description: 'Event UUID',
                schema: new OA\Schema(type: 'string', format: 'uuid')
            ),
            new OA\Parameter(
                name: 'ticketType',
                in: 'path',
                required: true,
                description: 'Ticket Type ID',
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Ticket type details',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'object'),
                    ]
                )
            ),
            new OA\Response(response: 403, description: 'Not authorized to view ticket type'),
            new OA\Response(response: 404, description: 'Ticket type does not belong to this event')
        ]
    )]
    public function show(Event $event, TicketType $ticketType)
    {
        // Public endpoint - no authorization required for public events
        if ($event->visibility !== 'public' && $event->status !== 'published') {
            // For non-public events, check authorization
            $this->authorize('view', $event);
        }

        // Ensure ticket type belongs to this event
        if ($ticketType->event_id !== $event->id) {
            return response()->json([
                'message' => 'Ticket type does not belong to this event',
            ], 404);
        }

        return new TicketTypeResource($ticketType);
    }

    #[OA\Put(
        path: '/events/{event}/ticket-types/{ticketType}',
        tags: ['Ticket Types'],
        summary: 'Update ticket type',
        description: 'Update ticket type details. Requires event update permissions.',
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(
                name: 'event',
                in: 'path',
                required: true,
                description: 'Event UUID',
                schema: new OA\Schema(type: 'string', format: 'uuid')
            ),
            new OA\Parameter(
                name: 'ticketType',
                in: 'path',
                required: true,
                description: 'Ticket Type ID',
                schema: new OA\Schema(type: 'integer')
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'VIP Pass'),
                    new OA\Property(property: 'description', type: 'string'),
                    new OA\Property(property: 'price', type: 'number', format: 'float', example: 100.00),
                    new OA\Property(property: 'quantity', type: 'integer', example: 50),
                    new OA\Property(property: 'sales_start_at', type: 'string', format: 'date-time'),
                    new OA\Property(property: 'sales_end_at', type: 'string', format: 'date-time'),
                    new OA\Property(property: 'order', type: 'integer'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Ticket type updated successfully'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Not authorized to update this event'),
            new OA\Response(response: 404, description: 'Ticket type does not belong to this event'),
            new OA\Response(response: 422, description: 'Validation error')
        ]
    )]
    public function update(UpdateTicketTypeRequest $request, Event $event, TicketType $ticketType)
    {
        $this->authorize('update', $event);

        // Ensure ticket type belongs to this event
        if ($ticketType->event_id !== $event->id) {
            return response()->json([
                'message' => 'Ticket type does not belong to this event',
            ], 404);
        }

        $ticketType->update($request->validated());

        return new TicketTypeResource($ticketType);
    }

    #[OA\Delete(
        path: '/events/{event}/ticket-types/{ticketType}',
        tags: ['Ticket Types'],
        summary: 'Delete ticket type',
        description: 'Delete a ticket type. Cannot delete if there are existing registrations. Requires event update permissions.',
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(
                name: 'event',
                in: 'path',
                required: true,
                description: 'Event UUID',
                schema: new OA\Schema(type: 'string', format: 'uuid')
            ),
            new OA\Parameter(
                name: 'ticketType',
                in: 'path',
                required: true,
                description: 'Ticket Type ID',
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Ticket type deleted successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Ticket type deleted successfully'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Not authorized to update this event'),
            new OA\Response(response: 404, description: 'Ticket type does not belong to this event'),
            new OA\Response(response: 422, description: 'Cannot delete ticket type with existing registrations')
        ]
    )]
    public function destroy(Event $event, TicketType $ticketType)
    {
        $this->authorize('update', $event);

        // Ensure ticket type belongs to this event
        if ($ticketType->event_id !== $event->id) {
            return response()->json([
                'message' => 'Ticket type does not belong to this event',
            ], 404);
        }

        // Check if there are any registrations for this ticket type
        if ($ticketType->quantity_sold > 0) {
            return response()->json([
                'message' => 'Cannot delete ticket type with existing registrations',
            ], 422);
        }

        $ticketType->delete();

        return response()->json([
            'message' => 'Ticket type deleted successfully',
        ]);
    }
}

<?php

namespace App\Http\Controllers\API\V1\Event;

use App\Http\Controllers\Controller;
use App\Http\Requests\Event\StoreEventRequest;
use App\Http\Requests\Event\UpdateEventRequest;
use App\Http\Resources\EventResource;
use App\Models\Event;
use App\Models\Organization;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class EventController extends Controller
{
    use AuthorizesRequests;

    #[OA\Get(
        path: '/organizations/{organization}/events',
        tags: ['Events'],
        summary: 'List organization events',
        description: 'Get all events for a specific organization. Requires user to be a member of the organization.',
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(
                name: 'organization',
                in: 'path',
                required: true,
                description: 'Organization UUID',
                schema: new OA\Schema(type: 'string', format: 'uuid')
            ),
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
                description: 'List of organization events',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'uuid', type: 'string', example: '550e8400-e29b-41d4-a716-446655440000'),
                                    new OA\Property(property: 'name', type: 'string', example: 'Tech Conference 2024'),
                                    new OA\Property(property: 'slug', type: 'string', example: 'tech-conference-2024'),
                                    new OA\Property(property: 'status', type: 'string', example: 'published'),
                                    new OA\Property(property: 'visibility', type: 'string', example: 'public'),
                                    new OA\Property(property: 'starts_at', type: 'string', format: 'date-time'),
                                    new OA\Property(property: 'registrations_count', type: 'integer', example: 45),
                                ]
                            )
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Not a member of this organization'),
            new OA\Response(response: 404, description: 'Organization not found')
        ]
    )]
    public function index(Request $request, Organization $organization)
    {
        $this->authorize('view', $organization);

        $events = $organization->events()
            ->with(['ticketTypes'])
            ->withCount(['registrations'])
            ->latest('starts_at')
            ->paginate(20);

        return EventResource::collection($events);
    }

    #[OA\Post(
        path: '/events',
        tags: ['Events'],
        summary: 'Create a new event',
        description: 'Create a new event for an organization. Requires admin or owner role in the organization.',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['organization_id', 'name', 'visibility', 'location_type', 'starts_at', 'ends_at'],
                properties: [
                    new OA\Property(property: 'organization_id', type: 'integer', example: 1),
                    new OA\Property(property: 'name', type: 'string', example: 'Tech Conference 2024'),
                    new OA\Property(property: 'slug', type: 'string', example: 'tech-conference-2024', nullable: true),
                    new OA\Property(property: 'description', type: 'string', example: 'Join us for an amazing tech conference', nullable: true),
                    new OA\Property(property: 'cover_image_url', type: 'string', format: 'url', nullable: true),
                    new OA\Property(property: 'visibility', type: 'string', enum: ['public', 'private'], example: 'public'),
                    new OA\Property(property: 'location_type', type: 'string', enum: ['physical', 'online', 'hybrid'], example: 'physical'),
                    new OA\Property(property: 'location_address', type: 'string', example: '123 Main St', nullable: true),
                    new OA\Property(property: 'location_city', type: 'string', example: 'San Francisco', nullable: true),
                    new OA\Property(property: 'location_state', type: 'string', example: 'CA', nullable: true),
                    new OA\Property(property: 'location_country', type: 'string', example: 'United States', nullable: true),
                    new OA\Property(property: 'location_zip', type: 'string', example: '94105', nullable: true),
                    new OA\Property(property: 'location_latitude', type: 'number', format: 'float', example: 37.7749, nullable: true),
                    new OA\Property(property: 'location_longitude', type: 'number', format: 'float', example: -122.4194, nullable: true),
                    new OA\Property(property: 'online_meeting_url', type: 'string', format: 'url', example: 'https://zoom.us/j/123456789', nullable: true),
                    new OA\Property(property: 'starts_at', type: 'string', format: 'date-time', example: '2024-12-01T10:00:00Z'),
                    new OA\Property(property: 'ends_at', type: 'string', format: 'date-time', example: '2024-12-01T18:00:00Z'),
                    new OA\Property(property: 'timezone', type: 'string', example: 'America/Los_Angeles', nullable: true),
                    new OA\Property(property: 'capacity', type: 'integer', example: 100, nullable: true),
                    new OA\Property(property: 'enable_waitlist', type: 'boolean', example: true, nullable: true),
                    new OA\Property(property: 'auto_approve_registrations', type: 'boolean', example: true, nullable: true),
                    new OA\Property(property: 'require_approval', type: 'boolean', example: false, nullable: true),
                    new OA\Property(property: 'registration_opens_at', type: 'string', format: 'date-time', nullable: true),
                    new OA\Property(property: 'registration_closes_at', type: 'string', format: 'date-time', nullable: true),
                    new OA\Property(property: 'category', type: 'string', enum: ['conference', 'workshop', 'meetup', 'webinar', 'networking'], nullable: true),
                    new OA\Property(property: 'tags', type: 'array', items: new OA\Items(type: 'string'), example: ['tech', 'networking'], nullable: true),
                    new OA\Property(property: 'status', type: 'string', enum: ['draft', 'published', 'cancelled', 'completed'], example: 'draft', nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Event created successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'object'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Not authorized to create events for this organization'),
            new OA\Response(response: 422, description: 'Validation error')
        ]
    )]
    public function store(StoreEventRequest $request)
    {
        $organization = Organization::findOrFail($request->organization_id);

        $this->authorize('create', [Event::class, $organization]);

        $event = Event::create($request->validated());

        return new EventResource($event->load(['ticketTypes'])->loadCount(['registrations']));
    }

    #[OA\Get(
        path: '/events/{event}',
        tags: ['Events'],
        summary: 'Get event details',
        description: 'Get detailed information about a specific event. Requires authorization to view the event.',
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
        responses: [
            new OA\Response(response: 200, description: 'Event details'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Not authorized to view this event'),
            new OA\Response(response: 404, description: 'Event not found')
        ]
    )]
    public function show(Event $event)
    {
        $this->authorize('view', $event);

        return new EventResource($event->load(['organization', 'ticketTypes'])->loadCount(['registrations']));
    }

    #[OA\Put(
        path: '/events/{event}',
        tags: ['Events'],
        summary: 'Update event',
        description: 'Update event details. Requires admin or owner role in the organization.',
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
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Tech Conference 2024'),
                    new OA\Property(property: 'slug', type: 'string', example: 'tech-conference-2024'),
                    new OA\Property(property: 'description', type: 'string'),
                    new OA\Property(property: 'visibility', type: 'string', enum: ['public', 'private']),
                    new OA\Property(property: 'location_type', type: 'string', enum: ['physical', 'online', 'hybrid']),
                    new OA\Property(property: 'starts_at', type: 'string', format: 'date-time'),
                    new OA\Property(property: 'ends_at', type: 'string', format: 'date-time'),
                    new OA\Property(property: 'capacity', type: 'integer'),
                    new OA\Property(property: 'status', type: 'string', enum: ['draft', 'published', 'cancelled', 'completed']),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Event updated successfully'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Not authorized to update this event'),
            new OA\Response(response: 422, description: 'Validation error')
        ]
    )]
    public function update(UpdateEventRequest $request, Event $event)
    {
        $this->authorize('update', $event);

        $event->update($request->validated());

        return new EventResource($event->load(['organization', 'ticketTypes'])->loadCount(['registrations']));
    }

    #[OA\Delete(
        path: '/events/{event}',
        tags: ['Events'],
        summary: 'Delete event',
        description: 'Delete an event. Requires admin or owner role in the organization.',
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
        responses: [
            new OA\Response(
                response: 200,
                description: 'Event deleted successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Event deleted successfully'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Not authorized to delete this event')
        ]
    )]
    public function destroy(Event $event)
    {
        $this->authorize('delete', $event);

        $event->delete();

        return response()->json([
            'message' => 'Event deleted successfully',
        ]);
    }

    #[OA\Get(
        path: '/events/{event}/availability',
        tags: ['Events'],
        summary: 'Get event availability (authenticated)',
        description: 'Get event capacity and availability information. Requires authorization to view the event.',
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
        responses: [
            new OA\Response(
                response: 200,
                description: 'Event availability details',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'capacity', type: 'integer', example: 100),
                        new OA\Property(property: 'total_registered', type: 'integer', example: 45),
                        new OA\Property(property: 'available_spots', type: 'integer', example: 55),
                        new OA\Property(property: 'is_full', type: 'boolean', example: false),
                        new OA\Property(property: 'enable_waitlist', type: 'boolean', example: true),
                        new OA\Property(property: 'is_registration_open', type: 'boolean', example: true),
                        new OA\Property(property: 'registration_opens_at', type: 'string', format: 'date-time', nullable: true),
                        new OA\Property(property: 'registration_closes_at', type: 'string', format: 'date-time', nullable: true),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Not authorized to view this event'),
            new OA\Response(response: 404, description: 'Event not found')
        ]
    )]
    public function availability(Event $event)
    {
        $this->authorize('view', $event);

        return response()->json([
            'capacity' => $event->capacity,
            'total_registered' => $event->total_registered,
            'available_spots' => $event->availableSpots(),
            'is_full' => $event->isFull(),
            'enable_waitlist' => $event->enable_waitlist,
            'is_registration_open' => $event->isRegistrationOpen(),
            'registration_opens_at' => $event->registration_opens_at?->toIso8601String(),
            'registration_closes_at' => $event->registration_closes_at?->toIso8601String(),
        ]);
    }
}

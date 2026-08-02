<?php

namespace App\Http\Controllers\API\V1\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\EventResource;
use App\Models\Event;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class PublicEventController extends Controller
{
    #[OA\Get(
        path: '/public/events',
        tags: ['Public Events'],
        summary: 'List public events',
        description: 'Get all public published events with filtering and search capabilities. No authentication required.',
        parameters: [
            new OA\Parameter(
                name: 'upcoming',
                in: 'query',
                description: 'Filter by upcoming events only (default: true)',
                required: false,
                schema: new OA\Schema(type: 'boolean', example: true)
            ),
            new OA\Parameter(
                name: 'start_date',
                in: 'query',
                description: 'Filter events starting from this date',
                required: false,
                schema: new OA\Schema(type: 'string', format: 'date', example: '2024-01-01')
            ),
            new OA\Parameter(
                name: 'end_date',
                in: 'query',
                description: 'Filter events ending before this date',
                required: false,
                schema: new OA\Schema(type: 'string', format: 'date', example: '2024-12-31')
            ),
            new OA\Parameter(
                name: 'category',
                in: 'query',
                description: 'Filter by event category',
                required: false,
                schema: new OA\Schema(type: 'string', example: 'conference')
            ),
            new OA\Parameter(
                name: 'city',
                in: 'query',
                description: 'Filter by city name',
                required: false,
                schema: new OA\Schema(type: 'string', example: 'San Francisco')
            ),
            new OA\Parameter(
                name: 'country',
                in: 'query',
                description: 'Filter by country name',
                required: false,
                schema: new OA\Schema(type: 'string', example: 'United States')
            ),
            new OA\Parameter(
                name: 'latitude',
                in: 'query',
                description: 'Latitude for proximity search (requires longitude)',
                required: false,
                schema: new OA\Schema(type: 'number', format: 'float', example: 37.7749)
            ),
            new OA\Parameter(
                name: 'longitude',
                in: 'query',
                description: 'Longitude for proximity search (requires latitude)',
                required: false,
                schema: new OA\Schema(type: 'number', format: 'float', example: -122.4194)
            ),
            new OA\Parameter(
                name: 'radius',
                in: 'query',
                description: 'Search radius in kilometers (default: 50)',
                required: false,
                schema: new OA\Schema(type: 'integer', example: 50)
            ),
            new OA\Parameter(
                name: 'location_type',
                in: 'query',
                description: 'Filter by location type',
                required: false,
                schema: new OA\Schema(type: 'string', enum: ['in_person', 'online', 'hybrid'], example: 'in_person')
            ),
            new OA\Parameter(
                name: 'search',
                in: 'query',
                description: 'Search in event name or description',
                required: false,
                schema: new OA\Schema(type: 'string', example: 'tech')
            ),
            new OA\Parameter(
                name: 'tags',
                in: 'query',
                description: 'Filter by tags (can be array or single value)',
                required: false,
                schema: new OA\Schema(type: 'string', example: 'networking')
            ),
            new OA\Parameter(
                name: 'sort_by',
                in: 'query',
                description: 'Sort field',
                required: false,
                schema: new OA\Schema(type: 'string', enum: ['starts_at', 'created_at', 'name'], example: 'starts_at')
            ),
            new OA\Parameter(
                name: 'sort_order',
                in: 'query',
                description: 'Sort order',
                required: false,
                schema: new OA\Schema(type: 'string', enum: ['asc', 'desc'], example: 'asc')
            ),
            new OA\Parameter(
                name: 'per_page',
                in: 'query',
                description: 'Items per page (default: 20)',
                required: false,
                schema: new OA\Schema(type: 'integer', example: 20)
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
                description: 'List of public events',
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
                                    new OA\Property(property: 'description', type: 'string'),
                                    new OA\Property(property: 'starts_at', type: 'string', format: 'date-time'),
                                    new OA\Property(property: 'ends_at', type: 'string', format: 'date-time'),
                                    new OA\Property(property: 'location_type', type: 'string', example: 'in_person'),
                                    new OA\Property(property: 'capacity', type: 'integer', example: 100),
                                    new OA\Property(property: 'total_registered', type: 'integer', example: 45),
                                ]
                            )
                        ),
                    ]
                )
            )
        ]
    )]
    public function index(Request $request)
    {
        $query = Event::query()
            ->public()
            ->with(['organization', 'ticketTypes'])
            ->withCount(['registrations']);

        // Filter by upcoming events only
        if ($request->boolean('upcoming', true)) {
            $query->upcoming();
        }

        // Filter by date range
        if ($request->has('start_date')) {
            $query->where('starts_at', '>=', $request->start_date);
        }

        if ($request->has('end_date')) {
            $query->where('starts_at', '<=', $request->end_date);
        }

        // Filter by category
        if ($request->has('category')) {
            $query->byCategory($request->category);
        }

        // Filter by location
        if ($request->has('city')) {
            $query->where('location_city', 'like', '%' . $request->city . '%');
        }

        if ($request->has('country')) {
            $query->where('location_country', 'like', '%' . $request->country . '%');
        }

        // Filter by proximity (requires latitude, longitude, and radius)
        if ($request->has(['latitude', 'longitude'])) {
            $radius = $request->get('radius', 50); // Default 50km
            $query->nearby(
                $request->latitude,
                $request->longitude,
                $radius
            );
        }

        // Filter by location type
        if ($request->has('location_type')) {
            $query->where('location_type', $request->location_type);
        }

        // Search by name or description
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                  ->orWhere('description', 'like', '%' . $search . '%');
            });
        }

        // Filter by tags
        if ($request->has('tags')) {
            $tags = is_array($request->tags) ? $request->tags : [$request->tags];
            $query->where(function ($q) use ($tags) {
                foreach ($tags as $tag) {
                    $q->orWhereJsonContains('tags', $tag);
                }
            });
        }

        // Sort options
        $sortBy = $request->get('sort_by', 'starts_at');
        $sortOrder = $request->get('sort_order', 'asc');

        if (in_array($sortBy, ['starts_at', 'created_at', 'name'])) {
            $query->orderBy($sortBy, $sortOrder);
        }

        $events = $query->paginate($request->get('per_page', 20));

        return EventResource::collection($events);
    }

    #[OA\Get(
        path: '/public/events/{event}',
        tags: ['Public Events'],
        summary: 'Get public event details',
        description: 'Get detailed information about a specific public event. No authentication required.',
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
                description: 'Event details',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'uuid', type: 'string', example: '550e8400-e29b-41d4-a716-446655440000'),
                                new OA\Property(property: 'name', type: 'string', example: 'Tech Conference 2024'),
                                new OA\Property(property: 'slug', type: 'string', example: 'tech-conference-2024'),
                                new OA\Property(property: 'description', type: 'string'),
                                new OA\Property(property: 'starts_at', type: 'string', format: 'date-time'),
                                new OA\Property(property: 'ends_at', type: 'string', format: 'date-time'),
                                new OA\Property(property: 'location_type', type: 'string', example: 'in_person'),
                                new OA\Property(property: 'capacity', type: 'integer', example: 100),
                                new OA\Property(property: 'total_registered', type: 'integer', example: 45),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Event not found or not available')
        ]
    )]
    public function show(Event $event)
    {
        // Only show public events
        if ($event->visibility !== 'public' || $event->status !== 'published') {
            return response()->json([
                'message' => 'Event not found or not available',
            ], 404);
        }

        return new EventResource($event->load(['organization', 'ticketTypes'])->loadCount(['registrations']));
    }

    #[OA\Get(
        path: '/public/events/{event}/availability',
        tags: ['Public Events'],
        summary: 'Get event availability',
        description: 'Get event capacity and ticket availability information. No authentication required.',
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
                        new OA\Property(
                            property: 'ticket_types',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'id', type: 'integer', example: 1),
                                    new OA\Property(property: 'name', type: 'string', example: 'General Admission'),
                                    new OA\Property(property: 'price', type: 'number', format: 'float', example: 50.00),
                                    new OA\Property(property: 'available_quantity', type: 'integer', example: 30),
                                    new OA\Property(property: 'is_sold_out', type: 'boolean', example: false),
                                    new OA\Property(property: 'is_on_sale', type: 'boolean', example: true),
                                ]
                            )
                        ),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Event not found or not available')
        ]
    )]
    public function availability(Event $event)
    {
        // Only show for public events
        if ($event->visibility !== 'public' || $event->status !== 'published') {
            return response()->json([
                'message' => 'Event not found or not available',
            ], 404);
        }

        // Eager load ticket types to prevent N+1 queries
        $event->load('ticketTypes');

        return response()->json([
            'capacity' => $event->capacity,
            'total_registered' => $event->total_registered,
            'available_spots' => $event->availableSpots(),
            'is_full' => $event->isFull(),
            'enable_waitlist' => $event->enable_waitlist,
            'is_registration_open' => $event->isRegistrationOpen(),
            'registration_opens_at' => $event->registration_opens_at?->toIso8601String(),
            'registration_closes_at' => $event->registration_closes_at?->toIso8601String(),
            'ticket_types' => $event->ticketTypes->map(function ($ticketType) {
                return [
                    'id' => $ticketType->id,
                    'name' => $ticketType->name,
                    'price' => $ticketType->price,
                    'available_quantity' => $ticketType->availableQuantity(),
                    'is_sold_out' => $ticketType->isSoldOut(),
                    'is_on_sale' => $ticketType->isOnSale(),
                ];
            }),
        ]);
    }
}

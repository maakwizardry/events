<?php

namespace App\Http\Controllers\API\V1\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\EventResource;
use App\Models\Event;
use Illuminate\Http\Request;

class PublicEventController extends Controller
{
    /**
     * Display a listing of public events with filtering.
     */
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

    /**
     * Display the specified public event.
     */
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

    /**
     * Get event availability (public endpoint).
     */
    public function availability(Event $event)
    {
        // Only show for public events
        if ($event->visibility !== 'public' || $event->status !== 'published') {
            return response()->json([
                'message' => 'Event not found or not available',
            ], 404);
        }

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

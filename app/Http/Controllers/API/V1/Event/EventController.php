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

class EventController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display a listing of organization's events.
     */
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

    /**
     * Store a newly created event.
     */
    public function store(StoreEventRequest $request)
    {
        $organization = Organization::findOrFail($request->organization_id);

        $this->authorize('create', [Event::class, $organization]);

        $event = Event::create($request->validated());

        return new EventResource($event->load(['ticketTypes'])->loadCount(['registrations']));
    }

    /**
     * Display the specified event.
     */
    public function show(Event $event)
    {
        $this->authorize('view', $event);

        return new EventResource($event->load(['organization', 'ticketTypes'])->loadCount(['registrations']));
    }

    /**
     * Update the specified event.
     */
    public function update(UpdateEventRequest $request, Event $event)
    {
        $this->authorize('update', $event);

        $event->update($request->validated());

        return new EventResource($event->load(['organization', 'ticketTypes'])->loadCount(['registrations']));
    }

    /**
     * Remove the specified event.
     */
    public function destroy(Event $event)
    {
        $this->authorize('delete', $event);

        $event->delete();

        return response()->json([
            'message' => 'Event deleted successfully',
        ]);
    }

    /**
     * Get event availability details.
     */
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

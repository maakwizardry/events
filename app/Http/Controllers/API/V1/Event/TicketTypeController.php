<?php

namespace App\Http\Controllers\API\V1\Event;

use App\Http\Controllers\Controller;
use App\Http\Requests\TicketType\StoreTicketTypeRequest;
use App\Http\Requests\TicketType\UpdateTicketTypeRequest;
use App\Http\Resources\TicketTypeResource;
use App\Models\Event;
use App\Models\TicketType;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class TicketTypeController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display a listing of ticket types for an event.
     */
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

    /**
     * Store a newly created ticket type.
     */
    public function store(StoreTicketTypeRequest $request, Event $event)
    {
        $this->authorize('update', $event);

        $ticketType = $event->ticketTypes()->create($request->validated());

        return new TicketTypeResource($ticketType);
    }

    /**
     * Display the specified ticket type.
     */
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

    /**
     * Update the specified ticket type.
     */
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

    /**
     * Remove the specified ticket type.
     */
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

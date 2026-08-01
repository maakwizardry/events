<?php

namespace App\Http\Controllers\API\V1\Event;

use App\Http\Controllers\Controller;
use App\Http\Resources\RegistrationResource;
use App\Models\Event;
use App\Models\Registration;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CheckInController extends Controller
{
    use AuthorizesRequests;

    /**
     * Check in an attendee by QR code.
     */
    public function checkIn(Request $request, Event $event)
    {
        $this->authorize('checkIn', $event);

        $request->validate([
            'qr_code_data' => 'required|string',
            'location' => 'nullable|string|max:255',
        ]);

        // Find registration by QR code
        $registration = Registration::where('qr_code_data', $request->qr_code_data)->first();

        if (!$registration) {
            return response()->json([
                'message' => 'Invalid QR code',
            ], 404);
        }

        // Verify registration belongs to this event
        if ($registration->event_id !== $event->id) {
            return response()->json([
                'message' => 'QR code is not valid for this event',
            ], 422);
        }

        // Check if already checked in
        if ($registration->is_checked_in) {
            return response()->json([
                'message' => 'Attendee has already been checked in',
                'checked_in_at' => $registration->checked_in_at?->toIso8601String(),
                'registration' => new RegistrationResource($registration),
            ], 422);
        }

        // Check if registration is confirmed
        if ($registration->status !== 'confirmed') {
            return response()->json([
                'message' => 'Registration status is: ' . $registration->status . '. Only confirmed registrations can be checked in.',
            ], 422);
        }

        // Perform check-in
        $registration->update([
            'is_checked_in' => true,
            'checked_in_at' => now(),
            'checked_in_by' => $request->user()->id,
            'check_in_location' => $request->location,
        ]);

        return response()->json([
            'message' => 'Check-in successful',
            'registration' => new RegistrationResource($registration->fresh()),
        ]);
    }

    /**
     * Get check-in statistics for an event.
     */
    public function statistics(Request $request, Event $event)
    {
        $this->authorize('checkIn', $event);

        $stats = DB::table('registrations')
            ->where('event_id', $event->id)
            ->select([
                DB::raw('COUNT(*) as total_registrations'),
                DB::raw('SUM(CASE WHEN is_checked_in = 1 THEN 1 ELSE 0 END) as checked_in_count'),
                DB::raw('SUM(CASE WHEN status = "confirmed" AND is_checked_in = 0 THEN 1 ELSE 0 END) as pending_check_in'),
                DB::raw('SUM(CASE WHEN status = "waitlisted" THEN 1 ELSE 0 END) as waitlisted'),
                DB::raw('SUM(CASE WHEN status = "cancelled" THEN 1 ELSE 0 END) as cancelled'),
            ])
            ->first();

        // Get check-ins by hour (last 24 hours)
        $checkInsByHour = DB::table('registrations')
            ->where('event_id', $event->id)
            ->where('is_checked_in', true)
            ->where('checked_in_at', '>=', now()->subDay())
            ->select([
                DB::raw('DATE_FORMAT(checked_in_at, "%Y-%m-%d %H:00:00") as hour'),
                DB::raw('COUNT(*) as count'),
            ])
            ->groupBy('hour')
            ->orderBy('hour')
            ->get();

        // Get ticket type breakdown
        $ticketTypeBreakdown = DB::table('registrations')
            ->join('ticket_types', 'registrations.ticket_type_id', '=', 'ticket_types.id')
            ->where('registrations.event_id', $event->id)
            ->select([
                'ticket_types.name',
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN registrations.is_checked_in = 1 THEN 1 ELSE 0 END) as checked_in'),
            ])
            ->groupBy('ticket_types.id', 'ticket_types.name')
            ->get();

        return response()->json([
            'event' => [
                'uuid' => $event->uuid,
                'name' => $event->name,
                'starts_at' => $event->starts_at?->toIso8601String(),
            ],
            'summary' => [
                'total_registrations' => $stats->total_registrations,
                'checked_in' => $stats->checked_in_count,
                'pending_check_in' => $stats->pending_check_in,
                'waitlisted' => $stats->waitlisted,
                'cancelled' => $stats->cancelled,
                'check_in_rate' => $stats->total_registrations > 0
                    ? round(($stats->checked_in_count / $stats->total_registrations) * 100, 2)
                    : 0,
            ],
            'check_ins_by_hour' => $checkInsByHour,
            'ticket_type_breakdown' => $ticketTypeBreakdown,
        ]);
    }

    /**
     * List all registrations for an event (for organizers).
     */
    public function registrations(Request $request, Event $event)
    {
        $this->authorize('manageRegistrations', $event);

        $query = $event->registrations()
            ->with(['ticketType']);

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by check-in status
        if ($request->has('checked_in')) {
            $query->where('is_checked_in', $request->boolean('checked_in'));
        }

        // Search by name or email
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('guest_name', 'like', '%' . $search . '%')
                  ->orWhere('guest_email', 'like', '%' . $search . '%')
                  ->orWhereHas('user', function ($userQuery) use ($search) {
                      $userQuery->where('name', 'like', '%' . $search . '%')
                               ->orWhere('email', 'like', '%' . $search . '%');
                  });
            });
        }

        $registrations = $query->latest('created_at')->paginate(50);

        return RegistrationResource::collection($registrations);
    }
}

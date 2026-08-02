<?php

namespace App\Http\Controllers\API\V1\Event;

use App\Http\Controllers\Controller;
use App\Http\Resources\RegistrationResource;
use App\Models\Event;
use App\Models\Registration;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class CheckInController extends Controller
{
    use AuthorizesRequests;

    #[OA\Post(
        path: '/events/{event}/check-in',
        tags: ['Check-In'],
        summary: 'Check in attendee',
        description: 'Check in an attendee using their QR code. Requires check-in permissions for the event.',
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
                required: ['qr_code_data'],
                properties: [
                    new OA\Property(property: 'qr_code_data', type: 'string', example: 'QR_CODE_STRING'),
                    new OA\Property(property: 'location', type: 'string', example: 'Main Entrance', nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Check-in successful',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Check-in successful'),
                        new OA\Property(property: 'registration', type: 'object'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Not authorized to check in attendees'),
            new OA\Response(response: 404, description: 'Invalid QR code'),
            new OA\Response(response: 422, description: 'Already checked in or invalid registration status')
        ]
    )]
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

        // Eager load relationships for the response
        $registration->load(['event', 'ticketType']);

        return response()->json([
            'message' => 'Check-in successful',
            'registration' => new RegistrationResource($registration),
        ]);
    }

    #[OA\Get(
        path: '/events/{event}/check-in/stats',
        tags: ['Check-In'],
        summary: 'Get check-in statistics',
        description: 'Get comprehensive check-in statistics and analytics for an event. Requires check-in permissions.',
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
                description: 'Check-in statistics',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'event',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'uuid', type: 'string'),
                                new OA\Property(property: 'name', type: 'string'),
                                new OA\Property(property: 'starts_at', type: 'string', format: 'date-time'),
                            ]
                        ),
                        new OA\Property(
                            property: 'summary',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'total_registrations', type: 'integer', example: 100),
                                new OA\Property(property: 'checked_in', type: 'integer', example: 75),
                                new OA\Property(property: 'pending_check_in', type: 'integer', example: 25),
                                new OA\Property(property: 'waitlisted', type: 'integer', example: 10),
                                new OA\Property(property: 'cancelled', type: 'integer', example: 5),
                                new OA\Property(property: 'check_in_rate', type: 'number', format: 'float', example: 75.00),
                            ]
                        ),
                        new OA\Property(property: 'check_ins_by_hour', type: 'array', items: new OA\Items(type: 'object')),
                        new OA\Property(property: 'ticket_type_breakdown', type: 'array', items: new OA\Items(type: 'object')),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Not authorized to view check-in statistics'),
            new OA\Response(response: 404, description: 'Event not found')
        ]
    )]
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

    #[OA\Get(
        path: '/events/{event}/registrations',
        tags: ['Check-In'],
        summary: 'List event registrations',
        description: 'Get all registrations for an event with filtering and search. Requires registration management permissions.',
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
                name: 'status',
                in: 'query',
                description: 'Filter by registration status',
                required: false,
                schema: new OA\Schema(type: 'string', enum: ['confirmed', 'waitlisted', 'cancelled'], example: 'confirmed')
            ),
            new OA\Parameter(
                name: 'checked_in',
                in: 'query',
                description: 'Filter by check-in status',
                required: false,
                schema: new OA\Schema(type: 'boolean', example: true)
            ),
            new OA\Parameter(
                name: 'search',
                in: 'query',
                description: 'Search by attendee name or email',
                required: false,
                schema: new OA\Schema(type: 'string', example: 'john')
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
                description: 'List of registrations',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(type: 'object')),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Not authorized to manage registrations'),
            new OA\Response(response: 404, description: 'Event not found')
        ]
    )]
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

    #[OA\Get(
        path: '/events/{event}/registrations/export',
        tags: ['Check-In'],
        summary: 'Export registrations to CSV',
        description: 'Export event registrations as a CSV file with filtering options. Requires registration management permissions.',
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
                name: 'status',
                in: 'query',
                description: 'Filter by registration status',
                required: false,
                schema: new OA\Schema(type: 'string', enum: ['confirmed', 'waitlisted', 'cancelled'])
            ),
            new OA\Parameter(
                name: 'checked_in',
                in: 'query',
                description: 'Filter by check-in status',
                required: false,
                schema: new OA\Schema(type: 'boolean')
            ),
            new OA\Parameter(
                name: 'search',
                in: 'query',
                description: 'Search by attendee name or email',
                required: false,
                schema: new OA\Schema(type: 'string')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'CSV file download',
                content: new OA\MediaType(
                    mediaType: 'text/csv',
                    schema: new OA\Schema(type: 'string', format: 'binary')
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Not authorized to export registrations'),
            new OA\Response(response: 404, description: 'Event not found')
        ]
    )]
    public function exportCsv(Request $request, Event $event)
    {
        $this->authorize('manageRegistrations', $event);

        $query = $event->registrations()
            ->with(['ticketType', 'user']);

        // Apply same filters as registrations() method
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('checked_in')) {
            $query->where('is_checked_in', $request->boolean('checked_in'));
        }

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

        $registrations = $query->latest('created_at')->get();

        // Generate CSV
        $filename = 'registrations-' . $event->slug . '-' . now()->format('Y-m-d-His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $callback = function () use ($registrations) {
            $file = fopen('php://output', 'w');

            // CSV headers
            fputcsv($file, [
                'Registration ID',
                'Attendee Name',
                'Email',
                'Phone',
                'Ticket Type',
                'Quantity',
                'Total Price',
                'Status',
                'Checked In',
                'Check-In Time',
                'Check-In Location',
                'Registered At',
                'Custom Fields',
            ]);

            // CSV rows
            foreach ($registrations as $registration) {
                fputcsv($file, [
                    $registration->uuid,
                    $registration->attendeeName,
                    $registration->attendeeEmail,
                    $registration->guest_phone ?? '',
                    $registration->ticketType->name,
                    $registration->quantity,
                    $registration->total_price,
                    $registration->status,
                    $registration->is_checked_in ? 'Yes' : 'No',
                    $registration->checked_in_at?->toDateTimeString() ?? '',
                    $registration->check_in_location ?? '',
                    $registration->registered_at?->toDateTimeString() ?? '',
                    $registration->custom_fields ? json_encode($registration->custom_fields) : '',
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}

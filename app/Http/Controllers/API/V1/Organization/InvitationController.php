<?php

namespace App\Http\Controllers\API\V1\Organization;

use App\Http\Controllers\Controller;
use App\Models\OrganizationInvitation;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class InvitationController extends Controller
{
    #[OA\Post(
        path: '/invitations/{token}/accept',
        tags: ['Invitations'],
        summary: 'Accept an organization invitation',
        description: 'Authenticated user accepts an invitation to join an organization. User email must match the invited email.',
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(
                name: 'token',
                in: 'path',
                required: true,
                description: 'Invitation token from email',
                schema: new OA\Schema(type: 'string', example: 'abc123xyz789...')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Invitation accepted successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Invitation accepted successfully'),
                        new OA\Property(
                            property: 'organization',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'uuid', type: 'string', example: '550e8400-e29b-41d4-a716-446655440000'),
                                new OA\Property(property: 'name', type: 'string', example: 'Tech Community'),
                                new OA\Property(property: 'role', type: 'string', example: 'admin'),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Not authenticated'),
            new OA\Response(response: 403, description: 'Email mismatch - wrong user'),
            new OA\Response(response: 404, description: 'Invalid invitation token'),
            new OA\Response(response: 422, description: 'Invitation expired, already accepted, or user already a member')
        ]
    )]
    public function accept(Request $request, string $token)
    {
        $invitation = OrganizationInvitation::where('token', $token)->first();

        if (!$invitation) {
            return response()->json([
                'message' => 'Invalid invitation token',
            ], 404);
        }

        if ($invitation->status === 'accepted') {
            return response()->json([
                'message' => 'This invitation has already been accepted',
            ], 422);
        }

        if ($invitation->isExpired()) {
            $invitation->markAsExpired();
            return response()->json([
                'message' => 'This invitation has expired',
            ], 422);
        }

        // User must be authenticated
        if (!$request->user()) {
            return response()->json([
                'message' => 'You must be logged in to accept this invitation',
                'invitation' => [
                    'organization' => $invitation->organization->name,
                    'role' => $invitation->role,
                    'email' => $invitation->email,
                ],
            ], 401);
        }

        // Check if user's email matches invitation email
        if ($request->user()->email !== $invitation->email) {
            return response()->json([
                'message' => 'This invitation was sent to ' . $invitation->email . '. Please log in with that email address.',
            ], 403);
        }

        // Check if user is already a member
        if ($invitation->organization->hasMember($request->user())) {
            $invitation->markAsExpired();
            return response()->json([
                'message' => 'You are already a member of this organization',
            ], 422);
        }

        // Accept the invitation
        $invitation->accept($request->user());

        return response()->json([
            'message' => 'Invitation accepted successfully',
            'organization' => [
                'uuid' => $invitation->organization->uuid,
                'name' => $invitation->organization->name,
                'role' => $invitation->role,
            ],
        ], 200);
    }

    #[OA\Get(
        path: '/invitations/{token}',
        tags: ['Invitations'],
        summary: 'Preview invitation details',
        description: 'Get details of an invitation before accepting (no authentication required)',
        parameters: [
            new OA\Parameter(
                name: 'token',
                in: 'path',
                required: true,
                description: 'Invitation token',
                schema: new OA\Schema(type: 'string')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Invitation details',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'invitation',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'organization_name', type: 'string', example: 'Tech Community'),
                                new OA\Property(property: 'role', type: 'string', example: 'admin'),
                                new OA\Property(property: 'invited_by', type: 'string', example: 'Admin User'),
                                new OA\Property(property: 'email', type: 'string', example: 'newuser@example.com'),
                                new OA\Property(property: 'expires_at', type: 'string', format: 'date-time'),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 404, description: 'Invalid invitation token'),
            new OA\Response(response: 422, description: 'Invitation expired or already accepted')
        ]
    )]
    public function show(string $token)
    {
        $invitation = OrganizationInvitation::where('token', $token)
            ->with(['organization', 'inviter'])
            ->first();

        if (!$invitation) {
            return response()->json([
                'message' => 'Invalid invitation token',
            ], 404);
        }

        if ($invitation->status === 'accepted') {
            return response()->json([
                'message' => 'This invitation has already been accepted',
            ], 422);
        }

        if ($invitation->isExpired()) {
            return response()->json([
                'message' => 'This invitation has expired',
            ], 422);
        }

        return response()->json([
            'invitation' => [
                'organization_name' => $invitation->organization->name,
                'role' => $invitation->role,
                'invited_by' => $invitation->inviter->name,
                'email' => $invitation->email,
                'expires_at' => $invitation->expires_at->toIso8601String(),
            ],
        ]);
    }

    #[OA\Get(
        path: '/invitations/my',
        tags: ['Invitations'],
        summary: 'List my pending invitations',
        description: 'Get all pending invitations for the authenticated user',
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'List of pending invitations',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'invitations',
                            type: 'array',
                            items: new OA\Items(
                                type: 'object',
                                properties: [
                                    new OA\Property(property: 'token', type: 'string', example: 'abc123xyz789...'),
                                    new OA\Property(property: 'organization_name', type: 'string', example: 'Tech Community'),
                                    new OA\Property(property: 'organization_uuid', type: 'string', example: '550e8400-e29b-41d4-a716-446655440000'),
                                    new OA\Property(property: 'role', type: 'string', example: 'admin'),
                                    new OA\Property(property: 'invited_by', type: 'string', example: 'Admin User'),
                                    new OA\Property(property: 'expires_at', type: 'string', format: 'date-time'),
                                ]
                            )
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Not authenticated')
        ]
    )]
    public function myInvitations(Request $request)
    {
        $invitations = OrganizationInvitation::where('email', $request->user()->email)
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->with(['organization', 'inviter'])
            ->latest()
            ->get();

        return response()->json([
            'invitations' => $invitations->map(function ($invitation) {
                return [
                    'token' => $invitation->token,
                    'organization_name' => $invitation->organization->name,
                    'organization_uuid' => $invitation->organization->uuid,
                    'role' => $invitation->role,
                    'invited_by' => $invitation->inviter->name,
                    'expires_at' => $invitation->expires_at->toIso8601String(),
                ];
            }),
        ]);
    }
}

<?php

namespace App\Http\Controllers\API\V1\Organization;

use App\Http\Controllers\Controller;
use App\Models\OrganizationInvitation;
use Illuminate\Http\Request;

class InvitationController extends Controller
{
    /**
     * Accept an organization invitation.
     */
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

    /**
     * Get invitation details by token (for preview before accepting).
     */
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

    /**
     * List pending invitations for the authenticated user.
     */
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

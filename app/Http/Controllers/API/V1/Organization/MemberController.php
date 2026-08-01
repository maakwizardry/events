<?php

namespace App\Http\Controllers\API\V1\Organization;

use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\InviteMemberRequest;
use App\Http\Requests\Organization\UpdateMemberRequest;
use App\Http\Resources\OrganizationMemberResource;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\OrganizationMember;
use App\Models\User;
use App\Notifications\MemberAddedNotification;
use App\Notifications\OrganizationInvitationNotification;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

class MemberController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display a listing of organization members.
     */
    public function index(Request $request, Organization $organization)
    {
        $this->authorize('manageMembers', $organization);

        $members = $organization->members()
            ->with('user')
            ->latest()
            ->paginate(20);

        return OrganizationMemberResource::collection($members);
    }

    /**
     * Add a member to the organization or send an invitation.
     */
    public function store(InviteMemberRequest $request, Organization $organization)
    {
        $this->authorize('manageMembers', $organization);

        $user = User::where('email', $request->email)->first();

        // Case 1: User exists - add them directly
        if ($user) {
            // Check if user is already a member
            if ($organization->hasMember($user)) {
                return response()->json([
                    'message' => 'User is already a member of this organization',
                ], 422);
            }

            $member = $organization->members()->create([
                'user_id' => $user->id,
                'role' => $request->role,
                'joined_at' => now(),
            ]);

            // Send notification to the added user
            $user->notify(new MemberAddedNotification(
                $organization,
                $request->role,
                $request->user()
            ));

            return response()->json([
                'message' => 'Member added successfully',
                'member' => new OrganizationMemberResource($member->load('user')),
            ], 201);
        }

        // Case 2: User doesn't exist - create invitation
        // Check if there's already a pending invitation
        $existingInvitation = OrganizationInvitation::where('organization_id', $organization->id)
            ->where('email', $request->email)
            ->where('status', 'pending')
            ->first();

        if ($existingInvitation) {
            if ($existingInvitation->isPending()) {
                return response()->json([
                    'message' => 'An invitation has already been sent to this email address',
                    'invitation' => [
                        'email' => $existingInvitation->email,
                        'role' => $existingInvitation->role,
                        'expires_at' => $existingInvitation->expires_at,
                    ],
                ], 422);
            }

            // Mark expired invitation as expired and create a new one
            $existingInvitation->markAsExpired();
        }

        // Create new invitation
        $invitation = OrganizationInvitation::create([
            'organization_id' => $organization->id,
            'email' => $request->email,
            'role' => $request->role,
            'invited_by' => $request->user()->id,
        ]);

        // Send invitation email
        Notification::route('mail', $request->email)
            ->notify(new OrganizationInvitationNotification($invitation));

        return response()->json([
            'message' => 'Invitation sent successfully',
            'invitation' => [
                'email' => $invitation->email,
                'role' => $invitation->role,
                'expires_at' => $invitation->expires_at,
                'token' => $invitation->token,
            ],
        ], 201);
    }

    /**
     * Update a member's role.
     */
    public function update(UpdateMemberRequest $request, Organization $organization, OrganizationMember $member)
    {
        $this->authorize('manageMembers', $organization);

        // Prevent updating if not part of this organization
        if ($member->organization_id !== $organization->id) {
            return response()->json([
                'message' => 'Member does not belong to this organization',
            ], 404);
        }

        // Prevent demoting the last owner
        if ($member->role === 'owner' && $request->role !== 'owner') {
            $ownerCount = $organization->members()->where('role', 'owner')->count();
            if ($ownerCount <= 1) {
                return response()->json([
                    'message' => 'Cannot change role. Organization must have at least one owner.',
                ], 422);
            }
        }

        $member->update(['role' => $request->role]);

        return new OrganizationMemberResource($member->load('user'));
    }

    /**
     * Remove a member from the organization.
     */
    public function destroy(Request $request, Organization $organization, OrganizationMember $member)
    {
        $this->authorize('manageMembers', $organization);

        // Prevent removing if not part of this organization
        if ($member->organization_id !== $organization->id) {
            return response()->json([
                'message' => 'Member does not belong to this organization',
            ], 404);
        }

        // Prevent removing the last owner
        if ($member->role === 'owner') {
            $ownerCount = $organization->members()->where('role', 'owner')->count();
            if ($ownerCount <= 1) {
                return response()->json([
                    'message' => 'Cannot remove the last owner. Please assign another owner first.',
                ], 422);
            }
        }

        $member->delete();

        return response()->json([
            'message' => 'Member removed successfully',
        ]);
    }
}

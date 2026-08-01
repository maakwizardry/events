<?php

namespace App\Http\Controllers\API\V1\Organization;

use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\InviteMemberRequest;
use App\Http\Requests\Organization\UpdateMemberRequest;
use App\Http\Resources\OrganizationMemberResource;
use App\Models\Organization;
use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

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
     * Add a member to the organization.
     */
    public function store(InviteMemberRequest $request, Organization $organization)
    {
        $this->authorize('manageMembers', $organization);

        $user = User::where('email', $request->email)->first();

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

        return new OrganizationMemberResource($member->load('user'));
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

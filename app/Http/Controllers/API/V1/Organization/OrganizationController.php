<?php

namespace App\Http\Controllers\API\V1\Organization;

use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\StoreOrganizationRequest;
use App\Http\Requests\Organization\UpdateOrganizationRequest;
use App\Http\Resources\OrganizationResource;
use App\Models\Organization;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

class OrganizationController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display a listing of the user's organizations.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Organization::class);

        $organizations = $request->user()
            ->organizations()
            ->withCount(['members', 'events'])
            ->latest()
            ->paginate(15);

        return OrganizationResource::collection($organizations);
    }

    /**
     * Store a newly created organization.
     */
    public function store(StoreOrganizationRequest $request)
    {
        $this->authorize('create', Organization::class);

        $organization = Organization::create($request->validated());

        // Add the creator as owner
        $organization->members()->create([
            'user_id' => $request->user()->id,
            'role' => 'owner',
            'joined_at' => now(),
        ]);

        return new OrganizationResource($organization->loadCount(['members', 'events']));
    }

    /**
     * Display the specified organization.
     */
    public function show(Organization $organization)
    {
        $this->authorize('view', $organization);

        return new OrganizationResource($organization->loadCount(['members', 'events']));
    }

    /**
     * Update the specified organization.
     */
    public function update(UpdateOrganizationRequest $request, Organization $organization)
    {
        $this->authorize('update', $organization);

        $organization->update($request->validated());

        return new OrganizationResource($organization->loadCount(['members', 'events']));
    }

    /**
     * Remove the specified organization.
     */
    public function destroy(Organization $organization)
    {
        $this->authorize('delete', $organization);

        $organization->delete();

        return response()->json([
            'message' => 'Organization deleted successfully',
        ]);
    }
}

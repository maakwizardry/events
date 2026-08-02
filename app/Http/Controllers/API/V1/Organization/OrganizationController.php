<?php

namespace App\Http\Controllers\API\V1\Organization;

use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\StoreOrganizationRequest;
use App\Http\Requests\Organization\UpdateOrganizationRequest;
use App\Http\Resources\OrganizationResource;
use App\Models\Organization;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class OrganizationController extends Controller
{
    use AuthorizesRequests;

    #[OA\Get(
        path: '/organizations',
        tags: ['Organizations'],
        summary: 'List user organizations',
        description: 'Get all organizations where the authenticated user is a member',
        security: [['sanctum' => []]],
        parameters: [
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
                description: 'List of organizations',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'uuid', type: 'string', example: '550e8400-e29b-41d4-a716-446655440000'),
                                    new OA\Property(property: 'name', type: 'string', example: 'Tech Community'),
                                    new OA\Property(property: 'slug', type: 'string', example: 'tech-community'),
                                    new OA\Property(property: 'description', type: 'string'),
                                    new OA\Property(property: 'logo_url', type: 'string', nullable: true),
                                    new OA\Property(property: 'website_url', type: 'string', nullable: true),
                                    new OA\Property(property: 'members_count', type: 'integer', example: 5),
                                    new OA\Property(property: 'events_count', type: 'integer', example: 12),
                                ]
                            )
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated')
        ]
    )]
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

    #[OA\Post(
        path: '/organizations',
        tags: ['Organizations'],
        summary: 'Create a new organization',
        description: 'Create a new organization and become its owner',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'slug'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Tech Community'),
                    new OA\Property(property: 'slug', type: 'string', example: 'tech-community'),
                    new OA\Property(property: 'description', type: 'string', example: 'A community for tech enthusiasts'),
                    new OA\Property(property: 'logo_url', type: 'string', format: 'url', nullable: true),
                    new OA\Property(property: 'website_url', type: 'string', format: 'url', nullable: true),
                    new OA\Property(property: 'primary_color', type: 'string', example: '#3B82F6'),
                    new OA\Property(property: 'secondary_color', type: 'string', example: '#1E40AF'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Organization created successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'data', type: 'object'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 422, description: 'Validation error')
        ]
    )]
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

    #[OA\Get(
        path: '/organizations/{organization}',
        tags: ['Organizations'],
        summary: 'Get organization details',
        description: 'Get details of a specific organization',
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(
                name: 'organization',
                in: 'path',
                required: true,
                description: 'Organization UUID',
                schema: new OA\Schema(type: 'string', format: 'uuid')
            )
        ],
        responses: [
            new OA\Response(response: 200, description: 'Organization details'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Not authorized'),
            new OA\Response(response: 404, description: 'Organization not found')
        ]
    )]
    public function show(Organization $organization)
    {
        $this->authorize('view', $organization);

        return new OrganizationResource($organization->loadCount(['members', 'events']));
    }

    #[OA\Put(
        path: '/organizations/{organization}',
        tags: ['Organizations'],
        summary: 'Update organization',
        description: 'Update organization details (owner/admin only)',
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(
                name: 'organization',
                in: 'path',
                required: true,
                description: 'Organization UUID',
                schema: new OA\Schema(type: 'string', format: 'uuid')
            )
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'slug', type: 'string'),
                    new OA\Property(property: 'description', type: 'string'),
                    new OA\Property(property: 'logo_url', type: 'string', format: 'url'),
                    new OA\Property(property: 'website_url', type: 'string', format: 'url'),
                    new OA\Property(property: 'primary_color', type: 'string'),
                    new OA\Property(property: 'secondary_color', type: 'string'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Organization updated'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Not authorized'),
            new OA\Response(response: 422, description: 'Validation error')
        ]
    )]
    public function update(UpdateOrganizationRequest $request, Organization $organization)
    {
        $this->authorize('update', $organization);

        $organization->update($request->validated());

        return new OrganizationResource($organization->loadCount(['members', 'events']));
    }

    #[OA\Delete(
        path: '/organizations/{organization}',
        tags: ['Organizations'],
        summary: 'Delete organization',
        description: 'Delete an organization (owner only)',
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(
                name: 'organization',
                in: 'path',
                required: true,
                description: 'Organization UUID',
                schema: new OA\Schema(type: 'string', format: 'uuid')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Organization deleted',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Organization deleted successfully'),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Not authorized - owner only')
        ]
    )]
    public function destroy(Organization $organization)
    {
        $this->authorize('delete', $organization);

        $organization->delete();

        return response()->json([
            'message' => 'Organization deleted successfully',
        ]);
    }
}

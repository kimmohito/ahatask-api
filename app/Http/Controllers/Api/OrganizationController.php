<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use Illuminate\Http\Request;
use OpenApi\Annotations as OA;
use OpenApi\Attributes as OAAttr;

/**
 * @OA\Tag(name="Organizations", description="Organization management")
 */

class OrganizationController extends Controller
{
    #[OAAttr\Get(
        path: '/api/organizations',
        tags: ['Organizations'],
        summary: 'List organizations',
        responses: [new OAAttr\Response(response: 200, description: 'Organizations list')]
    )]
    /**
     * @OA\Get(
     *     path="/api/organizations",
     *     tags={"Organizations"},
     *     summary="List organizations",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Organizations list")
     * )
     */
    public function index()
    {
        return Organization::all();
    }

    #[OAAttr\Post(
        path: '/api/organizations',
        tags: ['Organizations'],
        summary: 'Create an organization',
        requestBody: new OAAttr\RequestBody(
            required: true,
            content: new OAAttr\JsonContent(
                required: ['name'],
                properties: [new OAAttr\Property(property: 'name', type: 'string', example: 'QA Org')]
            )
        ),
        responses: [new OAAttr\Response(response: 200, description: 'Organization created')]
    )]
    /**
     * @OA\Post(
     *     path="/api/organizations",
     *     tags={"Organizations"},
     *     summary="Create an organization",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(required=true, @OA\JsonContent(required={"name"}, @OA\Property(property="name", type="string", example="QA Org"))),
     *     @OA\Response(response=200, description="Organization created")
     * )
     */
    public function store(Request $request)
    {
        $org = Organization::create([
            'name' => $request->name,
        ]);

        // attach creator to org
        $request->user()->update([
            'organization_id' => $org->id
        ]);

        return response()->json($org);
    }

    #[OAAttr\Get(
        path: '/api/organizations/{organization}',
        tags: ['Organizations'],
        summary: 'Get an organization',
        parameters: [new OAAttr\Parameter(name: 'organization', in: 'path', required: true, schema: new OAAttr\Schema(type: 'string'))],
        responses: [new OAAttr\Response(response: 200, description: 'Organization detail')]
    )]
    /**
     * @OA\Get(
     *     path="/api/organizations/{organization}",
     *     tags={"Organizations"},
     *     summary="Get an organization",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="organization", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Organization detail")
     * )
     */
    public function show(Organization $organization)
    {
        return $organization;
    }

    #[OAAttr\Put(
        path: '/api/organizations/{organization}',
        tags: ['Organizations'],
        summary: 'Update an organization',
        parameters: [new OAAttr\Parameter(name: 'organization', in: 'path', required: true, schema: new OAAttr\Schema(type: 'string'))],
        requestBody: new OAAttr\RequestBody(
            required: true,
            content: new OAAttr\JsonContent(
                properties: [new OAAttr\Property(property: 'name', type: 'string', example: 'QA Org Updated')]
            )
        ),
        responses: [new OAAttr\Response(response: 200, description: 'Organization updated')]
    )]
    /**
     * @OA\Put(
     *     path="/api/organizations/{organization}",
     *     tags={"Organizations"},
     *     summary="Update an organization",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="organization", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(@OA\Property(property="name", type="string", example="QA Org Updated"))),
     *     @OA\Response(response=200, description="Organization updated")
     * )
     */
    public function update(Request $request, Organization $organization)
    {
        $organization->update($request->only('name'));

        return $organization;
    }

    #[OAAttr\Delete(
        path: '/api/organizations/{organization}',
        tags: ['Organizations'],
        summary: 'Delete an organization',
        parameters: [new OAAttr\Parameter(name: 'organization', in: 'path', required: true, schema: new OAAttr\Schema(type: 'string'))],
        responses: [new OAAttr\Response(response: 204, description: 'Organization deleted')]
    )]
    /**
     * @OA\Delete(
     *     path="/api/organizations/{organization}",
     *     tags={"Organizations"},
     *     summary="Delete an organization",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="organization", in="path", required=true, @OA\Schema(type="string")),
     *     @OA\Response(response=204, description="Organization deleted")
     * )
     */
    public function destroy(Organization $organization)
    {
        $organization->delete();

        return response()->noContent();
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use Illuminate\Http\Request;

class OrganizationController extends Controller
{
    public function index()
    {
        return Organization::all();
    }

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

    public function show(Organization $organization)
    {
        return $organization;
    }

    public function update(Request $request, Organization $organization)
    {
        $organization->update($request->only('name'));

        return $organization;
    }

    public function destroy(Organization $organization)
    {
        $organization->delete();

        return response()->noContent();
    }
}

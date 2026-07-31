<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Example team-scoped API resource. The BelongsToTeam global scope
 * (bound by SetTeamFromApiToken) already restricts every query to the
 * token's team; policies enforce per-role permissions on top.
 */
class ProjectController extends Controller
{
    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', Project::class);

        return response()->json([
            'data' => Project::query()->latest()->paginate(25),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', Project::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:65535'],
        ]);

        $project = Project::create($validated);

        return response()->json(['data' => $project], 201);
    }

    public function show(Project $project): JsonResponse
    {
        Gate::authorize('view', $project);

        return response()->json(['data' => $project]);
    }

    public function update(Request $request, Project $project): JsonResponse
    {
        Gate::authorize('update', $project);

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:65535'],
        ]);

        $project->update($validated);

        return response()->json(['data' => $project]);
    }

    public function destroy(Project $project): JsonResponse
    {
        Gate::authorize('delete', $project);

        $project->delete();

        return response()->json(null, 204);
    }
}

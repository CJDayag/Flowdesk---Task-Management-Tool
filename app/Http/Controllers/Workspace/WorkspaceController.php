<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Http\Requests\Workspace\StoreWorkspaceRequest;
use App\Http\Requests\Workspace\UpdateWorkspaceSettingsRequest;
use App\Models\Workspace;
use App\Support\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;

class WorkspaceController extends Controller
{
    /**
     * Create a new workspace and attach the current user as owner.
     */
    public function store(StoreWorkspaceRequest $request): RedirectResponse
    {
        $user = $request->user();

        $workspace = Workspace::create([
            'owner_id' => $user->id,
            'name' => $request->string('name')->toString(),
            'slug' => Workspace::uniqueSlugFor($request->string('name')->toString()),
            'theme' => $request->string('theme')->toString() ?: 'system',
        ]);

        $workspace->members()->attach($user->id, [
            'role' => 'owner',
            'joined_at' => now(),
        ]);

        $user->forceFill(['current_workspace_id' => $workspace->id])->save();

        ActivityLogger::log(
            $user,
            $workspace,
            'workspace.created',
            'Created workspace '.$workspace->name,
            $workspace,
        );

        return to_route('dashboard')->with('status', 'Workspace created successfully.');
    }

    /**
     * Update workspace settings such as name, logo and theme.
     */
    public function update(UpdateWorkspaceSettingsRequest $request, Workspace $workspace): RedirectResponse
    {
        $this->authorize('update', $workspace);

        $validated = $request->validated();

        if ($request->hasFile('logo')) {
            if ($workspace->logo_path) {
                Storage::disk('public')->delete($workspace->logo_path);
            }

            $validated['logo_path'] = $request->file('logo')->store('workspace-logos', 'public');
        }

        unset($validated['logo']);

        if (($validated['name'] ?? null) && $validated['name'] !== $workspace->name) {
            $validated['slug'] = Workspace::uniqueSlugFor($validated['name']);
        }

        $workspace->update($validated);

        ActivityLogger::log(
            $request->user(),
            $workspace,
            'workspace.updated',
            'Updated workspace settings',
            $workspace,
        );

        return back()->with('status', 'Workspace settings updated.');
    }
}

<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Support\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PreferenceController extends Controller
{
    /**
     * Update user interface preferences.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'compact_view' => ['required', 'boolean'],
        ]);

        $user = $request->user();

        $user->forceFill([
            'compact_view' => (bool) $validated['compact_view'],
        ])->save();

        ActivityLogger::log(
            $user,
            $user->currentWorkspace,
            'preferences.updated',
            $user->name.' updated UI preferences',
            $user,
            ['compact_view' => (bool) $validated['compact_view']],
        );

        return back()->with('status', 'Preferences updated.');
    }
}

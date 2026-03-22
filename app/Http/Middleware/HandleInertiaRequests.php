<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $notifications = $user
            ? $user->notifications()
                ->latest()
                ->limit(10)
                ->get()
                ->map(fn ($notification): array => [
                    'id' => $notification->id,
                    'read_at' => $notification->read_at,
                    'created_at' => $notification->created_at,
                    'data' => $notification->data,
                ])
            : collect();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'flash' => [
                'status' => $request->session()->get('status'),
                'error' => $request->session()->get('error'),
            ],
            'auth' => [
                'user' => $user,
                'currentWorkspace' => $user?->currentWorkspace,
                'currentWorkspaceRole' => $user && $user->currentWorkspace
                    ? $user->roleInWorkspace($user->currentWorkspace)?->value
                    : null,
                'notifications' => $notifications,
                'unreadNotificationsCount' => $user?->unreadNotifications()->count() ?? 0,
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}

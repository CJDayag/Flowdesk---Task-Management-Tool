<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ActivityLogController extends Controller
{
    /**
     * Display workspace audit trail entries.
     */
    public function index(Request $request): Response
    {
        $workspace = $request->user()?->currentWorkspace;

        abort_unless($workspace instanceof Workspace, 403, 'No active workspace selected.');

        $actions = [
            'all',
            'task.created',
            'task.updated',
            'task.deleted',
            'task.status_changed',
            'task.moved',
            'member.role_updated',
            'member.removed',
        ];

        $actionFilter = $request->string('action')->toString() ?: 'all';

        $query = ActivityLog::query()
            ->with('user:id,name,email')
            ->where('workspace_id', $workspace->id)
            ->latest();

        if ($actionFilter !== 'all') {
            $query->where('action', $actionFilter);
        }

        $logs = $query->paginate(25)->withQueryString();

        return Inertia::render('activity-logs/index', [
            'logs' => $logs,
            'actionFilter' => $actionFilter,
            'actions' => $actions,
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Task;
use App\Models\Workspace;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Display the dashboard with workspace-scoped insights.
     */
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $workspace = $user?->currentWorkspace;

        if (! $workspace instanceof Workspace) {
            return Inertia::render('dashboard', [
                'workspace' => null,
                'stats' => [
                    'openTasks' => 0,
                    'completedThisWeek' => 0,
                    'overdueTasks' => 0,
                    'activeProjects' => 0,
                ],
                'myWork' => [
                    'overdue' => [],
                    'dueToday' => [],
                    'inProgress' => [],
                ],
                'upcomingDeadlines' => [],
                'recentActivity' => [
                    'data' => [],
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => 8,
                    'total' => 0,
                ],
                'projectProgress' => [],
                'notificationsSnapshot' => [],
                'analytics' => [
                    'tasksCompletedByDay' => [],
                    'tasksCompletedByWeek' => [],
                    'tasksByStatus' => [],
                    'userProductivity' => [],
                    'activityTimeline' => [],
                ],
            ]);
        }

        $projects = $workspace->projects()
            ->withCount([
                'tasks as tasks_total_count',
                'tasks as tasks_done_count' => fn (Builder $query): Builder => $query->where('status', 'done'),
                'tasks as tasks_in_progress_count' => fn (Builder $query): Builder => $query->where('status', 'in_progress'),
                'tasks as tasks_overdue_count' => fn (Builder $query): Builder => $query
                    ->where('status', '!=', 'done')
                    ->whereNotNull('due_at')
                    ->where('due_at', '<', now()),
            ])
            ->orderBy('name')
            ->get();

        $visibleProjects = $projects->filter(fn (Project $project): bool => $project->userCanAccess($user));
        $visibleProjectIds = $visibleProjects->pluck('id');

        $taskScope = Task::query()
            ->where('workspace_id', $workspace->id)
            ->where(function (Builder $query) use ($visibleProjectIds): void {
                $query->whereNull('project_id');

                if ($visibleProjectIds->isNotEmpty()) {
                    $query->orWhereIn('project_id', $visibleProjectIds->all());
                }
            });

        $myTaskScope = Task::query()
            ->where('workspace_id', $workspace->id)
            ->where(function (Builder $query) use ($visibleProjectIds): void {
                $query->whereNull('project_id');

                if ($visibleProjectIds->isNotEmpty()) {
                    $query->orWhereIn('project_id', $visibleProjectIds->all());
                }
            })
            ->where(function (Builder $query) use ($user): void {
                $query
                    ->where('assigned_to', $user->id)
                    ->orWhereHas('assignees', fn (Builder $assignees): Builder => $assignees->where('users.id', $user->id));
            });

        $stats = [
            'openTasks' => (clone $taskScope)->where('status', '!=', 'done')->count(),
            'completedThisWeek' => (clone $taskScope)
                ->where('status', 'done')
                ->whereBetween('updated_at', [now()->startOfWeek(), now()->endOfWeek()])
                ->count(),
            'overdueTasks' => (clone $taskScope)
                ->where('status', '!=', 'done')
                ->whereNotNull('due_at')
                ->where('due_at', '<', now())
                ->count(),
            'activeProjects' => $visibleProjects->count(),
        ];

        $mapTask = fn (Task $task): array => [
            'id' => $task->id,
            'title' => $task->title,
            'status' => $task->status,
            'due_at' => $task->due_at?->toDateTimeString(),
            'project' => $task->project ? [
                'id' => $task->project->id,
                'name' => $task->project->name,
            ] : null,
        ];

        $myWork = [
            'overdue' => (clone $myTaskScope)
                ->with('project:id,name')
                ->where('status', '!=', 'done')
                ->whereNotNull('due_at')
                ->where('due_at', '<', now())
                ->orderBy('due_at')
                ->limit(8)
                ->get()
                ->map($mapTask)
                ->all(),
            'dueToday' => (clone $myTaskScope)
                ->with('project:id,name')
                ->where('status', '!=', 'done')
                ->whereDate('due_at', now()->toDateString())
                ->orderBy('due_at')
                ->limit(8)
                ->get()
                ->map($mapTask)
                ->all(),
            'inProgress' => (clone $myTaskScope)
                ->with('project:id,name')
                ->where('status', 'in_progress')
                ->orderByDesc('updated_at')
                ->limit(8)
                ->get()
                ->map($mapTask)
                ->all(),
        ];

        $upcomingDeadlines = (clone $taskScope)
            ->with(['project:id,name', 'assignee:id,name'])
            ->where('status', '!=', 'done')
            ->whereNotNull('due_at')
            ->whereBetween('due_at', [now(), now()->addDays(7)->endOfDay()])
            ->orderBy('due_at')
            ->limit(12)
            ->get()
            ->map(fn (Task $task): array => [
                'id' => $task->id,
                'title' => $task->title,
                'due_at' => $task->due_at?->toDateTimeString(),
                'project' => $task->project ? [
                    'id' => $task->project->id,
                    'name' => $task->project->name,
                ] : null,
                'assignee' => $task->assignee ? [
                    'id' => $task->assignee->id,
                    'name' => $task->assignee->name,
                ] : null,
            ])
            ->all();

        $recentActivity = $workspace->activityLogs()
            ->with('user:id,name')
            ->latest()
            ->paginate(8, ['*'], 'activity_page')
            ->withQueryString();

        $recentActivity->setCollection(
            $recentActivity->getCollection()->map(fn ($activity): array => [
                'id' => $activity->id,
                'action' => $activity->action,
                'description' => $activity->description,
                'created_at' => $activity->created_at,
                'user' => $activity->user ? [
                    'id' => $activity->user->id,
                    'name' => $activity->user->name,
                ] : null,
            ])->values(),
        );

        $projectProgress = $visibleProjects
            ->map(fn (Project $project): array => [
                'id' => $project->id,
                'name' => $project->name,
                'visibility' => $project->visibility->value,
                'total' => (int) $project->tasks_total_count,
                'done' => (int) $project->tasks_done_count,
                'in_progress' => (int) $project->tasks_in_progress_count,
                'overdue' => (int) $project->tasks_overdue_count,
                'completion' => (int) ((int) $project->tasks_total_count > 0
                    ? round(((int) $project->tasks_done_count / (int) $project->tasks_total_count) * 100)
                    : 0),
            ])
            ->values()
            ->all();

        $notificationsSnapshot = $user->unreadNotifications()
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn ($notification): array => [
                'id' => $notification->id,
                'created_at' => $notification->created_at,
                'data' => $notification->data,
            ])
            ->all();

        $today = CarbonImmutable::now();
        $startDayWindow = $today->subDays(13)->startOfDay();
        $endDayWindow = $today->endOfDay();

        $completedByDayCounts = (clone $taskScope)
            ->where('status', 'done')
            ->whereBetween('updated_at', [$startDayWindow, $endDayWindow])
            ->selectRaw('DATE(updated_at) as day, COUNT(*) as total')
            ->groupBy(DB::raw('DATE(updated_at)'))
            ->pluck('total', 'day');

        $tasksCompletedByDay = collect(range(13, 0))
            ->map(function (int $daysAgo) use ($today, $completedByDayCounts): array {
                $day = $today->subDays($daysAgo);
                $dayKey = $day->toDateString();

                return [
                    'date' => $dayKey,
                    'label' => $day->format('M j'),
                    'count' => (int) ($completedByDayCounts[$dayKey] ?? 0),
                ];
            })
            ->values()
            ->all();

        $tasksCompletedByWeek = collect(range(7, 0))
            ->map(function (int $weeksAgo) use ($today, $taskScope): array {
                $weekStart = $today->subWeeks($weeksAgo)->startOfWeek();
                $weekEnd = $weekStart->endOfWeek();

                return [
                    'week_start' => $weekStart->toDateString(),
                    'week_end' => $weekEnd->toDateString(),
                    'label' => $weekStart->format('M j'),
                    'count' => (int) (clone $taskScope)
                        ->where('status', 'done')
                        ->whereBetween('updated_at', [$weekStart, $weekEnd])
                        ->count(),
                ];
            })
            ->values()
            ->all();

        $statusCounts = (clone $taskScope)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $tasksByStatus = collect([
            ['status' => 'todo', 'label' => 'To Do', 'count' => (int) ($statusCounts['todo'] ?? 0)],
            ['status' => 'in_progress', 'label' => 'In Progress', 'count' => (int) ($statusCounts['in_progress'] ?? 0)],
            ['status' => 'done', 'label' => 'Done', 'count' => (int) ($statusCounts['done'] ?? 0)],
        ])->values()->all();

        $members = $workspace->members()->select('users.id', 'users.name')->orderBy('users.name')->get();

        $userProductivity = $members
            ->map(function ($member) use ($taskScope, $today): array {
                $completedThisWeek = (int) (clone $taskScope)
                    ->where('status', 'done')
                    ->where('assigned_to', $member->id)
                    ->whereBetween('updated_at', [$today->startOfWeek(), $today->endOfWeek()])
                    ->count();

                $inProgress = (int) (clone $taskScope)
                    ->where('status', 'in_progress')
                    ->where('assigned_to', $member->id)
                    ->count();

                $overdue = (int) (clone $taskScope)
                    ->where('status', '!=', 'done')
                    ->where('assigned_to', $member->id)
                    ->whereNotNull('due_at')
                    ->where('due_at', '<', $today)
                    ->count();

                return [
                    'user_id' => $member->id,
                    'name' => $member->name,
                    'completed_this_week' => $completedThisWeek,
                    'in_progress' => $inProgress,
                    'overdue' => $overdue,
                ];
            })
            ->sortByDesc('completed_this_week')
            ->values()
            ->take(8)
            ->all();

        $activityCounts = $workspace->activityLogs()
            ->whereBetween('created_at', [$startDayWindow, $endDayWindow])
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->groupBy(DB::raw('DATE(created_at)'))
            ->pluck('total', 'day');

        $activityTimeline = collect(range(13, 0))
            ->map(function (int $daysAgo) use ($today, $activityCounts): array {
                $day = $today->subDays($daysAgo);
                $dayKey = $day->toDateString();

                return [
                    'date' => $dayKey,
                    'label' => $day->format('M j'),
                    'events' => (int) ($activityCounts[$dayKey] ?? 0),
                ];
            })
            ->values()
            ->all();

        $analytics = [
            'tasksCompletedByDay' => $tasksCompletedByDay,
            'tasksCompletedByWeek' => $tasksCompletedByWeek,
            'tasksByStatus' => $tasksByStatus,
            'userProductivity' => $userProductivity,
            'activityTimeline' => $activityTimeline,
        ];

        return Inertia::render('dashboard', [
            'workspace' => [
                'id' => $workspace->id,
                'name' => $workspace->name,
            ],
            'stats' => $stats,
            'myWork' => $myWork,
            'upcomingDeadlines' => $upcomingDeadlines,
            'recentActivity' => $recentActivity,
            'projectProgress' => $projectProgress,
            'notificationsSnapshot' => $notificationsSnapshot,
            'analytics' => $analytics,
        ]);
    }
}

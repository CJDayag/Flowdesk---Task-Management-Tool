import { Head, router } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import {
    ChartContainer,
    ChartLegend,
    ChartLegendContent,
    ChartTooltip,
    ChartTooltipContent,
    type ChartConfig,
} from '@/components/ui/chart';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';
import {
    Bar,
    BarChart,
    Cell,
    CartesianGrid,
    Line,
    LineChart,
    Pie,
    PieChart,
    XAxis,
    YAxis,
} from 'recharts';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
    },
];

const completionByDayChartConfig = {
    count: {
        label: 'Completed',
        color: '#0ea5e9',
    },
} satisfies ChartConfig;

const completionByWeekChartConfig = {
    count: {
        label: 'Completed',
        color: '#14b8a6',
    },
} satisfies ChartConfig;

const tasksByStatusChartConfig = {
    todo: {
        label: 'To Do',
        color: '#94a3b8',
    },
    in_progress: {
        label: 'In Progress',
        color: '#3b82f6',
    },
    done: {
        label: 'Done',
        color: '#22c55e',
    },
} satisfies ChartConfig;

const productivityChartConfig = {
    completed_this_week: {
        label: 'Completed',
        color: '#22c55e',
    },
    in_progress: {
        label: 'In progress',
        color: '#3b82f6',
    },
    overdue: {
        label: 'Overdue',
        color: '#ef4444',
    },
} satisfies ChartConfig;

const timelineChartConfig = {
    events: {
        label: 'Events',
        color: '#f59e0b',
    },
} satisfies ChartConfig;

type DashboardTask = {
    id: number;
    title: string;
    status: 'todo' | 'in_progress' | 'done';
    due_at?: string | null;
    project?: { id: number; name: string } | null;
    assignee?: { id: number; name: string } | null;
};

type ActivityItem = {
    id: number;
    action: string;
    description: string;
    created_at: string;
    user?: { id: number; name: string } | null;
};

type PaginatedActivity = {
    data: ActivityItem[];
    current_page: number;
    last_page: number;
    per_page?: number;
    total?: number;
};

type ProjectProgress = {
    id: number;
    name: string;
    visibility: 'public' | 'private';
    total: number;
    done: number;
    in_progress: number;
    overdue: number;
    completion: number;
};

type NotificationSnapshot = {
    id: string;
    created_at: string;
    data: {
        title?: string;
        message?: string;
        url?: string;
        [key: string]: unknown;
    };
};

type DashboardAnalytics = {
    tasksCompletedByDay: Array<{ date: string; label: string; count: number }>;
    tasksCompletedByWeek: Array<{ week_start: string; week_end: string; label: string; count: number }>;
    tasksByStatus: Array<{ status: 'todo' | 'in_progress' | 'done'; label: string; count: number }>;
    userProductivity: Array<{
        user_id: number;
        name: string;
        completed_this_week: number;
        in_progress: number;
        overdue: number;
    }>;
    activityTimeline: Array<{ date: string; label: string; events: number }>;
};

const StatCard = ({ label, value }: { label: string; value: number }) => (
    <div className="rounded-xl border border-sidebar-border/70 bg-card p-4 dark:border-sidebar-border">
        <p className="text-xs uppercase tracking-wide text-muted-foreground">{label}</p>
        <p className="mt-2 text-2xl font-semibold">{value}</p>
    </div>
);

const TaskList = ({ title, tasks }: { title: string; tasks: DashboardTask[] }) => (
    <div className="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
        <Heading variant="small" title={title} />
        <div className="mt-3 space-y-2">
            {tasks.length > 0 ? tasks.map((task) => (
                <article key={task.id} className="rounded-md border border-border/60 p-3">
                    <p className="text-sm font-medium">{task.title}</p>
                    <p className="text-xs text-muted-foreground">
                        {task.project?.name ?? 'No project'}
                        {task.due_at ? ` • due ${new Date(task.due_at).toLocaleDateString()}` : ''}
                    </p>
                </article>
            )) : (
                <p className="text-sm text-muted-foreground">No tasks in this section.</p>
            )}
        </div>
    </div>
);

export default function Dashboard({
    workspace,
    stats,
    myWork,
    upcomingDeadlines,
    recentActivity,
    projectProgress,
    notificationsSnapshot,
    analytics,
}: {
    workspace: { id: number; name: string } | null;
    stats: {
        openTasks: number;
        completedThisWeek: number;
        overdueTasks: number;
        activeProjects: number;
    };
    myWork: {
        overdue: DashboardTask[];
        dueToday: DashboardTask[];
        inProgress: DashboardTask[];
    };
    upcomingDeadlines: DashboardTask[];
    recentActivity: PaginatedActivity;
    projectProgress: ProjectProgress[];
    notificationsSnapshot: NotificationSnapshot[];
    analytics: DashboardAnalytics;
}) {
    const statusColors: Record<'todo' | 'in_progress' | 'done', string> = {
        todo: '#94a3b8',
        in_progress: '#3b82f6',
        done: '#22c55e',
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />

            <div className="space-y-4 p-4">
                <div className="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                    <Heading
                        variant="small"
                        title={workspace ? `${workspace.name} Overview` : 'Workspace Overview'}
                        description="Track what needs attention today and what moved recently."
                    />
                </div>

                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <StatCard label="Open Tasks" value={stats.openTasks} />
                    <StatCard label="Completed This Week" value={stats.completedThisWeek} />
                    <StatCard label="Overdue Tasks" value={stats.overdueTasks} />
                    <StatCard label="Active Projects" value={stats.activeProjects} />
                </div>

                <div className="grid gap-4 xl:grid-cols-2">
                    <div className="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                        <Heading variant="small" title="Tasks Completed (Daily)" description="Last 14 days" />
                        <div className="mt-3 h-72">
                            <ChartContainer config={completionByDayChartConfig} className="h-full w-full">
                                <BarChart data={analytics.tasksCompletedByDay}>
                                    <CartesianGrid strokeDasharray="3 3" />
                                    <XAxis dataKey="label" tick={{ fontSize: 12 }} />
                                    <YAxis allowDecimals={false} tick={{ fontSize: 12 }} />
                                    <ChartTooltip content={<ChartTooltipContent />} />
                                    <Bar dataKey="count" fill="var(--color-count)" radius={[4, 4, 0, 0]} />
                                </BarChart>
                            </ChartContainer>
                        </div>
                    </div>

                    <div className="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                        <Heading variant="small" title="Tasks Completed (Weekly)" description="Last 8 weeks" />
                        <div className="mt-3 h-72">
                            <ChartContainer config={completionByWeekChartConfig} className="h-full w-full">
                                <BarChart data={analytics.tasksCompletedByWeek}>
                                    <CartesianGrid strokeDasharray="3 3" />
                                    <XAxis dataKey="label" tick={{ fontSize: 12 }} />
                                    <YAxis allowDecimals={false} tick={{ fontSize: 12 }} />
                                    <ChartTooltip content={<ChartTooltipContent />} />
                                    <Bar dataKey="count" fill="var(--color-count)" radius={[4, 4, 0, 0]} />
                                </BarChart>
                            </ChartContainer>
                        </div>
                    </div>
                </div>

                <div className="grid gap-4 xl:grid-cols-2">
                    <div className="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                        <Heading variant="small" title="Tasks by Status" description="Current workspace distribution" />
                        <div className="mt-3 h-72">
                            <ChartContainer config={tasksByStatusChartConfig} className="h-full w-full">
                                <PieChart>
                                    <Pie
                                        data={analytics.tasksByStatus}
                                        dataKey="count"
                                        nameKey="label"
                                        innerRadius={56}
                                        outerRadius={92}
                                        paddingAngle={2}
                                    >
                                        {analytics.tasksByStatus.map((entry) => (
                                            <Cell key={entry.status} fill={statusColors[entry.status]} />
                                        ))}
                                    </Pie>
                                    <ChartTooltip content={<ChartTooltipContent nameKey="status" />} />
                                    <ChartLegend content={<ChartLegendContent nameKey="status" />} />
                                </PieChart>
                            </ChartContainer>
                        </div>
                    </div>

                    <div className="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                        <Heading variant="small" title="User Productivity" description="Completed this week, in progress, and overdue" />
                        <div className="mt-3 h-72">
                            <ChartContainer config={productivityChartConfig} className="h-full w-full">
                                <BarChart data={analytics.userProductivity}>
                                    <CartesianGrid strokeDasharray="3 3" />
                                    <XAxis dataKey="name" tick={{ fontSize: 12 }} />
                                    <YAxis allowDecimals={false} tick={{ fontSize: 12 }} />
                                    <ChartTooltip content={<ChartTooltipContent />} />
                                    <ChartLegend content={<ChartLegendContent />} />
                                    <Bar dataKey="completed_this_week" name="completed_this_week" fill="var(--color-completed_this_week)" radius={[4, 4, 0, 0]} />
                                    <Bar dataKey="in_progress" name="in_progress" fill="var(--color-in_progress)" radius={[4, 4, 0, 0]} />
                                    <Bar dataKey="overdue" name="overdue" fill="var(--color-overdue)" radius={[4, 4, 0, 0]} />
                                </BarChart>
                            </ChartContainer>
                        </div>
                    </div>
                </div>

                <div className="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                    <Heading variant="small" title="Activity Timeline" description="Event volume across the last 14 days" />
                    <div className="mt-3 h-72">
                        <ChartContainer config={timelineChartConfig} className="h-full w-full">
                            <LineChart data={analytics.activityTimeline}>
                                <CartesianGrid strokeDasharray="3 3" />
                                <XAxis dataKey="label" tick={{ fontSize: 12 }} />
                                <YAxis allowDecimals={false} tick={{ fontSize: 12 }} />
                                <ChartTooltip content={<ChartTooltipContent />} />
                                <Line type="monotone" dataKey="events" stroke="var(--color-events)" strokeWidth={2.5} dot={{ r: 3 }} />
                            </LineChart>
                        </ChartContainer>
                    </div>
                </div>

                <div className="grid gap-4 xl:grid-cols-3">
                    <TaskList title="My Overdue" tasks={myWork.overdue} />
                    <TaskList title="Due Today" tasks={myWork.dueToday} />
                    <TaskList title="In Progress" tasks={myWork.inProgress} />
                </div>

                <div className="grid gap-4 xl:grid-cols-2">
                    <div className="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                        <Heading variant="small" title="Upcoming Deadlines" />
                        <div className="mt-3 space-y-2">
                            {upcomingDeadlines.length > 0 ? upcomingDeadlines.map((task) => (
                                <article key={task.id} className="rounded-md border border-border/60 p-3">
                                    <p className="text-sm font-medium">{task.title}</p>
                                    <p className="text-xs text-muted-foreground">
                                        {task.project?.name ?? 'No project'} • due {task.due_at ? new Date(task.due_at).toLocaleString() : 'N/A'}
                                    </p>
                                </article>
                            )) : (
                                <p className="text-sm text-muted-foreground">No upcoming deadlines.</p>
                            )}
                        </div>
                    </div>

                    <div className="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                        <Heading variant="small" title="Notifications Snapshot" />
                        <div className="mt-3 space-y-2">
                            {notificationsSnapshot.length > 0 ? notificationsSnapshot.map((notification) => (
                                <article key={notification.id} className="rounded-md border border-border/60 p-3">
                                    <p className="text-sm font-medium">{notification.data.title ?? 'Notification'}</p>
                                    <p className="text-xs text-muted-foreground">{notification.data.message ?? ''}</p>
                                </article>
                            )) : (
                                <p className="text-sm text-muted-foreground">No unread notifications.</p>
                            )}
                            <Button asChild variant="outline" className="w-full">
                                <a href="/projects">Go to Projects</a>
                            </Button>
                        </div>
                    </div>
                </div>

                <div className="grid gap-4 xl:grid-cols-2">
                    <div className="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                        <Heading variant="small" title="Recent Activity" />
                        <div className="mt-3 space-y-2">
                            {recentActivity.data.length > 0 ? recentActivity.data.map((activity) => (
                                <article key={activity.id} className="rounded-md border border-border/60 p-3">
                                    <p className="text-sm font-medium">{activity.description}</p>
                                    <p className="text-xs text-muted-foreground">
                                        {activity.user?.name ?? 'System'} • {new Date(activity.created_at).toLocaleString()}
                                    </p>
                                </article>
                            )) : (
                                <p className="text-sm text-muted-foreground">No recent activity yet.</p>
                            )}

                            <div className="flex items-center justify-between pt-1">
                                <Button
                                    type="button"
                                    variant="outline"
                                    disabled={recentActivity.current_page <= 1}
                                    onClick={() => {
                                        router.get('/dashboard', {
                                            activity_page: recentActivity.current_page - 1,
                                        }, {
                                            preserveState: true,
                                            preserveScroll: true,
                                            replace: true,
                                        });
                                    }}
                                >
                                    Previous
                                </Button>

                                <span className="text-xs text-muted-foreground">
                                    Page {recentActivity.current_page} of {recentActivity.last_page}
                                </span>

                                <Button
                                    type="button"
                                    variant="outline"
                                    disabled={recentActivity.current_page >= recentActivity.last_page}
                                    onClick={() => {
                                        router.get('/dashboard', {
                                            activity_page: recentActivity.current_page + 1,
                                        }, {
                                            preserveState: true,
                                            preserveScroll: true,
                                            replace: true,
                                        });
                                    }}
                                >
                                    Next
                                </Button>
                            </div>
                        </div>
                    </div>

                    <div className="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                        <Heading variant="small" title="Project Progress" />
                        <div className="mt-3 space-y-2">
                            {projectProgress.length > 0 ? projectProgress.map((project) => (
                                <article key={project.id} className="rounded-md border border-border/60 p-3">
                                    <div className="flex items-center justify-between gap-2">
                                        <p className="text-sm font-medium">{project.name}</p>
                                        <span className="text-xs text-muted-foreground">{project.completion}%</span>
                                    </div>
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        {project.done}/{project.total} done • {project.in_progress} in progress • {project.overdue} overdue
                                    </p>
                                    <div className="mt-2 h-2 rounded-full bg-muted">
                                        <div
                                            className="h-2 rounded-full bg-emerald-500"
                                            style={{ width: `${project.completion}%` }}
                                        />
                                    </div>
                                </article>
                            )) : (
                                <p className="text-sm text-muted-foreground">No projects to display.</p>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}

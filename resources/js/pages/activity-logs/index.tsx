import { Head, router } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

type LogUser = {
    id: number;
    name: string;
    email: string;
};

type ActivityLogItem = {
    id: number;
    action: string;
    description: string;
    properties?: Record<string, unknown> | null;
    created_at: string;
    user?: LogUser | null;
};

type PaginatedLogs = {
    data: ActivityLogItem[];
    current_page: number;
    last_page: number;
};

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Activity Logs',
        href: '/activity-logs',
    },
];

export default function ActivityLogsPage({
    logs,
    actionFilter,
    actions,
}: {
    logs: PaginatedLogs;
    actionFilter: string;
    actions: string[];
}) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Activity Logs" />

            <div className="space-y-4 p-4">
                <div className="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                    <Heading
                        variant="small"
                        title="Audit Trail"
                        description="Track task lifecycle, status transitions, and member actions across your workspace."
                    />

                    <div className="mt-3 flex items-center gap-2">
                        <label htmlFor="action-filter" className="text-sm text-muted-foreground">
                            Filter
                        </label>
                        <select
                            id="action-filter"
                            className="h-10 rounded-md border border-input bg-background px-3 text-sm"
                            value={actionFilter}
                            onChange={(event) => {
                                router.get('/activity-logs', {
                                    action: event.target.value,
                                }, {
                                    preserveState: true,
                                    preserveScroll: true,
                                });
                            }}
                        >
                            {actions.map((action) => (
                                <option key={action} value={action}>
                                    {action}
                                </option>
                            ))}
                        </select>
                    </div>
                </div>

                <div className="space-y-2 rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                    {logs.data.length > 0 ? (
                        logs.data.map((log) => (
                            <article key={log.id} className="rounded-lg border border-border/60 bg-card p-3">
                                <div className="flex flex-wrap items-center justify-between gap-2">
                                    <p className="text-sm font-medium">{log.description}</p>
                                    <span className="text-xs text-muted-foreground">{new Date(log.created_at).toLocaleString()}</span>
                                </div>
                                <p className="mt-1 text-xs text-muted-foreground">
                                    {log.user?.name ?? 'System'} • {log.action}
                                </p>
                            </article>
                        ))
                    ) : (
                        <p className="text-sm text-muted-foreground">No activity logs found for this filter.</p>
                    )}
                </div>

                <div className="flex items-center justify-between">
                    <Button
                        variant="outline"
                        disabled={logs.current_page <= 1}
                        onClick={() => {
                            router.get('/activity-logs', {
                                action: actionFilter,
                                page: logs.current_page - 1,
                            }, {
                                preserveState: true,
                                preserveScroll: true,
                            });
                        }}
                    >
                        Previous
                    </Button>
                    <span className="text-sm text-muted-foreground">
                        Page {logs.current_page} of {logs.last_page}
                    </span>
                    <Button
                        variant="outline"
                        disabled={logs.current_page >= logs.last_page}
                        onClick={() => {
                            router.get('/activity-logs', {
                                action: actionFilter,
                                page: logs.current_page + 1,
                            }, {
                                preserveState: true,
                                preserveScroll: true,
                            });
                        }}
                    >
                        Next
                    </Button>
                </div>
            </div>
        </AppLayout>
    );
}

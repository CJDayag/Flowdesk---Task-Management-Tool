import { router, usePage } from '@inertiajs/react';
import { Bell } from 'lucide-react';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

export function AppSidebarHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItemType[];
}) {
    const compactView = Boolean(usePage().props.auth?.user?.compact_view);
    const notifications = usePage().props.auth?.notifications ?? [];
    const unreadCount = usePage().props.auth?.unreadNotificationsCount ?? 0;

    const markRead = (id: string) => {
        router.post(`/notifications/${id}/read`, {}, {
            preserveScroll: true,
            preserveState: true,
        });
    };

    const markAllRead = () => {
        router.post('/notifications/read-all', {}, {
            preserveScroll: true,
            preserveState: true,
        });
    };

    return (
        <header
            className={cn(
                'flex shrink-0 items-center gap-2 border-b border-sidebar-border/50 transition-[width,height] ease-linear',
                compactView
                    ? 'h-12 px-3 group-has-data-[collapsible=icon]/sidebar-wrapper:h-10 md:px-2'
                    : 'h-16 px-6 group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4',
            )}
        >
            <div className="flex items-center gap-2">
                <SidebarTrigger className="-ml-1" />
                <Breadcrumbs breadcrumbs={breadcrumbs} />
            </div>

            <div className="ml-auto">
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button
                            variant="ghost"
                            size="icon"
                            className="group relative h-9 w-9 cursor-pointer"
                        >
                            <Bell className="size-5 opacity-80 group-hover:opacity-100" />
                            {unreadCount > 0 && (
                                <span className="absolute -top-0.5 -right-0.5 inline-flex min-w-4 items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-semibold text-white">
                                    {unreadCount > 9 ? '9+' : unreadCount}
                                </span>
                            )}
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" className="w-96 max-w-[90vw]">
                        <DropdownMenuLabel className="flex items-center justify-between">
                            <span>Notifications</span>
                            {unreadCount > 0 && (
                                <button
                                    type="button"
                                    className="text-xs text-blue-600 hover:underline"
                                    onClick={markAllRead}
                                >
                                    Mark all as read
                                </button>
                            )}
                        </DropdownMenuLabel>
                        <DropdownMenuSeparator />
                        {notifications.length === 0 ? (
                            <div className="px-2 py-4 text-center text-sm text-muted-foreground">
                                No notifications yet.
                            </div>
                        ) : (
                            notifications.map((notification) => (
                                <DropdownMenuItem
                                    key={notification.id}
                                    className="items-start"
                                    onClick={() => {
                                        if (!notification.read_at) {
                                            markRead(notification.id);
                                        }

                                        const url = notification.data.url;

                                        if (typeof url === 'string' && url.length > 0) {
                                            router.get(url);
                                        }
                                    }}
                                >
                                    <div className="flex w-full flex-col gap-1">
                                        <div className="flex items-center justify-between gap-2">
                                            <p className="text-sm font-medium">
                                                {notification.data.title ?? 'Notification'}
                                            </p>
                                            {!notification.read_at && (
                                                <span className="size-2 rounded-full bg-blue-500" />
                                            )}
                                        </div>
                                        <p className="line-clamp-2 text-xs text-muted-foreground">
                                            {notification.data.message ?? ''}
                                        </p>
                                        <p className="text-[11px] text-muted-foreground">
                                            {new Date(notification.created_at).toLocaleString()}
                                        </p>
                                    </div>
                                </DropdownMenuItem>
                            ))
                        )}
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </header>
    );
}

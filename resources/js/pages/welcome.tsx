import { Head, Link, usePage } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';
import { dashboard, login, register } from '@/routes';

export default function Welcome({
    canRegister = true,
}: {
    canRegister?: boolean;
}) {
    const { auth } = usePage().props;

    const highlights = [
        {
            title: 'Workspace-based access',
            description: 'Manage teams with owner/admin/member roles and secure invitation flows.',
        },
        {
            title: 'Projects, boards, and timeline',
            description: 'Track work in list, calendar, and Kanban views with drag-and-drop movement.',
        },
        {
            title: 'Analytics and activity visibility',
            description: 'Stay on top of throughput, overdue items, and team momentum from one dashboard.',
        },
    ];

    return (
        <>
            <Head title="Welcome" />

            <div className="relative min-h-screen overflow-hidden bg-background text-foreground">
                <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_20%_20%,hsl(var(--primary)/0.13),transparent_40%),radial-gradient(circle_at_85%_10%,hsl(var(--accent)/0.11),transparent_35%),radial-gradient(circle_at_40%_90%,hsl(var(--chart-2)/0.12),transparent_45%)]" />

                <header className="relative z-10 mx-auto w-full max-w-6xl px-6 pt-8 lg:px-10">
                    <nav className="flex items-center justify-between gap-4 rounded-2xl border border-border/70 bg-card/70 px-4 py-3 backdrop-blur lg:px-6">
                        <div className="flex items-center gap-3">
                            <div className="rounded-lg border border-border/60 bg-background p-2">
                                <AppLogoIcon className="size-5 text-primary" />
                            </div>
                            <div>
                                <p className="text-sm font-semibold tracking-wide">TaskFlow</p>
                                <p className="text-xs text-muted-foreground">Team task management</p>
                            </div>
                        </div>

                        <div className="flex items-center gap-2">
                            {auth.user ? (
                                <Link
                                    href={dashboard()}
                                    className="inline-flex h-9 items-center rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground transition hover:opacity-90"
                                >
                                    Open dashboard
                                </Link>
                            ) : (
                                <>
                                    <Link
                                        href={login()}
                                        className="inline-flex h-9 items-center rounded-md border border-border px-4 text-sm font-medium transition hover:bg-muted"
                                    >
                                        Log in
                                    </Link>
                                    {canRegister && (
                                        <Link
                                            href={register()}
                                            className="inline-flex h-9 items-center rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground transition hover:opacity-90"
                                        >
                                            Register
                                        </Link>
                                    )}
                                </>
                            )}
                        </div>
                    </nav>
                </header>

                <main className="relative z-10 mx-auto flex w-full max-w-6xl flex-col gap-8 px-6 pb-16 pt-10 lg:px-10 lg:pt-14">
                    <section className="grid gap-6 lg:grid-cols-[1.2fr_0.8fr]">
                        <article className="rounded-3xl border border-border/70 bg-card/80 p-8 shadow-sm backdrop-blur lg:p-10">
                            <p className="inline-flex items-center rounded-full border border-border/70 bg-background px-3 py-1 text-xs font-medium text-muted-foreground">
                                Multi-tenant task workspace
                            </p>

                            <h1 className="mt-5 text-3xl font-semibold leading-tight tracking-tight lg:text-5xl">
                                Plan, assign, and deliver work with clarity.
                            </h1>

                            <p className="mt-4 max-w-xl text-sm leading-6 text-muted-foreground lg:text-base">
                                Organize projects by workspace, run your team with boards and calendars,
                                and keep momentum with real-time activity and analytics.
                            </p>

                            <div className="mt-7 flex flex-wrap items-center gap-3">
                                {auth.user ? (
                                    <Link
                                        href={dashboard()}
                                        className="inline-flex h-10 items-center rounded-md bg-primary px-5 text-sm font-medium text-primary-foreground transition hover:opacity-90"
                                    >
                                        Continue to dashboard
                                    </Link>
                                ) : (
                                    <>
                                        <Link
                                            href={login()}
                                            className="inline-flex h-10 items-center rounded-md bg-primary px-5 text-sm font-medium text-primary-foreground transition hover:opacity-90"
                                        >
                                            Get started
                                        </Link>
                                        {canRegister && (
                                            <Link
                                                href={register()}
                                                className="inline-flex h-10 items-center rounded-md border border-border px-5 text-sm font-medium transition hover:bg-muted"
                                            >
                                                Create account
                                            </Link>
                                        )}
                                    </>
                                )}
                            </div>
                        </article>

                        <aside className="grid gap-3 rounded-3xl border border-border/70 bg-card/80 p-6 shadow-sm backdrop-blur">
                            <p className="text-sm font-semibold">At a glance</p>
                            <div className="grid grid-cols-2 gap-3">
                                <div className="rounded-xl border border-border/60 bg-background p-3">
                                    <p className="text-xs text-muted-foreground">Views</p>
                                    <p className="mt-1 text-lg font-semibold">Board / List / Calendar</p>
                                </div>
                                <div className="rounded-xl border border-border/60 bg-background p-3">
                                    <p className="text-xs text-muted-foreground">Roles</p>
                                    <p className="mt-1 text-lg font-semibold">Owner / Admin / Member</p>
                                </div>
                                <div className="rounded-xl border border-border/60 bg-background p-3">
                                    <p className="text-xs text-muted-foreground">Activity</p>
                                    <p className="mt-1 text-lg font-semibold">Audit Timeline</p>
                                </div>
                                <div className="rounded-xl border border-border/60 bg-background p-3">
                                    <p className="text-xs text-muted-foreground">Notifications</p>
                                    <p className="mt-1 text-lg font-semibold">In-app + Invites</p>
                                </div>
                            </div>
                        </aside>
                    </section>

                    <section className="grid gap-4 lg:grid-cols-3">
                        {highlights.map((item) => (
                            <article
                                key={item.title}
                                className="rounded-2xl border border-border/70 bg-card/70 p-5 shadow-sm backdrop-blur"
                            >
                                <h2 className="text-base font-semibold tracking-tight">{item.title}</h2>
                                <p className="mt-2 text-sm text-muted-foreground">{item.description}</p>
                            </article>
                        ))}
                    </section>

                    <section className="rounded-3xl border border-border/70 bg-card/80 p-5 shadow-sm backdrop-blur lg:p-6">
                        <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <p className="text-sm font-semibold">Live product preview</p>
                                <p className="text-xs text-muted-foreground">A quick look at your workspace dashboard experience.</p>
                            </div>
                            <span className="rounded-full border border-border/70 bg-background px-3 py-1 text-xs text-muted-foreground">
                                Analytics + Activity
                            </span>
                        </div>

                        <div className="grid gap-4 lg:grid-cols-[1.1fr_0.9fr]">
                            <div className="rounded-2xl border border-border/60 bg-background p-4">
                                <div className="grid gap-3 sm:grid-cols-3">
                                    <div className="rounded-lg border border-border/60 bg-card p-3">
                                        <p className="text-[11px] uppercase tracking-wide text-muted-foreground">Open Tasks</p>
                                        <p className="mt-1 text-xl font-semibold">28</p>
                                    </div>
                                    <div className="rounded-lg border border-border/60 bg-card p-3">
                                        <p className="text-[11px] uppercase tracking-wide text-muted-foreground">Done This Week</p>
                                        <p className="mt-1 text-xl font-semibold text-emerald-600 dark:text-emerald-400">43</p>
                                    </div>
                                    <div className="rounded-lg border border-border/60 bg-card p-3">
                                        <p className="text-[11px] uppercase tracking-wide text-muted-foreground">Overdue</p>
                                        <p className="mt-1 text-xl font-semibold text-amber-600 dark:text-amber-400">6</p>
                                    </div>
                                </div>

                                <div className="mt-4 rounded-xl border border-border/60 bg-card p-3">
                                    <div className="mb-2 flex items-center justify-between">
                                        <p className="text-xs font-medium text-muted-foreground">Weekly Throughput</p>
                                        <p className="text-xs text-muted-foreground">+18%</p>
                                    </div>
                                    <div className="flex h-24 items-end gap-2">
                                        {[38, 52, 44, 63, 59, 72, 68].map((value, index) => (
                                            <div key={index} className="flex flex-1 flex-col items-center gap-1">
                                                <div
                                                    className="w-full rounded-t bg-primary/80"
                                                    style={{ height: `${value}%` }}
                                                />
                                                <span className="text-[10px] text-muted-foreground">{index + 1}</span>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            </div>

                            <div className="grid gap-3 rounded-2xl border border-border/60 bg-background p-4">
                                <div className="rounded-lg border border-border/60 bg-card p-3">
                                    <p className="text-xs font-medium">Recent Activity</p>
                                    <ul className="mt-2 space-y-2 text-xs text-muted-foreground">
                                        <li>Anna moved “API Testing” to Done</li>
                                        <li>Michael commented on “Billing Sync”</li>
                                        <li>Workspace invite sent to jamie@team.com</li>
                                    </ul>
                                </div>

                                <div className="rounded-lg border border-border/60 bg-card p-3">
                                    <p className="text-xs font-medium">My Work</p>
                                    <div className="mt-2 space-y-2 text-xs">
                                        <div className="flex items-center justify-between rounded border border-border/60 px-2 py-1.5">
                                            <span>Finalize sprint notes</span>
                                            <span className="text-muted-foreground">Today</span>
                                        </div>
                                        <div className="flex items-center justify-between rounded border border-border/60 px-2 py-1.5">
                                            <span>Refactor alerts UI</span>
                                            <span className="text-muted-foreground">Tomorrow</span>
                                        </div>
                                        <div className="flex items-center justify-between rounded border border-border/60 px-2 py-1.5">
                                            <span>Review onboarding flow</span>
                                            <span className="text-muted-foreground">Fri</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>
                </main>
            </div>
        </>
    );
}

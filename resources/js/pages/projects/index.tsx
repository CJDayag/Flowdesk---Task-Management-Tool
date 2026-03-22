import { Head, router, useForm } from '@inertiajs/react';
import { FormEvent, useEffect, useLayoutEffect, useMemo, useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, Project, ProjectColumn, ProjectMember, Task } from '@/types';

type ViewMode = 'board' | 'list' | 'calendar';

type FilterState = {
    q: string;
    status: '' | 'todo' | 'in_progress' | 'done';
    assignee_id: string;
    priority: '' | 'low' | 'medium' | 'high';
    due_date: string;
};

type SearchResults = {
    tasks: Array<{
        id: number;
        title: string;
        status: 'todo' | 'in_progress' | 'done';
        priority?: 'low' | 'medium' | 'high' | null;
        due_at?: string | null;
        assignee?: { id: number; name: string } | null;
        project?: { id: number; name: string } | null;
    }>;
    projects: Array<{
        id: number;
        name: string;
        visibility: 'public' | 'private';
    }>;
    users: ProjectMember[];
};

const areFiltersEqual = (left: FilterState, right: FilterState) => (
    left.q === right.q
    && left.status === right.status
    && left.assignee_id === right.assignee_id
    && left.priority === right.priority
    && left.due_date === right.due_date
);

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Projects',
        href: '/projects',
    },
];

export default function ProjectsPage({
    projects,
    columns,
    tasks,
    selectedProjectId,
    workspaceMembers,
    view,
    filters,
    searchResults,
}: {
    projects: Project[];
    columns: ProjectColumn[];
    tasks: Task[];
    selectedProjectId?: number;
    workspaceMembers: ProjectMember[];
    view: ViewMode;
    filters: {
        q?: string;
        status?: '' | 'todo' | 'in_progress' | 'done';
        assignee_id?: number | null;
        priority?: '' | 'low' | 'medium' | 'high';
        due_date?: string;
    };
    searchResults: SearchResults;
}) {
    const [activeView, setActiveView] = useState<ViewMode>(view ?? 'board');
    const [draggedTaskId, setDraggedTaskId] = useState<number | null>(null);
    const [draggedColumnId, setDraggedColumnId] = useState<number | null>(null);
    const [activeFilters, setActiveFilters] = useState<FilterState>({
        q: filters.q ?? '',
        status: filters.status ?? '',
        assignee_id: filters.assignee_id ? String(filters.assignee_id) : '',
        priority: filters.priority ?? '',
        due_date: filters.due_date ?? '',
    });

    const selectedProject = useMemo(
        () => projects.find((project) => project.id === selectedProjectId) ?? projects[0],
        [projects, selectedProjectId],
    );

    useLayoutEffect(() => {
        const nextView = view ?? 'board';

        if (nextView !== activeView) {
            setActiveView(nextView);
        }
    }, [activeView, view]);

    useLayoutEffect(() => {
        const nextFilters: FilterState = {
            q: filters.q ?? '',
            status: filters.status ?? '',
            assignee_id: filters.assignee_id ? String(filters.assignee_id) : '',
            priority: filters.priority ?? '',
            due_date: filters.due_date ?? '',
        };

        setActiveFilters((current) => (areFiltersEqual(current, nextFilters) ? current : nextFilters));
    }, [filters.q, filters.status, filters.assignee_id, filters.priority, filters.due_date]);

    const createProjectForm = useForm({
        name: '',
        description: '',
        visibility: 'public' as 'public' | 'private',
    });

    const createTaskForm = useForm({
        project_id: selectedProject?.id ?? null,
        project_column_id: null as number | null,
        title: '',
        description: '',
        status: 'todo' as 'todo' | 'in_progress' | 'done',
        priority: 'medium' as 'low' | 'medium' | 'high',
        due_at: '',
    });

    const createColumnForm = useForm({
        name: '',
        color: '#94a3b8',
    });

    const projectMembersForm = useForm({
        member_ids: [] as number[],
    });

    useEffect(() => {
        projectMembersForm.setData(
            'member_ids',
            selectedProject?.members?.map((member) => member.id) ?? [],
        );
    }, [projectMembersForm, selectedProject?.members]);

    const orderedColumns = useMemo(
        () => [...columns].sort((a, b) => a.sort_order - b.sort_order),
        [columns],
    );

    const tasksByColumn = useMemo(() => {
        const map = new Map<number, Task[]>();

        for (const column of orderedColumns) {
            map.set(column.id, []);
        }

        for (const task of tasks) {
            if (task.project_column_id && map.has(task.project_column_id)) {
                map.get(task.project_column_id)?.push(task);
            }
        }

        for (const columnTasks of map.values()) {
            columnTasks.sort((a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0));
        }

        return map;
    }, [orderedColumns, tasks]);

    const visitProject = (
        projectId?: number,
        nextView: ViewMode = activeView,
        nextFilters: FilterState = activeFilters,
    ) => {
        const params: Record<string, string | number> = {
            view: nextView,
        };

        if (typeof projectId === 'number') {
            params.project_id = projectId;
        }

        if (nextFilters.q.trim()) {
            params.q = nextFilters.q.trim();
        }

        if (nextFilters.status) {
            params.status = nextFilters.status;
        }

        if (nextFilters.assignee_id) {
            params.assignee_id = Number(nextFilters.assignee_id);
        }

        if (nextFilters.priority) {
            params.priority = nextFilters.priority;
        }

        if (nextFilters.due_date) {
            params.due_date = nextFilters.due_date;
        }

        router.get(
            '/projects',
            params,
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const applyFilters = () => {
        visitProject(selectedProject?.id, activeView, activeFilters);
    };

    const clearFilters = () => {
        const reset: FilterState = {
            q: '',
            status: '',
            assignee_id: '',
            priority: '',
            due_date: '',
        };

        setActiveFilters(reset);
        visitProject(selectedProject?.id, activeView, reset);
    };

    const submitProject = (event: FormEvent) => {
        event.preventDefault();

        createProjectForm.post('/projects', {
            preserveScroll: true,
            onSuccess: () => createProjectForm.reset(),
        });
    };

    const submitTask = (event: FormEvent) => {
        event.preventDefault();

        if (! selectedProject) {
            return;
        }

        const defaultColumnId = orderedColumns[0]?.id ?? null;

        createTaskForm.transform((data) => ({
            ...data,
            project_id: selectedProject.id,
            project_column_id: data.project_column_id ?? defaultColumnId,
            due_at: data.due_at || null,
        }));

        createTaskForm.post(`/workspaces/${selectedProject.workspace_id}/tasks`, {
            preserveScroll: true,
            onSuccess: () => createTaskForm.reset('title', 'description', 'due_at'),
        });
    };

    const toggleMember = (memberId: number) => {
        const current = projectMembersForm.data.member_ids;

        if (current.includes(memberId)) {
            projectMembersForm.setData('member_ids', current.filter((id) => id !== memberId));

            return;
        }

        projectMembersForm.setData('member_ids', [...current, memberId]);
    };

    const submitMembers = (event: FormEvent) => {
        event.preventDefault();

        if (! selectedProject) {
            return;
        }

        projectMembersForm.put(`/projects/${selectedProject.id}/members`, {
            preserveScroll: true,
        });
    };

    const submitColumn = (event: FormEvent) => {
        event.preventDefault();

        if (! selectedProject) {
            return;
        }

        createColumnForm.post(`/projects/${selectedProject.id}/columns`, {
            preserveScroll: true,
            onSuccess: () => createColumnForm.reset('name'),
        });
    };

    const handleColumnDrop = (targetColumnId: number) => {
        if (! selectedProject || ! draggedColumnId || draggedColumnId === targetColumnId) {
            setDraggedColumnId(null);
            return;
        }

        const ids = orderedColumns.map((column) => column.id);
        const fromIndex = ids.indexOf(draggedColumnId);
        const toIndex = ids.indexOf(targetColumnId);

        if (fromIndex < 0 || toIndex < 0) {
            setDraggedColumnId(null);
            return;
        }

        const next = [...ids];
        const [moved] = next.splice(fromIndex, 1);
        next.splice(toIndex, 0, moved);

        router.patch(`/projects/${selectedProject.id}/columns/reorder`, {
            ordered_column_ids: next,
        }, {
            preserveScroll: true,
        });

        setDraggedColumnId(null);
    };

    const buildOrderedTaskIds = (targetColumnId: number, targetTaskId?: number): number[] => {
        if (! draggedTaskId) {
            return [];
        }

        const targetTasks = [...(tasksByColumn.get(targetColumnId) ?? [])]
            .filter((task) => task.id !== draggedTaskId)
            .map((task) => task.id);

        const insertIndex = typeof targetTaskId === 'number'
            ? targetTasks.indexOf(targetTaskId)
            : -1;

        if (insertIndex >= 0) {
            targetTasks.splice(insertIndex, 0, draggedTaskId);
        } else {
            targetTasks.push(draggedTaskId);
        }

        return targetTasks;
    };

    const moveTask = (targetColumnId: number, targetTaskId?: number) => {
        if (! draggedTaskId) {
            return;
        }

        const orderedTaskIds = buildOrderedTaskIds(targetColumnId, targetTaskId);

        if (orderedTaskIds.length === 0) {
            return;
        }

        router.patch(`/tasks/${draggedTaskId}/move`, {
            project_column_id: targetColumnId,
            ordered_task_ids: orderedTaskIds,
        }, {
            preserveScroll: true,
        });

        setDraggedTaskId(null);
    };

    const handleTaskDrop = (targetColumnId: number, targetTaskId?: number) => {
        if (! draggedTaskId) {
            return;
        }

        moveTask(targetColumnId, targetTaskId);
    };

    const assignTaskToMember = (taskId: number, assigneeId: string) => {
        if (! assigneeId) {
            return;
        }

        router.post(`/tasks/${taskId}/assign`, {
            assignee_ids: [Number(assigneeId)],
        }, {
            preserveScroll: true,
            preserveState: true,
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Projects" />

            <div className="space-y-6 p-4">
                <div className="grid gap-4 lg:grid-cols-[320px_1fr]">
                    <aside className="space-y-4 rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                        <Heading
                            variant="small"
                            title="Create Project"
                            description="Public projects are visible to all workspace members; private projects only to assigned members."
                        />

                        <form onSubmit={submitProject} className="space-y-3">
                            <div className="grid gap-1">
                                <Label htmlFor="project-name">Name</Label>
                                <Input
                                    id="project-name"
                                    value={createProjectForm.data.name}
                                    onChange={(event) => createProjectForm.setData('name', event.target.value)}
                                />
                                <InputError message={createProjectForm.errors.name} />
                            </div>

                            <div className="grid gap-1">
                                <Label htmlFor="project-description">Description</Label>
                                <textarea
                                    id="project-description"
                                    className="min-h-20 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs"
                                    value={createProjectForm.data.description}
                                    onChange={(event) => createProjectForm.setData('description', event.target.value)}
                                />
                            </div>

                            <div className="grid gap-1">
                                <Label htmlFor="project-visibility">Visibility</Label>
                                <select
                                    id="project-visibility"
                                    className="h-10 rounded-md border border-input bg-background px-3 text-sm"
                                    value={createProjectForm.data.visibility}
                                    onChange={(event) => createProjectForm.setData('visibility', event.target.value as 'public' | 'private')}
                                >
                                    <option value="public">Public</option>
                                    <option value="private">Private</option>
                                </select>
                            </div>

                            <Button disabled={createProjectForm.processing} className="w-full">Create project</Button>
                        </form>

                        <div className="space-y-2">
                            <Heading variant="small" title="Projects" />
                            {projects.map((project) => (
                                <button
                                    type="button"
                                    key={project.id}
                                    onClick={() => visitProject(project.id)}
                                    className={`w-full rounded-lg border p-3 text-left text-sm transition ${selectedProject?.id === project.id ? 'border-foreground/60 bg-muted/40' : 'border-border/70 hover:bg-muted/20'}`}
                                >
                                    <p className="font-medium">{project.name}</p>
                                    <p className="text-xs text-muted-foreground">{project.visibility} • {project.members?.length ?? 0} members</p>
                                </button>
                            ))}
                        </div>
                    </aside>

                    <section className="space-y-4">
                        <div className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                            <div>
                                <h2 className="text-lg font-semibold">{selectedProject?.name ?? 'No project selected'}</h2>
                                <p className="text-sm text-muted-foreground">{selectedProject?.description || 'Select a project to manage tasks and members.'}</p>
                            </div>
                            <div className="flex items-center gap-2">
                                {(['board', 'list', 'calendar'] as ViewMode[]).map((mode) => (
                                    <Button
                                        key={mode}
                                        type="button"
                                        variant={activeView === mode ? 'default' : 'outline'}
                                        onClick={() => {
                                            setActiveView(mode);
                                            visitProject(selectedProject?.id, mode);
                                        }}
                                    >
                                        {mode[0].toUpperCase() + mode.slice(1)}
                                    </Button>
                                ))}
                            </div>
                        </div>

                        <div className="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                            <Heading
                                variant="small"
                                title="Global Search & Filters"
                                description="Search tasks, users, and projects. Filter tasks by status, assignee, priority, and due date."
                            />

                            <div className="mt-3 grid gap-3 md:grid-cols-5">
                                <div className="grid gap-1 md:col-span-2">
                                    <Label htmlFor="search-query">Search</Label>
                                    <Input
                                        id="search-query"
                                        placeholder="Try task title, user, or project"
                                        value={activeFilters.q}
                                        onChange={(event) => setActiveFilters((current) => ({
                                            ...current,
                                            q: event.target.value,
                                        }))}
                                    />
                                </div>

                                <div className="grid gap-1">
                                    <Label htmlFor="filter-status">Status</Label>
                                    <select
                                        id="filter-status"
                                        className="h-10 rounded-md border border-input bg-background px-3 text-sm"
                                        value={activeFilters.status}
                                        onChange={(event) => setActiveFilters((current) => ({
                                            ...current,
                                            status: event.target.value as FilterState['status'],
                                        }))}
                                    >
                                        <option value="">All</option>
                                        <option value="todo">To do</option>
                                        <option value="in_progress">In progress</option>
                                        <option value="done">Done</option>
                                    </select>
                                </div>

                                <div className="grid gap-1">
                                    <Label htmlFor="filter-assignee">Assignee</Label>
                                    <select
                                        id="filter-assignee"
                                        className="h-10 rounded-md border border-input bg-background px-3 text-sm"
                                        value={activeFilters.assignee_id}
                                        onChange={(event) => setActiveFilters((current) => ({
                                            ...current,
                                            assignee_id: event.target.value,
                                        }))}
                                    >
                                        <option value="">All</option>
                                        {workspaceMembers.map((member) => (
                                            <option key={member.id} value={member.id}>{member.name}</option>
                                        ))}
                                    </select>
                                </div>

                                <div className="grid gap-1">
                                    <Label htmlFor="filter-priority">Priority</Label>
                                    <select
                                        id="filter-priority"
                                        className="h-10 rounded-md border border-input bg-background px-3 text-sm"
                                        value={activeFilters.priority}
                                        onChange={(event) => setActiveFilters((current) => ({
                                            ...current,
                                            priority: event.target.value as FilterState['priority'],
                                        }))}
                                    >
                                        <option value="">All</option>
                                        <option value="low">Low</option>
                                        <option value="medium">Medium</option>
                                        <option value="high">High</option>
                                    </select>
                                </div>
                            </div>

                            <div className="mt-3 grid gap-3 md:grid-cols-[220px_auto_auto] md:items-end">
                                <div className="grid gap-1">
                                    <Label htmlFor="filter-due-date">Due date</Label>
                                    <Input
                                        id="filter-due-date"
                                        type="date"
                                        value={activeFilters.due_date}
                                        onChange={(event) => setActiveFilters((current) => ({
                                            ...current,
                                            due_date: event.target.value,
                                        }))}
                                    />
                                </div>
                                <Button type="button" onClick={applyFilters}>Apply filters</Button>
                                <Button type="button" variant="outline" onClick={clearFilters}>Clear</Button>
                            </div>
                        </div>

                        {activeFilters.q.trim() && (
                            <div className="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                                <Heading variant="small" title="Global Search Results" />
                                <div className="mt-3 grid gap-4 md:grid-cols-3">
                                    <div>
                                        <h3 className="mb-2 text-sm font-semibold">Tasks</h3>
                                        <div className="space-y-2">
                                            {searchResults.tasks.map((task) => (
                                                <article key={task.id} className="rounded-md border border-border/70 p-2 text-xs">
                                                    <p className="text-sm font-medium">{task.title}</p>
                                                    <p className="text-muted-foreground">
                                                        {task.project?.name ?? 'No project'} • {task.assignee?.name ?? 'Unassigned'}
                                                    </p>
                                                </article>
                                            ))}
                                            {searchResults.tasks.length === 0 && (
                                                <p className="text-xs text-muted-foreground">No matching tasks.</p>
                                            )}
                                        </div>
                                    </div>

                                    <div>
                                        <h3 className="mb-2 text-sm font-semibold">Projects</h3>
                                        <div className="space-y-2">
                                            {searchResults.projects.map((project) => (
                                                <button
                                                    type="button"
                                                    key={project.id}
                                                    className="w-full rounded-md border border-border/70 p-2 text-left text-xs hover:bg-muted/20"
                                                    onClick={() => visitProject(project.id)}
                                                >
                                                    <p className="text-sm font-medium">{project.name}</p>
                                                    <p className="text-muted-foreground">{project.visibility}</p>
                                                </button>
                                            ))}
                                            {searchResults.projects.length === 0 && (
                                                <p className="text-xs text-muted-foreground">No matching projects.</p>
                                            )}
                                        </div>
                                    </div>

                                    <div>
                                        <h3 className="mb-2 text-sm font-semibold">Users</h3>
                                        <div className="space-y-2">
                                            {searchResults.users.map((user) => (
                                                <article key={user.id} className="rounded-md border border-border/70 p-2 text-xs">
                                                    <p className="text-sm font-medium">{user.name}</p>
                                                    <p className="text-muted-foreground">{user.email}</p>
                                                </article>
                                            ))}
                                            {searchResults.users.length === 0 && (
                                                <p className="text-xs text-muted-foreground">No matching users.</p>
                                            )}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        )}

                        {selectedProject && (
                            <div className="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                                <Heading variant="small" title="Quick Add Task" description="Create tasks directly in the selected project." />
                                <form onSubmit={submitTask} className="mt-3 grid gap-3 md:grid-cols-2">
                                    <div className="grid gap-1">
                                        <Label htmlFor="task-title">Title</Label>
                                        <Input
                                            id="task-title"
                                            value={createTaskForm.data.title}
                                            onChange={(event) => createTaskForm.setData('title', event.target.value)}
                                        />
                                        <InputError message={createTaskForm.errors.title} />
                                    </div>
                                    <div className="grid gap-1">
                                        <Label htmlFor="task-status">Status</Label>
                                        <select
                                            id="task-status"
                                            className="h-10 rounded-md border border-input bg-background px-3 text-sm"
                                            value={createTaskForm.data.status}
                                            onChange={(event) => createTaskForm.setData('status', event.target.value as 'todo' | 'in_progress' | 'done')}
                                        >
                                            <option value="todo">To do</option>
                                            <option value="in_progress">In progress</option>
                                            <option value="done">Done</option>
                                        </select>
                                    </div>
                                    <div className="grid gap-1 md:col-span-2">
                                        <Label htmlFor="task-description">Description</Label>
                                        <textarea
                                            id="task-description"
                                            className="min-h-20 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs"
                                            value={createTaskForm.data.description}
                                            onChange={(event) => createTaskForm.setData('description', event.target.value)}
                                        />
                                    </div>
                                    <div className="grid gap-1">
                                        <Label htmlFor="task-due-at">Due date</Label>
                                        <Input
                                            id="task-due-at"
                                            type="date"
                                            value={createTaskForm.data.due_at}
                                            onChange={(event) => createTaskForm.setData('due_at', event.target.value)}
                                        />
                                    </div>
                                    <div className="flex items-end">
                                        <Button disabled={createTaskForm.processing}>Create task</Button>
                                    </div>
                                </form>
                            </div>
                        )}

                        {activeView === 'board' && (
                            <div className="space-y-4">
                                {selectedProject && (
                                    <div className="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                                        <Heading variant="small" title="Add column" description="Create custom columns per project." />
                                        <form onSubmit={submitColumn} className="mt-3 grid gap-3 md:grid-cols-[1fr_140px_auto]">
                                            <div className="grid gap-1">
                                                <Label htmlFor="column-name">Column name</Label>
                                                <Input
                                                    id="column-name"
                                                    value={createColumnForm.data.name}
                                                    onChange={(event) => createColumnForm.setData('name', event.target.value)}
                                                />
                                                <InputError message={createColumnForm.errors.name} />
                                            </div>
                                            <div className="grid gap-1">
                                                <Label htmlFor="column-color">Color</Label>
                                                <Input
                                                    id="column-color"
                                                    type="color"
                                                    value={createColumnForm.data.color}
                                                    onChange={(event) => createColumnForm.setData('color', event.target.value)}
                                                />
                                            </div>
                                            <div className="flex items-end">
                                                <Button disabled={createColumnForm.processing}>Add column</Button>
                                            </div>
                                        </form>
                                    </div>
                                )}

                                <div className="grid gap-4 md:auto-cols-fr md:grid-flow-col">
                                    {orderedColumns.map((column) => {
                                        const columnTasks = tasksByColumn.get(column.id) ?? [];

                                        return (
                                            <div
                                                key={column.id}
                                                className="rounded-xl border border-sidebar-border/70 p-3 dark:border-sidebar-border"
                                                onDragOver={(event) => event.preventDefault()}
                                                onDrop={() => handleTaskDrop(column.id)}
                                            >
                                                <div
                                                    className="mb-2 flex cursor-move items-center justify-between gap-2"
                                                    draggable
                                                    onDragStart={() => setDraggedColumnId(column.id)}
                                                    onDragOver={(event) => event.preventDefault()}
                                                    onDrop={() => handleColumnDrop(column.id)}
                                                >
                                                    <h3 className="text-sm font-semibold" style={{ color: column.color }}>
                                                        {column.name}
                                                    </h3>
                                                    <span className="text-xs text-muted-foreground">{columnTasks.length}</span>
                                                </div>

                                                <div className="space-y-2">
                                                    {columnTasks.map((task) => (
                                                        <div
                                                            key={task.id}
                                                            className="rounded-lg border border-border/70 bg-card p-3"
                                                            draggable
                                                            onDragStart={() => {
                                                                setDraggedTaskId(task.id);
                                                            }}
                                                            onDragOver={(event) => event.preventDefault()}
                                                            onDrop={(event) => {
                                                                event.stopPropagation();
                                                                handleTaskDrop(column.id, task.id);
                                                            }}
                                                        >
                                                            <p className="text-sm font-medium">{task.title}</p>
                                                            <p className="text-xs text-muted-foreground">{task.assignee?.name ?? 'Unassigned'}</p>
                                                            <div className="mt-2 grid gap-1">
                                                                <Label htmlFor={`board-task-assignee-${task.id}`} className="text-xs">Assign to</Label>
                                                                <select
                                                                    id={`board-task-assignee-${task.id}`}
                                                                    className="h-8 rounded-md border border-input bg-background px-2 text-xs"
                                                                    value={task.assigned_to ? String(task.assigned_to) : ''}
                                                                    onChange={(event) => assignTaskToMember(task.id, event.target.value)}
                                                                    onClick={(event) => event.stopPropagation()}
                                                                >
                                                                    <option value="" disabled>Unassigned</option>
                                                                    {workspaceMembers.map((member) => (
                                                                        <option key={member.id} value={member.id}>{member.name}</option>
                                                                    ))}
                                                                </select>
                                                            </div>
                                                        </div>
                                                    ))}
                                                    {columnTasks.length === 0 && <p className="text-xs text-muted-foreground">Drop tasks here</p>}
                                                </div>
                                            </div>
                                        );
                                    })}
                                </div>
                            </div>
                        )}

                        {activeView === 'list' && (
                            <div className="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                                <table className="w-full text-sm">
                                    <thead className="bg-muted/30 text-left">
                                        <tr>
                                            <th className="px-3 py-2">Task</th>
                                            <th className="px-3 py-2">Status</th>
                                            <th className="px-3 py-2">Assignee</th>
                                            <th className="px-3 py-2">Assign</th>
                                            <th className="px-3 py-2">Due</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {tasks.map((task) => (
                                            <tr key={task.id} className="border-t border-border/60">
                                                <td className="px-3 py-2">{task.title}</td>
                                                <td className="px-3 py-2">{task.status.replace('_', ' ')}</td>
                                                <td className="px-3 py-2">{task.assignee?.name ?? 'Unassigned'}</td>
                                                <td className="px-3 py-2">
                                                    <select
                                                        className="h-8 rounded-md border border-input bg-background px-2 text-xs"
                                                        value={task.assigned_to ? String(task.assigned_to) : ''}
                                                        onChange={(event) => assignTaskToMember(task.id, event.target.value)}
                                                    >
                                                        <option value="" disabled>Unassigned</option>
                                                        {workspaceMembers.map((member) => (
                                                            <option key={member.id} value={member.id}>{member.name}</option>
                                                        ))}
                                                    </select>
                                                </td>
                                                <td className="px-3 py-2">{task.due_at ? new Date(task.due_at).toLocaleDateString() : '-'}</td>
                                            </tr>
                                        ))}
                                        {tasks.length === 0 && (
                                            <tr>
                                                <td colSpan={5} className="px-3 py-6 text-center text-muted-foreground">No tasks yet.</td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        )}

                        {activeView === 'calendar' && (
                            <div className="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                                <Heading variant="small" title="Calendar view" description="Optional planning lens based on due dates." />
                                <div className="mt-3 space-y-2">
                                    {tasks.filter((task) => task.due_at).map((task) => (
                                        <div key={task.id} className="rounded-lg border border-border/70 p-3 text-sm">
                                            <p className="font-medium">{task.title}</p>
                                            <p className="text-xs text-muted-foreground">Due {new Date(task.due_at as string).toLocaleDateString()}</p>
                                        </div>
                                    ))}
                                    {tasks.filter((task) => task.due_at).length === 0 && (
                                        <p className="text-sm text-muted-foreground">No dated tasks to display yet.</p>
                                    )}
                                </div>
                            </div>
                        )}

                        {selectedProject && (
                            <div className="rounded-xl border border-sidebar-border/70 p-4 dark:border-sidebar-border">
                                <Heading variant="small" title="Project members" description="Assigned members can access private projects." />

                                <form onSubmit={submitMembers} className="mt-3 space-y-3">
                                    <div className="grid gap-2 md:grid-cols-2">
                                        {workspaceMembers.map((member) => {
                                            const checked = projectMembersForm.data.member_ids.includes(member.id);

                                            return (
                                                <label
                                                    key={member.id}
                                                    className="flex items-center gap-2 rounded-md border border-border/70 px-3 py-2 text-sm"
                                                >
                                                    <input
                                                        type="checkbox"
                                                        checked={checked}
                                                        onChange={() => toggleMember(member.id)}
                                                    />
                                                    <span>{member.name}</span>
                                                    <span className="ml-auto text-xs text-muted-foreground">{member.email}</span>
                                                </label>
                                            );
                                        })}
                                    </div>

                                    <Button type="submit" variant="outline" disabled={projectMembersForm.processing}>
                                        Save project members
                                    </Button>
                                </form>
                            </div>
                        )}
                    </section>
                </div>
            </div>
        </AppLayout>
    );
}

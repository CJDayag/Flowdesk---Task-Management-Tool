export type ProjectVisibility = 'public' | 'private';

export type ProjectMember = {
    id: number;
    name: string;
    email: string;
};

export type ProjectColumn = {
    id: number;
    project_id: number;
    name: string;
    color: string;
    sort_order: number;
    created_at: string;
    updated_at: string;
};

export type Project = {
    id: number;
    workspace_id: number;
    created_by: number;
    name: string;
    slug: string;
    description?: string | null;
    visibility: ProjectVisibility;
    members?: ProjectMember[];
    created_at: string;
    updated_at: string;
};

export type Task = {
    id: number;
    workspace_id: number;
    project_id?: number | null;
    project_column_id?: number | null;
    title: string;
    description?: string | null;
    status: 'todo' | 'in_progress' | 'done';
    priority?: 'low' | 'medium' | 'high';
    sort_order?: number;
    due_at?: string | null;
    assigned_to?: number | null;
    assignee?: { id: number; name: string } | null;
    creator?: { id: number; name: string } | null;
    created_at: string;
    updated_at: string;
};

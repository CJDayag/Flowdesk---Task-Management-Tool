# Flowdesk Task Management System

Flowdesk is a multi-tenant task management platform inspired by Jira-style workflows, built with Laravel + Inertia + React + TypeScript.

It supports workspace-based collaboration, role-based access, project boards, task lifecycle management, notifications, audit logs, and analytics dashboards.

## Table of Contents

- Overview
- Tech Stack
- Core Features
- Product Flows
- Local Development Setup
- Environment and Services
- Testing
- Key Routes
- Project Structure
- Notes for Contributors

## Overview

Flowdesk is designed for teams that need:

- Clear workspace boundaries between organizations or teams
- Flexible project planning views (board, list, calendar)
- Rich task operations (priorities, assignees, labels, comments, attachments)
- Activity traceability and actionable notifications
- Dashboard analytics for execution visibility

## Tech Stack

### Backend

- PHP 8+
- Laravel 13
- Fortify authentication
- Pest for testing
- SQLite/MySQL compatible Eloquent models and migrations

### Frontend

- Inertia.js (Laravel + React bridge)
- React + TypeScript
- Tailwind CSS
- shadcn/ui components
- Recharts (analytics charts)

## Core Features

### 1. Multi-Tenancy and Workspaces

- Workspace ownership and membership model
- Workspace switch context per user
- Workspace settings management
- Safe empty-state handling when user has no workspace

### 2. Access Control (RBAC)

- Workspace roles: owner, admin, member
- Policy-based authorization for workspace, project, and task actions
- Member role updates and member removal controls

### 3. Workspace Invitations

- Invite by email with role assignment
- Resend and revoke pending invites
- Signed invitation acceptance links
- Friendly invalid/expired invitation pages
- In-app + email invite notifications

### 4. Project Management

- Create, update, delete projects
- Public/private project visibility
- Project member assignment
- Global project search support

### 5. Kanban and Views

- Configurable project columns
- Column reorder
- Drag-and-drop task movement and reorder
- Task status sync from canonical board columns
- Board, list, and calendar views

### 6. Task Management

- CRUD task lifecycle
- Task priority, due date, status
- Multi-assignee support
- Label management
- File attachments
- Threaded comments

### 7. Notifications

- In-app database notifications
- Assignment, comment, due-soon notifications
- Mark single notification read
- Mark all notifications read
- Global toast feedback for flash success/error actions

### 8. Activity Logs and Audit Trail

- Workspace-scoped audit logs
- Human-readable activity entries
- Member action and task action tracking
- Filtered/paginated activity log page

### 9. Search and Filtering

- Global search for tasks, users, and projects
- Task filters by status, assignee, priority, due date

### 10. Dashboard and Analytics

- KPI cards (open tasks, completed this week, overdue, active projects)
- My work sections (overdue, due today, in progress)
- Upcoming deadlines
- Recent activity with pagination
- Project progress breakdown
- Analytics datasets and charts:
  - Tasks completed by day
  - Tasks completed by week
  - Tasks by status
  - User productivity
  - Activity timeline

### 11. UI and Settings

- Workspace settings page with management actions
- User appearance preference support
- Compact view preference
- Branded welcome page with product preview

## Product Flows

### New Team Setup

1. Register and verify account
2. Create workspace
3. Invite members
4. Create projects
5. Add columns/tasks and start execution

### Task Execution

1. Create tasks in project
2. Assign members and labels
3. Move tasks through board columns
4. Track progress in dashboard analytics

## Local Development Setup

## 1) Prerequisites

- PHP 8+
- Composer
- Node.js 20+
- npm
- SQLite or MySQL

## 2) Install dependencies

```bash
composer install
npm install
```

## 3) Configure environment

```bash
cp .env.example .env
php artisan key:generate
```

Update database credentials in `.env` as needed.

## 4) Migrate database

```bash
php artisan migrate
```

## 5) Run app

Use separate terminals:

```bash
php artisan serve
npm run dev
```

Optional worker for queued notifications/mail when using queue drivers:

```bash
php artisan queue:work
```

## Environment and Services

Common environment keys to verify:

- APP_URL
- DB_CONNECTION and DB_* settings
- MAIL_* settings (for invitation emails)
- QUEUE_CONNECTION

## Testing

Run all tests:

```bash
php artisan test
```

Run specific suites:

```bash
php artisan test tests/Feature/DashboardTest.php
php artisan test tests/Feature/Workspace/WorkspaceFlowTest.php
php artisan test tests/Feature/Project/KanbanBoardTest.php
```

## Key Routes

- Dashboard: `/dashboard`
- Projects: `/projects`
- Activity Logs: `/activity-logs`
- Workspace Settings: `/settings/workspace`
- Appearance Settings: `/settings/appearance`
- Invitation Acceptance: `/workspace-invitations/{invitation}`

## Project Structure

- `app/` Laravel domain logic (controllers, models, policies, requests)
- `database/migrations/` schema evolution
- `resources/js/` frontend pages, components, layouts, types
- `routes/` route definitions
- `tests/Feature/` product behavior and regression coverage

## Notes for Contributors

- Keep changes workspace-scoped for multi-tenant safety
- Add tests for behavioral changes
- Prefer policy checks for access control paths
- Keep frontend types in sync with controller payloads
- Re-run `php artisan test` and `npm run types:check` before merging

---

Flowdesk aims to provide a clean, scalable base for team execution workflows while remaining straightforward to extend for advanced project operations.

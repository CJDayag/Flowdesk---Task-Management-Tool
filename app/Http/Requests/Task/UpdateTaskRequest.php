<?php

namespace App\Http\Requests\Task;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTaskRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'project_id' => ['sometimes', 'nullable', 'integer', 'exists:projects,id'],
            'project_column_id' => ['sometimes', 'nullable', 'integer', 'exists:project_columns,id'],
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['sometimes', 'required', 'in:todo,in_progress,done'],
            'priority' => ['sometimes', 'required', 'in:low,medium,high'],
            'due_at' => ['nullable', 'date'],
            'assignee_ids' => ['sometimes', 'array'],
            'assignee_ids.*' => ['integer', 'exists:users,id'],
            'label_ids' => ['sometimes', 'array'],
            'label_ids.*' => ['integer', 'exists:task_labels,id'],
        ];
    }
}

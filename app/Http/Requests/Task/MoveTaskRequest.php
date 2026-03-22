<?php

namespace App\Http\Requests\Task;

use Illuminate\Foundation\Http\FormRequest;

class MoveTaskRequest extends FormRequest
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
            'project_column_id' => ['required', 'integer', 'exists:project_columns,id'],
            'ordered_task_ids' => ['required', 'array', 'min:1'],
            'ordered_task_ids.*' => ['integer', 'exists:tasks,id'],
        ];
    }
}

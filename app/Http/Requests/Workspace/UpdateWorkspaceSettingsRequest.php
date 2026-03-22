<?php

namespace App\Http\Requests\Workspace;

use App\Models\Workspace;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWorkspaceSettingsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $workspace = $this->route('workspace');

        if (! $workspace instanceof Workspace || ! $this->user()) {
            return false;
        }

        return $this->user()->can('update', $workspace);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'theme' => ['required', Rule::in(['system', 'light', 'dark'])],
            'logo' => ['nullable', 'image', 'max:4096'],
        ];
    }
}

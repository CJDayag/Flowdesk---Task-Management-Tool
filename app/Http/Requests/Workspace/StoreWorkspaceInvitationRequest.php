<?php

namespace App\Http\Requests\Workspace;

use App\Models\Workspace;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWorkspaceInvitationRequest extends FormRequest
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

        return $this->user()->can('manageMembers', $workspace);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', Rule::in(['admin', 'member'])],
            'expires_in_hours' => ['nullable', 'integer', 'min:1', 'max:168'],
        ];
    }
}

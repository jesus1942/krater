<?php

namespace Crater\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        $target = $this->route('user');
        if (! $target) {
            $target = new \Crater\Models\User(['company_id' => \Crater\Support\TenantContext::companyId(), 'role' => 'staff']);
        } elseif (! app(\Crater\Services\Access\TenantUsers::class)->canViewStaff($this->user(), $target)) {
            return false;
        }

        return app(\Crater\Services\Access\AccessManager::class)->canManageUser($this->user(), $target);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $rules = [
            'name' => [
                'required',
            ],
            'email' => [
                'required',
                'email',
                Rule::unique('users'),
            ],
            'phone' => [
                'nullable',
            ],
            'password' => [
                'required',
                'min:8',
            ],
        ];

        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            $rules['email'] = [
                'required',
                'email',
                Rule::unique('users')->ignore($this->route('user')->id),
            ];
            $rules['password'] = [
                'nullable',
                'min:8',
            ];
        }

        return $rules;
    }
}

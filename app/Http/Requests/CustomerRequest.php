<?php

namespace Crater\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CustomerRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        $customer = $this->route('customer');

        if (! $customer) {
            return true;
        }
        if (! app(\Crater\Services\Access\TenantUsers::class)->canViewCustomer($customer)) {
            return false;
        }
        // Una persona puede ser familiar y tener un rol de personal. El camino
        // de clientes no puede usarse para tomar esa cuenta por email/password.
        $access = app(\Crater\Services\Access\AccessManager::class);
        $changesLogin = $this->filled('password')
            || ($this->has('email') && $this->input('email') !== $customer->email)
            || ($this->has('enable_portal') && (bool) $this->input('enable_portal') !== (bool) $customer->enable_portal);

        return ! ($changesLogin && $access->hasActiveRole($customer))
            || ($access->allows($this->user(), \Crater\Enums\Permission::USER_MANAGE, \Crater\Support\TenantContext::schoolLevelId())
                && $access->canManageUser($this->user(), $customer));
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
            'addresses.*.address_street_1' => [
                'max:255',
            ],
            'addresses.*.address_street_2' => [
                'max:255',
            ],
            'email' => [
                'email',
                'nullable',
                'unique:users,email',
            ],
        ];

        if ($this->isMethod('PUT') && $this->email != null) {
            $rules = [
                'email' => [
                    'email',
                    'nullable',
                    Rule::unique('users')->ignore($this->route('customer')->id),
                ],
            ];
        };

        return $rules;
    }
}

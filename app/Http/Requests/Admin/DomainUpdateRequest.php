<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DomainUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'url' => [
                'required',
                'string',
                'between:5,255',
                Rule::unique('domains')->ignore($this->domain),
            ],
            'user_id' => 'nullable|exists:users,id',
            'server_id' => 'required|exists:servers,id',
        ];
    }
}

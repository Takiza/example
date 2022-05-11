<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ServerUpdateRequest extends FormRequest
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
            'ip' => [
                'required',
                'string',
                'between:5,36',
                Rule::unique('servers')->ignore($this->server),
            ],
            'login' => 'required|string|between:3,50',
            'password' => 'required|string|between:3,100',
            'domains_path' => 'required|string|between:3,100',
        ];
    }
}

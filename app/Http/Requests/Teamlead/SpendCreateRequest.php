<?php

namespace App\Http\Requests\Teamlead;

use Illuminate\Foundation\Http\FormRequest;

class SpendCreateRequest extends FormRequest
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
            'spend' => 'required|numeric|between:1,90000.00',
            'user_id' => 'required|exists:users,id',
            'created_at' => 'required|date_format:Y-m-d',
        ];
    }
}

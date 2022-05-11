<?php

namespace App\Http\Requests\WebMaster;

use Illuminate\Foundation\Http\FormRequest;

class SpendingCreateRequest extends FormRequest
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
            'spending_category_id' => 'required|exists:spending_categories,id',
            'user_id' => 'nullable|exists:users,id',
            'date' => 'required'
        ];
    }
}

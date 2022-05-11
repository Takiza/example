<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class CardMassCreateRequest extends FormRequest
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
            'cards' => 'required|array',
            'cards.*.number' => 'required|integer|digits:16|distinct|unique:cards,number',
            'cards.*.expiration' => 'required|string|size:4',
            'cards.*.cvv' => 'required|string|size:3',
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array
     */
    public function messages()
    {
        $messages = [];
        foreach ($this->request->get('cards') as $key => $val) {
            $messages['cards.' . $key . '.digits'] = 'The ' . ++$key . ' card must be 16 digits';
            $messages['cards.' . $key . '.unique'] = 'The ' . ++$key . ' has already been taken';
            $messages['cards.' . $key . '.distinct'] = 'The ' . ++$key . ' has a duplicate value';
        }
        return $messages;

    }
}

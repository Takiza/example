<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
class LeadUpdateRequest extends FormRequest
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
            'email' => [
                'required','email','max:250', Rule::unique('leads')->ignore($this->lead)
            ],
            'phone' => [
                'required','string','max:17',Rule::unique('leads')->ignore($this->lead),
            ],
            'full_name' => 'required|string|max:100',
            'ip' => 'required|string|max:39',
            'user_id' => 'nullable|exists:users,id',
            'country_id' => 'required',
            'landing_name_id' => 'required',
            'landing_id' => 'required',
        ];
    }

    public function withValidator($validator)
    {
        if ($validator->fails()) {
            Log::channel('error_lead')->error( $this->ip() . "\n" . $validator->messages(). "\n" . print_r($validator->getData(), true));
        }
    }
}

<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;
use App\Rules\ExistLead;
class LeadCreateRequest extends FormRequest
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
            'email' => ['nullable','email','max:250',new ExistLead('email')],
            'phone' => ['required','numeric','digits_between:8,17', new ExistLead('phone')],
            'full_name' => 'required|string|max:100',
            'ip' => 'required|string|max:39',
            'landing' => 'required|string|max:355',
            'user_id' => 'nullable|exists:users,id',
            'country' => 'required|exists:countries,ISO_2',
            'landing_name' => 'required|string|max:30',
            'source' => 'required|exists:sources,name',
            'description' => 'nullable|string'
        ];
    }

    public function withValidator($validator)
    {
        if ($validator->fails()) {
            Log::channel('error_lead')->error( $this->ip() . "\n" . $validator->messages(). "\n" . print_r($validator->getData(), true));
        }
    }
}

<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class LandingNameStatisticRequest extends FormRequest
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
            'period' => 'required_without:date_start,date_end|string|in:today,yesterday,this_week,last_week,this_month,last_month',
            'date_start' => 'required_without:period|date_format:Y-m-d',
            'date_end' => 'required_without:period|date_format:Y-m-d'
        ];
    }
}

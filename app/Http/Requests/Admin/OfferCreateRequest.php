<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class OfferCreateRequest extends FormRequest
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
            'name' => 'required|string|between:3,50|unique:offers',
            'integration_id' => 'nullable|exists:integrations,id',
            'partner_id' => 'required|exists:partners,id',
            'daily_cap' => 'required|integer|max:10000',
            'total_cap' => 'nullable|integer|max:10000',
            'is_active' => 'nullable|boolean',
            'countries_ids' => 'required|array',
            'countries_ids.*' => 'integer|exists:countries,id',
            'landing_names_ids' => 'required|array',
            'landing_names_ids.*' => 'integer|exists:landing_names,id',
            'sources_ids' => 'required|array',
            'sources_ids.*' => 'integer|exists:sources,id',
            'pay' => 'nullable|integer|max:100',
        ];
    }
}

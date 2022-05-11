<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ArchiveUploadRequest extends FormRequest
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
            'domain_id' => 'required|exists:domains,id',
            'main_folder' => 'nullable|string|between:1,50',

            'pre_landing_id' => 'nullable|exists:archives,id',
            'pre_landing_folder' => 'nullable|string|between:1,50',

            'white_id' => 'nullable|exists:archives,id',
            'white_folder' => 'nullable|string|between:1,50',

            'land_id' => 'required|exists:archives,id',
            'land_folder' => 'nullable|string|between:1,50',
        ];
    }
}

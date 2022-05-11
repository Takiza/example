<?php 

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use App\Models\Lead;

class ExistLead implements Rule
{

    protected $column;


    public function __construct($column)
    {
        $this->column = $column;
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        return Lead::where($this->column, $value)
            ->where('created_at','>',date('Y-m-d',strtotime('-1 month')))
            ->doesntExist();
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'Lead already exists';
    }
}
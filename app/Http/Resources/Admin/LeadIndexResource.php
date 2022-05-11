<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class LeadIndexResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {

        if($this->is_action){
            $action_time = $this->action_time;
        }else{
            $action_time = '';
        }

        return [
            'id' => $this->id,
            'email' => $this->email,
            'phone' => $this->phone,
            'offer' => $this->offer->name ?? null,
            'full_name' => $this->full_name,
            'country' => $this->country->ISO_2,
            'landing' => $this->landing->url ?? null,
            'landing_name' => $this->landing_name->name ?? null,
            'status' => $this->status->name,
            'is_action' => $this->is_action,
            'user' => $this->user->name ?? null,
            'date' => $this->date,
            'query_parameters' => $this->query_parameters,
            'action_time' => $action_time
        ];
    }
}

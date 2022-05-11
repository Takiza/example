<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class LeadShowResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'full_name' => $this->full_name,
            'phone' => $this->phone,
            'offer' => $this->offer_id ?? null,
            'ip' => $this->ip,
            'country' => $this->country_id,
            'landing' => $this->landing_id ?? null,
            'landing_name' => $this->landing_name_id ?? null,
            'status' => $this->status_id,
            'is_action' => $this->is_action,
            'action_time' => $this->action_time,
            'user' => $this->user_id ?? null,
            'date' => $this->date,
            'query_parameters' => $this->query_parameters??22
        ];
    }
}

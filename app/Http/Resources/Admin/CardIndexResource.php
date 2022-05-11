<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class CardIndexResource extends JsonResource
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
            'number' => $this->number,
            'user_id' => $this->web_master->id ?? null,
            'web_master' => $this->web_master->name ?? null,
            'expiration' => $this->expiration ? substr_replace($this->expiration, '/', 2, 0) : null,
            'cvv' => $this->cvv,
            'date' => $this->date,
            'status' => $this->card_status->name ?? null,
            'card_status_id' => $this->card_status_id,
        ];
    }
}

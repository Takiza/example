<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class LandingIndexResource extends JsonResource
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
            'url' => $this->url,
            'user' => $this->domain->user->name ?? '',
            'land' => $this->land->name ?? '',
            'pre_land' => $this->pre_land->name ?? '',
            'white' => $this->white->name ?? '',
        ];
    }
}

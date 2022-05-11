<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\ResourceCollection;

class SpendIndexCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'data' => $this->collection->map(function ($item) use ($request){
                return (new SpendIndexResource($item))->toArray($request);
            }),
            'current_page' => $this->currentPage(),
            'total_pages' => $this->lastPage(),
            'total' => $this->total(),
        ];
    }
}

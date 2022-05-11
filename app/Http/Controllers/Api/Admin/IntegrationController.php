<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Admin\IntegrationCreateRequest;
use App\Http\Resources\Admin\GeneralIndexCollection;
use App\Models\Integration;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;


class IntegrationController extends BaseController
{
    /**
     * Display a listing of the resource.
     *
     * @return JsonResponse
     */
    public function index()
    {
        $items = Integration::select(['id', 'name'])
            ->paginate(20);

        return $this->sendResponse(new GeneralIndexCollection($items), 'Integrations collection');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  IntegrationCreateRequest  $request
     * @return JsonResponse
     */
    public function store(IntegrationCreateRequest $request)
    {
        $data = $request->input();

        $result = Integration::create($data);

        if($result){
            return $this->sendResponse($result, 'Integration created successfully');
        } else {
            return $this->sendError('Error server.', null, 500);
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return JsonResponse
     */
    public function destroy($id)
    {
        $result = Integration::destroy($id);

        if (!$result) {
            return $this->sendError('Integration not found');
        } else {
            return $this->sendResponse($id, 'Integration removed');
        }
    }

    public function select()
    {
        $items = Integration::select(['id', 'name'])->get();

        return $this->sendResponse($items, 'Integration select');
    }
}

<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Admin\ServerCreateRequest;
use App\Http\Requests\Admin\ServerUpdateRequest;
use App\Http\Resources\Admin\GeneralIndexCollection;
use App\Models\Server;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;

class ServerController extends BaseController
{
    /**
     * Display a listing of the resource.
     *
     * @return JsonResponse
     */
    public function index()
    {
        $items = Server::select([
            'id',
            'ip',
            'login',
            'password',
            'domains_path'
        ])
            ->paginate(20);

        return $this->sendResponse(new GeneralIndexCollection($items), 'Servers collection');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  ServerCreateRequest $request
     * @return JsonResponse
     */
    public function store(ServerCreateRequest $request)
    {
        $data = $request->input();

        $token = Setting::where('name', 'git_token')->first();
        if(!$token) {
            return $this->sendError('Git token not found', null);
        }

        $result = Server::addKeys($data, $token->value);

        if(!$result) {
            return $this->sendError('Server not connect', null, 400);
        }

        $item = Server::create($data);

        if($item){
            return $this->sendResponse($item, 'Server created successfully');
        } else {
            return $this->sendError('Error server.', null, 500);
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  ServerUpdateRequest  $request
     * @param  int  $id
     * @return JsonResponse
     */
    public function update(ServerUpdateRequest $request, $id)
    {
        $item = Server::find($id);

        if (empty($item)) {
            return $this->sendError('Server not found');
        }

        $data = $request->input();

        $result = $item->update($data);

        if($result){
            return $this->sendResponse($item, 'Server updated successfully');
        } else {
            return $this->sendError('Error server.', null, 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return JsonResponse
     */
    public function destroy($id)
    {
        $result = Server::destroy($id);

        if (!$result) {
            return $this->sendError('Server not found');
        } else {
            return $this->sendResponse($id, 'Server removed');
        }
    }

    public function select()
    {
        $items = Server::select(['id', 'ip'])->get();

        return $this->sendResponse($items, 'Server select');
    }
}

<?php

namespace App\Http\Controllers\Api\Admin;

use App\Components\DomainServices;
use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Admin\DomainCreateRequest;
use App\Http\Requests\Admin\DomainUpdateRequest;
use App\Http\Resources\Admin\GeneralIndexCollection;
use App\Models\Domain;
use App\Models\Server;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class DomainController extends BaseController
{
    /**
     * Display a listing of the resource.
     *
     * @return JsonResponse
     */
    public function index(Request $request)
    {
        $items = Domain::select([
            'id',
            'url',
            'user_id',
            'server_id'
        ])
            ->with([
                'user:id,name',
                'server:id,ip',
            ])
            ->index($request)
            ->latest()
            ->paginate(20);

        return $this->sendResponse(new GeneralIndexCollection($items), 'Domains collection');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  DomainCreateRequest $request
     * @return JsonResponse
     */
    public function store(DomainCreateRequest $request)
    {
        $data = $request->input();

        $item = Domain::create($data);

        if($item){
            $server = Server::select('ip')->find($data['server_id']);

            // create domain in tiny
            DomainServices::createDomainTINY($data['url']);

            // add domain DO
            DomainServices::createDomainDO($data['url'], $server->ip);

            $item = Domain::select([
                'id',
                'url',
                'user_id',
                'server_id'
            ])
                ->with([
                    'user:id,name',
                    'server:id,ip',
                ])
                ->find($item->id);
            return $this->sendResponse($item, 'Domain created successfully');
        } else {
            return $this->sendError('Error server.', null, 500);
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  DomainUpdateRequest  $request
     * @param  int  $id
     * @return JsonResponse
     */
    public function update(DomainUpdateRequest $request, $id)
    {
        $item = Domain::find($id);

        if (empty($item)) {
            return $this->sendError('Domain not found');
        }

        $data = $request->input();

        $result = $item->update($data);

        if($result){
            return $this->sendResponse($item, 'Offer updated successfully');
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
        $result = Domain::destroy($id);

        if (!$result) {
            return $this->sendError('Domain not found');
        } else {
            return $this->sendResponse($id, 'Domain removed');
        }
    }

    public function selectByUser(Request $request)
    {
        $items = Domain::select(['id', 'url'])
            ->get();

        return $this->sendResponse($items, 'Domain by user select');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function massUpload(Request $request)
    {
        $data = $request->input();

        foreach ($data['domains'] as $domain) {

            $item = Domain::where('url', $domain)->first();

            $item->user_id = $data['user_id'];
            $item->save();
        }

        return $this->sendResponse($item, 'Successfully');

    }
}

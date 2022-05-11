<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Admin\LandingNameCreateRequest;
use App\Http\Requests\Admin\LandingNameStatisticRequest;
use App\Http\Resources\Admin\GeneralIndexCollection;
use App\Models\LandingName;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LandingNameController extends BaseController
{
    /**
     * Display a listing of the resource.
     *
     * @return JsonResponse
     */
    public function index()
    {
        $items = LandingName::select(['id', 'name'])
            ->orderBy('id', 'DESC')
            ->paginate(20);

        return $this->sendResponse(new GeneralIndexCollection($items), 'Landing names collection');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  LandingNameCreateRequest  $request
     * @return JsonResponse
     */
    public function store(LandingNameCreateRequest $request)
    {
        $data = $request->input();

        $result = LandingName::create($data);

        if($result){
            return $this->sendResponse($result, 'Landing name created successfully');
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
        $result = LandingName::destroy($id);

        if (!$result) {
            return $this->sendError('Landing name not found');
        } else {
            return $this->sendResponse($id, 'Landing name removed');
        }
    }

    public function select()
    {
        $items = LandingName::select(['id', 'name'])->get();

        return $this->sendResponse($items, 'Landing name select');
    }

    public function statistics(LandingNameStatisticRequest $request)
    {
        $data = $request->input();

        if (isset($data['period'])) {
            if ($data['period'] === 'today') {
                $dateStart = $dateEnd = date('Y-m-d');
            } else if ($data['period'] === 'yesterday') {
                $dateStart = $dateEnd = date('Y-m-d', strtotime('-1 day'));
            } else if ($data['period'] === 'this_week') {
                $dateStart = date('Y-m-d', strtotime('last Monday'));
                $dateEnd = date('Y-m-d');
            } else if ($data['period'] === 'last_week') {
                $dateEnd = date('Y-m-d', strtotime('last Saturday'));
                $dateStart = date('Y-m-d', strtotime($dateEnd, strtotime('-7 days')));
            } else if ($data['period'] === 'this_month') {
                $dateStart = date('Y-m-01');
                $dateEnd = date('Y-m-d');
            } else if ($data['period'] === 'last_month') {
                $month_ini = new \DateTime("first day of last month");
                $month_end = new \DateTime("last day of last month");

                $dateStart = $month_ini->format('Y-m-d');
                $dateEnd = $month_end->format('Y-m-d');
            }
        } else {
            $dateStart = $data['date_start'];
            $dateEnd = $data['date_end'];
        }

        $items = DB::select("select landing_names.name,
                                        count(leads.id) as count_leads,
                                        IFNULL(sum(leads.is_action = 1), 0) as count_actions
                                    from landing_names left JOIN leads
                                        on landing_names.id = leads.landing_name_id
                                    where date(leads.created_at) BETWEEN ? AND ?
                                    group by landing_names.name", [$dateStart, $dateEnd]);

        $items = json_decode(json_encode($items), true);

        foreach ($items as $key => $item){
            if($item['count_leads'] != 0){
                $items[$key]['cr'] = round($item['count_actions'] / $item['count_leads'] * 100, 2);
            } else {
                $items[$key]['cr'] = 0;
            }
        }

        return response()->json($items);
    }
}

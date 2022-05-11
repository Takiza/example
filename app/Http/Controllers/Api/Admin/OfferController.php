<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Admin\OfferCreateRequest;
use App\Http\Requests\Admin\OfferDashboardRequest;
use App\Http\Requests\Admin\OfferUpdateRequest;
use App\Http\Requests\Admin\WebMasterStatisticRequest;
use App\Http\Resources\Admin\GeneralIndexCollection;
use App\Models\Lead;
use App\Models\Offer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OfferController extends BaseController
{
    /**
     * Display a listing of the resource.
     *
     * @return JsonResponse
     */
    public function index()
    {
        $items = Offer::select([
            'id',
            'name',
            'integration_id',
            'partner_id',
            'daily_cap',
            'total_cap',
            'is_active',
            DB::raw('date(offers.created_at) as date')
        ])
            ->with([
                'integration:id,name',
                'partner:id,name',
                'countries:id,ISO_2 as name',
                'landing_names:id,name'
            ])
            ->latest()
            ->paginate(20);

        return $this->sendResponse(new GeneralIndexCollection($items), 'Offers collection');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  OfferCreateRequest $request
     * @return JsonResponse
     */
    public function store(OfferCreateRequest $request)
    {
        $data = $request->input();

        $item = Offer::create($data);

        if($item){
            $item->countries()->sync($request->countries_ids);
            $item->sources()->sync($request->sources_ids);
            $item->landing_names()->sync($request->landing_names_ids);

            $offer = Offer::select([
                'id',
                'name',
                'integration_id',
                'partner_id',
                'daily_cap',
                'total_cap',
                'is_active',
                'pay',
                DB::raw('date(offers.created_at) as date')
            ])
                ->with([
                    'integration:id,name',
                    'partner:id,name',
                ])
                ->find($item->id);

            return $this->sendResponse($offer, 'Offer created successfully');
        } else {
            return $this->sendError('Error server.', null, 500);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return JsonResponse
     */
    public function show($id)
    {
        $item = Offer::select([
                'id',
                'name',
                'integration_id',
                'partner_id',
                'daily_cap',
                'total_cap',
                'is_active',
                'pay',
            ])
            ->with([
                'countries:id,ISO_2',
                'sources:id,name',
                'landing_names:id,name'
            ])
            ->find($id);

        if (empty($item)) {
            return $this->sendError('Offer not found');
        }

        return $this->sendResponse($item, 'Offer show');
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  OfferUpdateRequest  $request
     * @param  int  $id
     * @return JsonResponse
     */
    public function update(OfferUpdateRequest $request, $id)
    {
        $item = Offer::find($id);

        if (empty($item)) {
            return $this->sendError('Offer not found');
        }

        $data = $request->input();

        $result = $item->update($data);

        if($result){
            $item->countries()->sync($request->countries_ids);
            $item->sources()->sync($request->sources_ids);
            $item->landing_names()->sync($request->landing_names_ids);

            if($item->daily_cap != 0){
                $coefficient = Lead::getCountTodayByOffer($item->id) / $item->daily_cap * 100;
                if ($coefficient >= 100) {
                    $coefficient = 100;
                    $item->is_active = 0;
                }
                $item->coefficient = $coefficient;
                $item->save();
            }

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
        $result = Offer::destroy($id);

        if (!$result) {
            return $this->sendError('Offer not found');
        } else {
            return $this->sendResponse($id, 'Offer removed');
        }
    }

    public function activity(Request $request, $id)
    {
        $item = Offer::find($id);

        if (empty($item)) {
            return $this->sendError('Offer not found');
        }

        $item->is_active = $request->activity;
        $result = $item->save();

        if($result) {
            return $this->sendResponse($result, 'Offer change status');
        } else {
            return $this->sendError('Offer not change status');
        }
    }

    public function selectForLeads(Request $request)
    {
        $request->offer_ids = null;

        $items = Offer::select(['id', 'name'])
            ->whereHas('leads', function ($query) use ($request){
                $query->index($request);
            })
            ->get();

        return $this->sendResponse($items, 'Offers select');
    }

    public function getCurrentCap()
    {
        $countLeads = DB::select("select count(leads.id) as count_leads
                                        from leads
                                        where leads.offer_id is not NULL AND
                                            date(created_at) = CURRENT_DATE");
        $countLeads = json_decode(json_encode($countLeads), true);

        $capAll = DB::select("select sum(offers.daily_cap) as sum_cap
                                    from offers;");
        $capAll = json_decode(json_encode($capAll), true);

        $capActive = DB::select("select sum(offers.daily_cap) as sum_cap
                                    from offers
                                    where offers.is_active = 1;");
        $capActive = json_decode(json_encode($capActive), true);

        $result = [
            'count_leads' => $countLeads[0]['count_leads'],
            'cap_all' => $capAll[0]['sum_cap'],
            'cap_active' => $capActive[0]['sum_cap'],
        ];

        return $this->sendResponse($result, 'Dashboard cap');
    }

    public function getDashboard(OfferDashboardRequest $request)
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

        $items =  DB::select("select offers.name,
                                        offers.daily_cap,
                                        offers.is_active,
                                        IFNULL(leads_data.count_leads, 0) as count_leads
                                    from offers left join
                                      (select offers.id as offer_id, count(leads.id) as count_leads
                                        from offers, leads
                                        where offers.id = leads.offer_id AND
                                              date(leads.created_at) BETWEEN ? AND ?
                                        group by offers.id) as leads_data
                                      ON offers.id = leads_data.offer_id
                                    order by offers.is_active DESC", [$dateStart, $dateEnd]);

        $items = json_decode(json_encode($items), true);

        foreach ($items as $key => $item){
            $items[$key]['percent'] = round(100 * $item['count_leads'] / $item['daily_cap'], 2);
        }

        return response()->json($items);
    }

    public function statistics(WebMasterStatisticRequest $request)
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

        $items = DB::select("select offers.name,
                                        count(data_leads.id) as count_leads,
                                        IFNULL(sum(data_leads.is_action = 1), 0) as count_actions
                                    from offers left JOIN
                                        (select *
                                        from leads
                                        where date(leads.created_at) BETWEEN ? AND ?) as data_leads
                                        on offers.id = data_leads.offer_id
                                    group by offers.name", [$dateStart, $dateEnd]);

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

    /**
     * Display the access offers.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function getForSelectAccess($id)
    {
        $lead = Lead::find($id);

        if($lead->user->role->name == 'affiliate'){
            $items = Offer::getOffersForSentAffiliate($lead->country_id, $lead->source_id);
        }else{
            $items = Offer::getOffersForSent($lead->landing_name_id, $lead->country_id, $lead->source_id);
        }

        return response()->json($items);
    }

    public function select()
    {
        $items = Offer::select(['id', 'name'])->get();

        return $this->sendResponse($items, 'Offers select');
    }
}

<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Admin\LeadCreateRequest;
use App\Http\Requests\Admin\LeadExportRequest;
use App\Http\Requests\Admin\LeadUpdateRequest;
use App\Http\Resources\Admin\LeadIndexCollection;
use App\Http\Resources\Admin\LeadShowResource;
use App\Http\Resources\Admin\LeadStatusesResource;
use App\Jobs\Leads\GenerateResendLeadsJob;
use App\Jobs\UpdateStatusesJob;
use App\Models\Lead;
use App\Models\LeadStatus;
use App\Models\Offer;
use Box\Spout\Writer\Common\Creator\WriterEntityFactory;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class LeadController extends BaseController
{
    /**
     * Display a listing of the resource.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request)
    {
        $rowsPerPage = $request->input('per_page') ?? 20;
        
        $items = Lead::select([
            'id',
            'email',
            'phone',
            'full_name',
            'landing_id',
            'user_id',
            'country_id',
            'status_id',
            'offer_id',
            'landing_name_id',
            'is_action',
            'action_time',
            'query_parameters',
            DB::raw('DATE_FORMAT(leads.created_at, "%Y-%m-%d %H:%i:%s") as date')
        ])
            ->with([
                'user:id,name',
                'country:id,ISO_2',
                'status:id,name',
                'offer:id,name',
                'landing_name:id,name',
                'landing:id,url',
            ])
            ->index($request)
            ->latest()
            ->paginate($rowsPerPage);

        return $this->sendResponse(new LeadIndexCollection($items), 'Leads collection');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  LeadCreateRequest  $request
     * @return JsonResponse
     */
    public function store(LeadCreateRequest $request)
    {
        $data = $request->input();

        $lead = Lead::saveLead($data);
        \Log::info('lead save');

        if($lead){
            $lead = Lead::find($lead->id);
            // send offer
            return response()->json(Offer::sendLead($lead));
        } else {
            return response()->json([
                'status' => false,
                'error' => 'Server error'
            ]);
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
        $item = Lead::select([
            'id',
            'phone',
            'email',
            'ip',
            'full_name',
            'landing_id',
            'user_id',
            'country_id',
            'status_id',
            'offer_id',
            'landing_name_id',
            'is_action',
            'query_parameters',
            'action_time',
            DB::raw('DATE_FORMAT(leads.created_at, "%Y-%m-%d %H:%i:%s") as date')
        ])
//            ->with([
//                'user:id,name',
//                'country:id,ISO_2',
//                'offer:id,name',
//                'landing_name:id,name',
//                'landing:id,url',
//            ])
            ->find($id);

        if (empty($item)) {
            return $this->sendError('Lead not found');
        }

        return $this->sendResponse(new LeadShowResource($item), 'Lead show');
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  Request  $request
     * @param  int  $id
     * @return JsonResponse
     */
    public function update(LeadUpdateRequest $request, $id)
    {
        $item = Lead::find($id);


        if (empty($item)) {
            return $this->sendError('Lead not found');
        }

        $data = $request->input();

        $result = $item->update($data);

        if($result){
            return $this->sendResponse($item, 'Lead updated successfully');
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
        $result = Lead::destroy($id);

        if (!$result) {
            return $this->sendError('Lead not found');
        } else {
            return $this->sendResponse($id, 'Lead removed');
        }
    }

    public function export(LeadExportRequest $request)
    {
        $nameFile = 'leads-' . date('Y-m-d H-i-s') . '.xlsx';
        Storage::disk('public')->put($nameFile, 'Contents');

        $storagePath = Storage::disk('public')->getDriver()->getAdapter()->getPathPrefix();

        $pathFile = $storagePath . $nameFile;

        $writer = WriterEntityFactory::createXLSXWriter();

        $writer->openToFile($pathFile);

        // set Headers
        $headers = [
            'ID',
            'Offer',
            'Full name',
            'Email',
            'Phone',
            'IP',
            'Web master',
            'Status',
            'Action',
            'Date action',
            'Landing',
            'Landing parameters',
            'Country',
            'Source',
            'Landing name',
            'Date',
            'External ID',
        ];

        $rowHeaders = WriterEntityFactory::createRowFromArray($headers);
        $writer->addRow($rowHeaders);

        $leads = Lead::getLeadForExport($request->input());

        foreach ($leads as $lead) {
            $writer->addRow(WriterEntityFactory::createRowFromArray((array)$lead));
        }

        $writer->close();

        return Storage::disk('public')->url($nameFile);
    }

    public function resend($id)
    {
        $lead = Lead::find($id);

        $result = Offer::sendLead($lead);

        if (isset($result['status']) && $result['status']) {
            $code = 200;
        } else {
            $code = 400;
        }

        return response()->json($result, $code);
    }

    public function postBack(Request $request)
    {
        $data = $request->input();

        if(isset($data['phone']) && isset($data['status'])){
            $lead = Lead::where('phone', $data['phone'])->first();

            if($lead){
                $statusLead = LeadStatus::where('name', $data['status'])->first();

                if($statusLead){
                    $lead->status_id = $statusLead->id;
                    $lead->save();
                } else {
                    $statusLead = LeadStatus::create(['name' => $data['status']]);
                }
                $lead->status_id = $statusLead->id;
                $lead->save();

                if(isset($data['payment']) && $data['payment'] == 1){
                    $lead->is_action = 1;
                    $lead->save();
                }
            }
        }
    }

    /**
     * Resend lead to offer by id
     *
     * @param $id
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function resendToOffer($id, Request $request)
    {
        $lead = Lead::find($id);

        if (!$lead) {
            return response()->json(['msg' => 'Lead not found'], 400);
        }

        if (is_null($request->offer_id)) {
            return response()->json(['msg' => 'Offer required'], 400);
        }

        $offer = Offer::find($request->offer_id);

        if (!$offer) {
            return response()->json(['msg' => 'Offer not found'], 400);
        }

        $result = Offer::sendLeadToBroker($offer, $lead);

        if (isset($result['status']) && $result['status']) {
            $result['offer_id'] = $offer->id;
            $code = 200;
        } else {
            $code = 400;
        }

        return response()->json($result, $code);
    }

    /**
     * Update statuses by period
     *
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function updateStatuses(Request $request)
    {
        if (!isset($request->start_date) && !isset($request->end_date)) {
            return response()->json('Dates is required', 400);
        }

        $data = $request->input();

        $begin = new \DateTime($data['start_date']);
        $end = new \DateTime($data['end_date']);
        $end = $end->modify('+1 day');

        $interval = new \DateInterval('P1D');
        $dateRange = new \DatePeriod($begin, $interval, $end);

        $i = 0;

        foreach ($data['offer_ids'] as $offerId) {

            foreach ($dateRange as $date) {
                $dateUpdate = $date->format("Y-m-d");

                UpdateStatusesJob::dispatch($offerId, $dateUpdate)->delay(now()->addSeconds($i));
                $i += 2;
            }
        }

        return $this->sendResponse([], 'Success');
    }

    public function resendHold(Request $request)
    {
        $data = $request->input();

        $startTime = Carbon::make($data['date_resend'] . ' ' . $data['time_resend']);
        $timeActivityFormat = Carbon::parse($startTime)->format('Y-m-d H:i:s');

        GenerateResendLeadsJob::dispatch($data, $timeActivityFormat)->onQueue('resend_leads')->delay($startTime);;

        return response()->json('Success');
    }

    public function getStatuses(Request $request)
    {
        $date = $request->input('date');

        if(!$date){
            return $this->sendError('Date not found');
        }

        $items = Lead::select([
            'id',
            'email',
            'status_id',
            'is_action',
            'action_time',
            DB::raw('DATE_FORMAT(leads.created_at, "%Y-%m-%d %H:%i:%s") as date')
        ])
            ->with(['status:id,name'])
            ->whereDate('created_at', $date)
            ->latest()
            ->get();

        return $this->sendResponse( LeadStatusesResource::collection($items), 'Leads collection');
    }
}

<?php

namespace App\Jobs\Leads;

use App\Models\Lead;
use App\Models\Offer;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class SendLeadOfHoldJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $leadRepository;

    private $offersIds;

    private $iterable;

    private $leadId;

    /**
     * Create a new job instance.
     * @param array $offersIds
     * @param int $iterable
     * @param int $leadId
     * @return void
     */
    public function __construct($offersIds, $iterable, $leadId)
    {
        $this->offersIds = $offersIds;
        $this->iterable = $iterable;
        $this->leadId = $leadId;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $lead = Lead::find($this->leadId);

        if ($lead) {
            $result = $this->setOfferAndSendFromBrokerForHold($lead);

            if (!$result) {
                $lead->status_id = 2;
                $lead->offer_id = null;
                $lead->save();
            }
        }
    }

    private function setOfferAndSendFromBrokerForHold($lead)
    {
        $offers = Offer::getOffersForSentHold($lead->landing_name_id, $lead->country_id, $lead->source_id, $this->offersIds);

        if (!empty($offers) && is_array($offers)) {
            foreach ($offers as $offer) {
                $offerObj = Offer::find($offer->id);

                $response = Offer::sendLeadToBroker($offerObj, $lead);

                if (isset($response['status']) && $response['status']) {
                    return true;
                }
            }
        }

        return false;
    }
}

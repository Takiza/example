<?php

namespace App\Models;

use App\Jobs\SendKeitaroJob;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Offer extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'integration_id',
        'partner_id',
        'daily_cap',
        'total_cap',
        'is_active',
        'pay'
    ];

    public function countries()
    {
        return $this->belongsToMany(Country::class, 'offer_country');
    }

    public function sources()
    {
        return $this->belongsToMany(Source::class, 'offer_source');
    }

    public function landing_names()
    {
        return $this->belongsToMany(LandingName::class, 'offer_landing_name', 'offer_id', 'landing_name_id');
    }

    public function leads()
    {
        return $this->hasMany(Lead::class);
    }

    public function integration()
    {
        return $this->belongsTo(Integration::class);
    }

    public function partner()
    {
        return $this->belongsTo(Partner::class);
    }

    public static function sendLead(Lead $lead)
    {   

        \Log::info($lead->user->role->name);
        if($lead->user->role->name == 'affiliate'){
            $offers = self::getOffersForSentAffiliate($lead->country_id, $lead->source_id);
            \Log::info(json_encode($offers));

        }else{
            $offers = self::getOffersForSent($lead->landing_name_id, $lead->country_id, $lead->source_id);
        }

        if(empty($offers)){

            $statusNoSend = LeadStatus::where('name', 'No send')->first();
            if($statusNoSend) {
                $lead->status_id = $statusNoSend->id;
                $lead->save();
            }

            return [
                'status' => false,
                'error' => 'Offers not found'
            ];
        }

        foreach ($offers as $offer) {

            $result = self::sendLeadToBroker($offer, $lead);

            if ((isset($result['status']) && $result['status']) || (isset($result['error']) && $result['error'] == 'TIME_OUT')) {
                return $result;
            }
        }

        return [
            'status' => false,
            'error' => 'Offer not found'
        ];
    }

    public static function sendLeadToBroker($offer, $lead)
    {
        $offer = Offer::find($offer->id);
        \Log::info('popal'.$offer);

        $result = self::sendLeadToIntegration($offer, $lead);

        if($result['status']){
            // success send lead to integration

            $result['lead_id'] = $lead->id;

            if(isset($result['link_auto_login'])){
                $lead->link_auto_login = substr($result['link_auto_login'], 0, 300);
            }

            if(isset($result['external_id'])){
                $lead->external_id = substr($result['external_id'], 0, 100);
            }

            // set offer in lead
            $lead->offer_id = $offer->id;
            $lead->status_id = 1;
            $lead->created_at = Carbon::now();

            $lead->save();

            // update coefficient in offer
            if($offer->daily_cap != 0){
                $coefficient = Lead::getCountTodayByOffer($offer->id) / $offer->daily_cap * 100;
                if ($coefficient >= 100) {
                    $coefficient = 100;
                    $offer->is_active = 0;
                }
                $offer->coefficient = $coefficient;
                $offer->save();
            }

            // check all goal offer
            $countLeads = Offer::find($offer->id)
                ->leads()
                ->count();

            if (!is_null($offer->total_cap) && $offer->total_cap <= $countLeads) {
                $offer->is_active = 0;
                $offer->save();
            }

            // send keitaro
            if(env('KEITARO_URL')){
                SendKeitaroJob::dispatch($lead->id, 'create');
            }
        } else {
            $statusNoSend = LeadStatus::where('name', 'No send')->first();
            if($statusNoSend) {
                $lead->status_id = $statusNoSend->id;
                $lead->save();
            }
        }

        return $result;
    }


    public static function getOffersForSentAffiliate($country, $source){
        return DB::select("select offers.id, offers.name
                from offers, offer_landing_name, offer_country, offer_source
                where offers.is_active = 1 AND
                    offers.integration_id is not null AND
                    offers.coefficient < 100 AND
                    offers.id = offer_country.offer_id AND
                    offer_country.country_id = ? AND
                    offers.id = offer_source.offer_id AND
                    offer_source.source_id = ?
                order by offers.coefficient ASC", [$country, $source]);
    }

    public static function getOffersForSent($landingName, $country, $source)
    {
        return DB::select("select offers.id, offers.name
                                    from offers, offer_landing_name, offer_country, offer_source
                                    where offers.is_active = 1 AND
                                        offers.integration_id is not null AND
                                        offers.id = offer_landing_name.offer_id AND
                                        offer_landing_name.landing_name_id = ? AND
                                        offers.coefficient < 100 AND
                                        offers.id = offer_country.offer_id AND
                                        offer_country.country_id = ? AND
                                        offers.id = offer_source.offer_id AND
                                        offer_source.source_id = ?
                                    order by offers.coefficient ASC", [$landingName, $country, $source]);
    }

    public static function getOffersForSentHold($landingName, $country, $source, $offerIds)
    {
        $offers = implode(',', $offerIds);

        return DB::select("select offers.id, offers.name
                                    from offers, offer_landing_name, offer_country, offer_source
                                    where offers.is_active = 1 AND
                                        offers.integration_id is not null AND
                                        offers.id = offer_landing_name.offer_id AND
                                        offer_landing_name.landing_name_id = ? AND
                                        offers.coefficient < 100 AND
                                        offers.id = offer_country.offer_id AND
                                        offer_country.country_id = ? AND
                                        offers.id = offer_source.offer_id AND
                                        offer_source.source_id = ? AND
                                        offers.id IN ($offers)
                                    order by offers.coefficient ASC", [$landingName, $country, $source]);
    }

    private static function sendLeadToIntegration($offer, $lead)
    {
        // search class integration
        $integration = Integration::find($offer->integration_id);

        if(!$integration) {
            return [
                'status' => false,
                'error' => 'Integration not found'
            ];
        }
        $className = "App\Integrations\\" . $integration->name;

        if (!class_exists($className)) {
            Log::error('Integration ' . $integration->name . ' not found');

            return [
                'status' => false,
                'error' => 'Integration ' . $integration->name . ' not found'
            ];
        }

        $integrationClass = new $className();

        $serializeData = self::setSerializeData($lead);

        return $integrationClass->sent($serializeData);
    }

    private static function setSerializeData($lead)
    {
        $firstAndLastName = explode(' ', $lead->full_name);

        $country = Country::find($lead->country_id);
        $landingName = LandingName::find($lead->landing_name_id);
        $source = Source::find($lead->source_id);

        return [
            'full_name' => $lead->full_name,
            'first_name' => $firstAndLastName[0],
            'last_name' => $firstAndLastName[1] ?? '',
            'phone' => $lead->phone,
            'email' => $lead->email ?? '',
            'ip' => $lead->ip,
            'landing_name' => $landingName->name??'',
            'country_ISO2' => $country->ISO_2,
            'country_full_name' => $country->full_name,
            'source' => $source->name,
            'description' => $lead->description,
            'landing_url' => $lead->landing->url,
            'query_parameters' => $lead->query_parameters,
        ];
    }
}

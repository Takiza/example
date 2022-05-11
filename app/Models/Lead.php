<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Lead extends Model
{
    use HasFactory;

    protected $fillable = [
        'email',
        'phone',
        'full_name',
        'ip',
        'query_parameters',
        'landing_id',
        'user_id',
        'country_id',
        'status_id',
        'landing_name_id',
        'source_id',
        'keitaro_id',
        'is_action',
        'action_time',
        'created_at',
        'offer_id',
        'description'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function status()
    {
        return $this->belongsTo(LeadStatus::class, 'status_id');
    }

    public function offer()
    {
        return $this->belongsTo(Offer::class);
    }

    public function landing_name()
    {
        return $this->belongsTo(LandingName::class);
    }

    public function landing()
    {
        return $this->belongsTo(Landing::class);
    }

    public function source()
    {
        return $this->belongsTo(Source::class);
    }

    public static function saveLead($data)
    {
        $data['status_id'] = 1;

        $fullName = trim($data['full_name']);

        $data['full_name'] = preg_replace('/\s+/', ' ', $fullName);

        $data['email'] = Str::lower($data['email']);

        // get country
        $country = Country::where('ISO_2', $data['country'])->first();
        $data['country_id'] = $country->id;


        $curUser = '';
        if(isset($data['user_id'])){
            $curUser = User::find($data['user_id']);
        }


        if(isset($curUser->id) && $curUser->role->name == 'affiliate'){
            $landingName = LandingName::where([
                'name' => $data['landing_name']
            ])->first();

            $data['landing_name_id'] = null;
        }else{
            // get or crete landing name
            $landingName = LandingName::firstOrCreate([
                'name' => $data['landing_name']
            ]);
            $data['landing_name_id'] = $landingName->id;
        }
        // parse URL
        $landingURL = '';
        $landingQuery = '';
        $landingFullURL = parse_url($data['landing']);

        if (isset($landingFullURL['host'])) {
            $landingURL .= $landingFullURL['host'];
        }
        if (isset($landingFullURL['path'])) {
            $landingURL .= $landingFullURL['path'];
        }
        if (isset($landingFullURL['query'])) {
            if (strpos($landingFullURL['query'], '&fbclid') !== -1) {
                parse_str($landingFullURL['query'], $output);
                unset($output['fbclid']);
                $landingFullURL['query'] = http_build_query($output);
            }

            if (strpos($landingFullURL['query'], '&gclid') !== -1) {
                parse_str($landingFullURL['query'], $output);
                unset($output['gclid']);
                $landingFullURL['query'] = http_build_query($output);
            }

            $landingQuery = $landingFullURL['query'];
        }

        $landingURL = trim($landingURL, '/');

        $landing = Landing::firstOrCreate([
            'url' => $landingURL
        ]);
        $data['query_parameters'] = urldecode($landingQuery);
        $data['landing_id'] = $landing->id;


        if(!isset($landingFullURL['host']) && isset($landingFullURL['path'])){
            $landingFullURL['host'] = $landingFullURL['path'];
        }


        // get user
        if (!isset($data['user_id']) && isset($landingFullURL['host'])) {
            $user = User::whereHas('domains', function ($query) use ($landingFullURL) {
                $query->where('url', $landingFullURL['host']);
            })->first();

            if ($user) {
                $data['user_id'] = $user->id;
            }
        }

        // get source
        $source = Source::where('name', $data['source'])->first();
        $data['source_id'] = $source->id;

        return Lead::create($data);
    }

    public static function getCountTodayByOffer($offerId)
    {
        return Lead::where('offer_id', $offerId)
            ->whereDate('created_at', date('Y-m-d'))
            ->count();
    }

    public static function getLeadForExport($data)
    {
        return DB::select("select DISTINCT leads.id,
                                     offers.name,
                                     leads.full_name,
                                     leads.email,
                                     leads.phone,
                                     leads.ip,
                                     users.name as user_name,
                                     lead_statuses.name as status_name,
                                     leads.is_action,
                                     leads.action_time,
                                     landings.url as landing_url,
                                     leads.query_parameters,
                                     countries.ISO_2 as country_name,
                                     sources.name as source_name,
                                     landing_names.name as landing_name,
                                     leads.created_at,
                                     leads.external_id
                        from leads left join users
                            on leads.user_id = users.id
                            left join lead_statuses
                            on leads.status_id = lead_statuses.id
                            left join landings
                            on leads.landing_id = landings.id
                            left join countries
                            on leads.country_id = countries.id
                            left join sources
                            on leads.source_id = sources.id
                            left join landing_names
                            on leads.landing_name_id = landing_names.id
                            left JOIN offers
                            on leads.offer_id = offers.id
                        where date(leads.created_at) BETWEEN ? AND ?
                        order by leads.created_at DESC", [$data['date_start'], $data['date_end']]);
    }

    public function scopeOffers($query, $itemIds)
    {
        if (!is_null($itemIds)) {
            $itemIds = explode(',', $itemIds);

            $query->whereIn('offer_id', $itemIds);
        }

        return $query;
    }

    public function scopeStatuses($query, $itemIds)
    {
        if (!is_null($itemIds)) {
            $itemIds = explode(',', $itemIds);

            $query->whereIn('status_id', $itemIds);
        }

        return $query;
    }

    public function scopeSearch($query, $search)
    {
        if (!is_null($search)) {
            return $query->where('email', 'like', '%' . $search . '%')
                ->orWhere('id', 'like', '%' . $search . '%');
        }

        return $query;
    }

    public function scopeQueryParameters($query, $item)
    {
        if (!is_null($item)) {
            return $query->where('query_parameters', 'like', '%' . $item . '%');
        }

        return $query;
    }

    public function scopeWebMasters($query, $itemIds)
    {
        if (!is_null($itemIds)) {
            $itemIds = explode(',', $itemIds);

            $query->whereIn('user_id', $itemIds);
        }

        return $query;
    }

    public function scopeDateStartRegistration($query, $item)
    {
        if (!is_null($item)) {
            return $query->whereDate('leads.created_at', '>=', $item);
        }

        return $query;
    }

    public function scopeDateEndRegistration($query, $item)
    {
        if (!is_null($item)) {
            return $query->whereDate('leads.created_at', '<=', $item);
        }

        return $query;
    }


    public function scopeDateStartDep($query, $item)
    {
        if (!is_null($item)) {
            return $query->whereDate('leads.action_time', '>=', $item);
        }

        return $query;
    }

    public function scopeDateEndDep($query, $item)
    {
        if (!is_null($item)) {
            return $query->whereDate('leads.action_time', '<=', $item);
        }

        return $query;
    }

    public function scopeLandingNames($query, $itemIds)
    {
        if (!is_null($itemIds)) {
            $itemIds = explode(',', $itemIds);

            $query->whereIn('landing_name_id', $itemIds);
        }

        return $query;
    }

    public function scopeCountry($query, $itemIds)
    {
        if (!is_null($itemIds)) {
            $itemIds = explode(',', $itemIds);

            return $query->whereIn('country_id', $itemIds);
        }

        return $query;
    }

    public function scopeLandings($query, $itemIds)
    {
        if (!is_null($itemIds)) {
            $itemIds = explode(',', $itemIds);

            return $query->whereIn('landing_id', $itemIds);
        }

        return $query;
    }

    public function scopeIsAction($query, $item)
    {
        if (!is_null($item)) {
            return $query->where('is_action', $item);
        }

        return $query;
    }

    public static function sendKeitaro($url)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        $res = curl_exec($ch);

        curl_close($ch);

        return $res;
    }

    public function scopeIndex($query, $request)
    {
        return $query
            ->search($request->search)
            ->queryParameters($request->query_parameters)
            ->dateStartRegistration($request->date_start_registration)
            ->dateEndRegistration($request->date_end_registration)
            ->dateStartDep($request->date_start_dep)
            ->dateEndDep($request->date_end_dep)
            ->statuses($request->lead_statuses_ids)
            ->webMasters($request->web_master_ids)
            ->landingNames($request->landing_name_ids)
            ->country($request->country_ids)
            ->landings($request->landing_ids)
            ->isAction($request->is_action)
            ->offers($request->offer_ids);
    }

    public function setActionTimeAttribute($value)
    {
        $this->attributes['action_time'] = date('Y-m-d H:i:s', strtotime($value));
    }
}

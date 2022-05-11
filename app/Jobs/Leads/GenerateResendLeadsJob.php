<?php

namespace App\Jobs\Leads;

use App\Models\Lead;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateResendLeadsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $data;

    private $timeActivity;

    /**
     * Create a new job instance.
     *
     * @param array $data
     * @param string $campaign
     * @param string $timeActivity
     * @return void
     */
    public function __construct($data, $timeActivity)
    {
        $this->data = $data;
        $this->timeActivity = $timeActivity;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $leads = Lead::select('id')
            ->whereHas('offer', function ($query) {
                $query->whereHas('integration', function ($query) {
                    $query->where('name', 'HOLD');
                });
            })
            ->where('status_id', 1)
            ->latest()
            ->pluck('id')
            ->toArray();

        $countLeads = count($leads);

        if ($countLeads == 0) {
            return;
        }

        $leadsFilter = [];
        $tempLeads = $leads;
        foreach ($leads as $key => $lead) {
            if ($key % 4 == 0) {
                // get new
                $leadsFilter[] = array_shift($tempLeads);
            } else {
                // get old
                $leadsFilter[] = array_pop($tempLeads);
            }
        }

        $startTime = Carbon::make(date($this->data['date_resend'] . ' ' . $this->data['time_resend']));

        $endTime = Carbon::make(date($this->data['date_resend'] . ' ' . $this->data['time_resend']))->addHours($this->data['hour'])->addMinutes($this->data['minute']);

        $duration = $endTime->diffInSeconds($startTime);

        $amplitude = $duration / $countLeads;

        $startRnd = 0;
        $endRnd = $amplitude;

        for ($i = 0; $i < $countLeads; $i++) {

            $seconds = rand($startRnd, $endRnd);

            SendLeadOfHoldJob::dispatch($this->data['offer_ids'], $i, $leadsFilter[$i])->delay(now()->addSeconds($seconds));

            $startRnd = $endRnd;
            $endRnd += $amplitude;
        }
    }

    public function tags()
    {
        return [
            'date_action' => $this->timeActivity
        ];
    }
}

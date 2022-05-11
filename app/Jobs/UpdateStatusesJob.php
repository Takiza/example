<?php

namespace App\Jobs;

use App\Models\Offer;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class UpdateStatusesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $offerId;

    private $date;

    /**
     * Create a new job instance.
     *
     * @param Offer $offerId
     * @param string $date
     *
     * @return void
     */
    public function __construct($offerId, $date)
    {
        $this->offerId = $offerId;
        $this->date = $date;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $offer = Offer::find($this->offerId);

        if($offer && isset($offer->integration)) {
            $className = "App\Integrations\\" . $offer->integration->name;

            if (class_exists($className)) {
                $integrationClass = new $className();

                $integrationClass->update($this->date);
            }
        }
    }
}

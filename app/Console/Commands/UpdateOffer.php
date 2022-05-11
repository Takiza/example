<?php

namespace App\Console\Commands;

use App\Jobs\UpdateStatusesJob;
use App\Models\Offer;
use Illuminate\Console\Command;

class UpdateOffer extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'update:offer';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $dateStart = date('Y-m-d', strtotime('-3 days'));
        $dateEnd = date('Y-m-d');

        $begin = new \DateTime($dateStart);
        $end = new \DateTime($dateEnd);
        $end = $end->modify('+1 day');

        $interval = new \DateInterval('P1D');
        $dateRange = new \DatePeriod($begin, $interval, $end);

        $offers = Offer::select(['id'])
            ->whereHas('leads', function ($query) use ($dateStart, $dateEnd){
                $query->whereDate('created_at', '>=', $dateStart)
                    ->whereDate('created_at', '<=', $dateEnd);
            })
            ->get();

        $i = 0;
        foreach ($offers as $offer) {
            foreach ($dateRange as $date) {
                $dateUpdate = $date->format("Y-m-d");

                UpdateStatusesJob::dispatch($offer->id, $dateUpdate)->delay(now()->addSeconds($i));
                $i += 2;
            }
        }


        return 1;
    }
}

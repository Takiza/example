<?php

namespace App\Console\Commands;

use App\Models\Offer;
use Illuminate\Console\Command;

class ClearCoefficient extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'clear:coefficient';

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
        $offers = Offer::select(['id', 'coefficient'])->get();

        foreach ($offers as $offer) {
            $offer->coefficient = 0;
            $offer->save();
        }

        return 1;
    }
}

<?php

namespace App\Observers;

use App\Jobs\SendKeitaroJob;
use App\Models\Lead;
use Illuminate\Support\Facades\Log;

class LeadObserver
{
    public function updated(Lead $lead)
    {
        if ($lead->isDirty('is_action') && $lead->getOriginal('is_action') == 0) {
            SendKeitaroJob::dispatch($lead->id, 'deposit');
        }
    }

}

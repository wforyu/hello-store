<?php

namespace App\Console\Commands;

use App\Models\FlashSale;
use Illuminate\Console\Command;

class FlashSaleActivate extends Command
{
    protected $signature = 'flash-sale:activate';

    protected $description = 'Activate flash sales that have reached their start time';

    public function handle(): void
    {
        $count = FlashSale::where('status', 'scheduled')
            ->where('is_active', true)
            ->where('start_time', '<=', now())
            ->update(['status' => 'active']);

        $this->info("Activated {$count} flash sales");
    }
}

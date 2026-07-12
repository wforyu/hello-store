<?php

namespace App\Console\Commands;

use App\Models\FlashSale;
use Illuminate\Console\Command;

class FlashSaleComplete extends Command
{
    protected $signature = 'flash-sale:complete';

    protected $description = 'Complete flash sales past their end time';

    public function handle(): void
    {
        $count = FlashSale::where('status', 'active')
            ->where('end_time', '<=', now())
            ->update(['status' => 'completed', 'is_active' => false]);

        $this->info("Completed {$count} flash sales");
    }
}

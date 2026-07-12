<?php

namespace App\Console\Commands;

use App\Models\Coupon;
use Illuminate\Console\Command;

class CouponExpire extends Command
{
    protected $signature = 'coupon:expire';

    protected $description = 'Expire coupons past their expires_at';

    public function handle(): void
    {
        $count = Coupon::where('is_active', true)
            ->where('expires_at', '<=', now())
            ->update(['is_active' => false]);

        $this->info("Expired {$count} coupons");
    }
}

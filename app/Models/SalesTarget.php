<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesTarget extends Model
{
    protected $fillable = [
        'name', 'target_amount', 'current_amount', 'target_orders',
        'current_orders', 'start_date', 'end_date', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'target_amount' => 'decimal:2',
            'current_amount' => 'decimal:2',
            'start_date' => 'date',
            'end_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function getRevenueProgressAttribute(): float
    {
        return $this->target_amount > 0
            ? round(($this->current_amount / $this->target_amount) * 100, 1)
            : 0;
    }

    public function getOrderProgressAttribute(): float
    {
        return $this->target_orders > 0
            ? round(($this->current_orders / $this->target_orders) * 100, 1)
            : 0;
    }

    public function getDaysRemainingAttribute(): int
    {
        return max(0, now()->diffInDays($this->end_date, false));
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where('start_date', '<=', now())
            ->where('end_date', '>=', now());
    }

    public static function syncCurrentData(): void
    {
        $activeTargets = self::active()->get();

        foreach ($activeTargets as $target) {
            $stats = Order::where('payment_status', 'paid')
                ->whereBetween('created_at', [$target->start_date, $target->end_date->copy()->endOfDay()])
                ->selectRaw('COUNT(*) as orders, COALESCE(SUM(total), 0) as revenue')
                ->first();

            $target->update([
                'current_amount' => $stats->revenue,
                'current_orders' => $stats->orders,
            ]);
        }
    }
}

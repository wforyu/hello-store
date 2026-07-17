<x-filament-panels::page>
    <style>
        .promo-cal { background: var(--gray-800); border: 1px solid var(--gray-700); border-radius: 12px; overflow: hidden; }
        .promo-cal-header { display: flex; align-items: center; justify-content: space-between; padding: 16px 20px; border-bottom: 1px solid var(--gray-700); }
        .promo-cal-header h3 { font-size: 18px; font-weight: 700; color: var(--gray-100); margin: 0; }
        .promo-cal-nav { display: flex; gap: 8px; }
        .promo-cal-nav button { padding: 6px 12px; border-radius: 6px; border: 1px solid var(--gray-600); background: var(--gray-700); color: var(--gray-300); cursor: pointer; font-size: 13px; }
        .promo-cal-nav button:hover { background: var(--gray-600); }
        .promo-cal-grid { display: grid; grid-template-columns: repeat(7, 1fr); }
        .promo-cal-day-header { padding: 8px; text-align: center; font-size: 12px; font-weight: 600; color: var(--gray-400); border-bottom: 1px solid var(--gray-700); }
        .promo-cal-day { min-height: 80px; padding: 6px; border-right: 1px solid var(--gray-700); border-bottom: 1px solid var(--gray-700); position: relative; }
        .promo-cal-day:nth-child(7n) { border-right: none; }
        .promo-cal-day-num { font-size: 12px; font-weight: 600; color: var(--gray-400); margin-bottom: 4px; }
        .promo-cal-day.today .promo-cal-day-num { color: var(--primary-400); background: var(--primary-900); width: 22px; height: 22px; border-radius: 50%; display: flex; align-items: center; justify-content: center; }
        .promo-cal-event { font-size: 10px; padding: 2px 4px; border-radius: 3px; margin-bottom: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; color: #fff; font-weight: 500; }
        .promo-cal-legend { display: flex; gap: 16px; padding: 12px 20px; border-top: 1px solid var(--gray-700); }
        .promo-cal-legend-item { display: flex; align-items: center; gap: 6px; font-size: 12px; color: var(--gray-400); }
        .promo-cal-legend-dot { width: 10px; height: 10px; border-radius: 50%; }
    </style>

    <div class="promo-cal">
        <div class="promo-cal-header">
            <h3>{{ $this->getMonthName() }}</h3>
            <div class="promo-cal-nav">
                <button wire:click="previousMonth">← Prev</button>
                <button wire:click="nextMonth">Next →</button>
            </div>
        </div>

        <div class="promo-cal-grid">
            @foreach(['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'] as $dayName)
                <div class="promo-cal-day-header">{{ $dayName }}</div>
            @endforeach

            @foreach($this->getCalendarDays() as $cell)
                @if($cell['day'] === null)
                    <div class="promo-cal-day" style="opacity: 0.3;"></div>
                @else
                    <div class="promo-cal-day {{ ($cell['is_today'] ?? false) ? 'today' : '' }}">
                        <div class="promo-cal-day-num">{{ $cell['day'] }}</div>
                        @foreach(array_slice($cell['events'], 0, 3) as $event)
                            <div class="promo-cal-event" style="background: {{ $event['color'] }};" title="{{ $event['label'] }}">
                                {{ $event['label'] }}
                            </div>
                        @endforeach
                        @if(count($cell['events']) > 3)
                            <div style="font-size: 9px; color: var(--gray-500);">+{{ count($cell['events']) - 3 }} lagi</div>
                        @endif
                    </div>
                @endif
            @endforeach
        </div>

        <div class="promo-cal-legend">
            <div class="promo-cal-legend-item">
                <div class="promo-cal-legend-dot" style="background: #f59e0b;"></div>
                Flash Sale
            </div>
            <div class="promo-cal-legend-item">
                <div class="promo-cal-legend-dot" style="background: #8b5cf6;"></div>
                Kupon
            </div>
            <div class="promo-cal-legend-item">
                <div class="promo-cal-legend-dot" style="background: #10b981;"></div>
                Bundle
            </div>
        </div>
    </div>
</x-filament-panels::page>

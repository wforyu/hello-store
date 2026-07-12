<?php

namespace App\Models\Traits;

use App\Models\ActivityTimeline;

trait RecordsActivity
{
    protected static function bootRecordsActivity(): void
    {
        foreach (static::getActivityEvents() as $event) {
            static::$event(function ($model) use ($event) {
                $model->recordActivity($event);
            });
        }
    }

    protected static function getActivityEvents(): array
    {
        return ['created', 'updated', 'deleted'];
    }

    protected function getActivityType(string $event): string
    {
        $type = strtolower(class_basename($this));

        return "{$type}_{$event}";
    }

    protected function getActivityDescription(string $event): string
    {
        $userName = auth()->user()?->name ?? 'System';
        $modelName = class_basename($this);
        $identifier = $this->name ?? $this->title ?? $this->order_number ?? $this->opname_number ?? $this->return_number ?? "#{$this->id}";

        return match ($event) {
            'created' => "{$userName} membuat {$modelName} {$identifier}",
            'updated' => "{$userName} mengupdate {$modelName} {$identifier}",
            'deleted' => "{$userName} menghapus {$modelName} {$identifier}",
            default => "{$userName} {$event} {$modelName} {$identifier}",
        };
    }

    public function recordActivity(string $event): void
    {
        if (app()->runningInConsole() && $event !== 'created') {
            return;
        }

        ActivityTimeline::create([
            'subject_type' => get_class($this),
            'subject_id' => $this->id,
            'user_id' => auth()->id(),
            'type' => $this->getActivityType($event),
            'description' => $this->getActivityDescription($event),
            'data' => $event === 'updated' ? [
                'old' => $this->getOriginal(),
                'new' => $this->getAttributes(),
            ] : null,
        ]);
    }
}

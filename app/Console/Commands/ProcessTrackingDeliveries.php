<?php

namespace App\Console\Commands;

use App\Models\TrackingDelivery;
use App\Services\Tracking\TrackingDeliveryProcessor;
use Illuminate\Console\Command;

class ProcessTrackingDeliveries extends Command
{
    protected $signature = 'tracking:deliver-pending {--limit=50 : Maximum deliveries processed in one run}';
    protected $description = 'Process a bounded batch of due tracking deliveries';

    public function handle(TrackingDeliveryProcessor $processor): int
    {
        $limit = max(1, min(200, (int) $this->option('limit')));
        TrackingDelivery::query()->where('status', 'processing')->where('updated_at', '<', now()->subMinutes(10))
            ->update(['status' => 'pending', 'next_attempt_at' => now(), 'error_summary' => 'Recovered stale processing claim.']);
        $ids = TrackingDelivery::query()->where('status', 'pending')
            ->where(fn ($query) => $query->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now()))
            ->orderBy('id')->limit($limit)->pluck('id');
        $processed = 0;
        foreach ($ids as $id) {
            $result = $processor->process((int) $id);
            $processed += (int) $result['processed'];
        }
        $this->info("Processed {$processed} due tracking delivery(ies).");
        return self::SUCCESS;
    }
}

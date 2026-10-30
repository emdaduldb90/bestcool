<?php

namespace App\Console\Commands;

use App\Models\TrackingEvent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PruneTrackingData extends Command
{
    protected $signature = 'tracking:prune {--limit=5000 : Maximum event rows deleted in one run}';
    protected $description = 'Delete expired tracking events and aggregates according to the configured retention periods';

    public function handle(): int
    {
        $limit = max(100, min(50000, (int) $this->option('limit')));
        $cutoff = now()->subDays(max(30, (int) config('tracking.retention_days', 180)));
        $ids = TrackingEvent::query()->where('occurred_at', '<', $cutoff)->orderBy('id')->limit($limit)->pluck('id');
        if ($ids->isNotEmpty()) {
            $eventIds = TrackingEvent::query()->whereIn('id', $ids)->pluck('event_id');
            DB::table('tracking_event_observations')->whereIn('event_id', $eventIds)->delete();
            TrackingEvent::query()->whereIn('id', $ids)->delete();
        }
        DB::table('tracking_consents')->whereNotNull('expires_at')->where('expires_at', '<', now())->delete();
        DB::table('tracking_sessions')->where('last_seen_at', '<', $cutoff)->delete();
        DB::table('tracking_daily_aggregates')->where('aggregate_date', '<', now()->subDays(max(90, (int) config('tracking.aggregate_retention_days', 730)))->toDateString())->delete();
        DB::table('tracking_link_aggregates')->where('aggregate_date', '<', now()->subDays(max(90, (int) config('tracking.aggregate_retention_days', 730)))->toDateString())->delete();
        DB::table('tracking_ai_reports')->where('created_at', '<', now()->subDays(max(90, (int) config('tracking.audit_retention_days', 365)))->startOfDay())->delete();
        DB::table('tracking_audit_logs')->where('created_at', '<', now()->subDays(max(90, (int) config('tracking.audit_retention_days', 365)))->startOfDay())->delete();
        $this->info('Pruned up to '.$ids->count().' expired event(s) and expired consent/session/aggregate records.');
        return self::SUCCESS;
    }
}

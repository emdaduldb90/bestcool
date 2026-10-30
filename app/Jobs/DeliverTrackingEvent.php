<?php

namespace App\Jobs;

use App\Services\Tracking\TrackingDeliveryProcessor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DeliverTrackingEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public readonly int $deliveryId) {}

    public function handle(TrackingDeliveryProcessor $processor): void
    {
        $result = $processor->process($this->deliveryId);
        if (! empty($result['retry_after'])) {
            $this->release((int) $result['retry_after']);
        }
    }
}

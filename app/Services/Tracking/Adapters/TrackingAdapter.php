<?php

namespace App\Services\Tracking\Adapters;

use App\Models\TrackingDelivery;

interface TrackingAdapter
{
    /** @return array{http_status:int,response_code:?string} */
    public function send(TrackingDelivery $delivery): array;
}

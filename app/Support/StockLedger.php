<?php

namespace App\Support;

use App\Models\Product;
use App\Models\StockAdjustment;

class StockLedger
{
    public static function record(Product $product, int $previousStock, int $change, string $reason, ?string $notes = null): void
    {
        StockAdjustment::query()->create([
            'product_id' => $product->id,
            'actor_id' => auth()->id(),
            'previous_stock' => $previousStock,
            'change_quantity' => $change,
            'resulting_stock' => $product->stock,
            'reason' => $reason,
            'notes' => $notes,
            'created_at' => now(),
        ]);
    }
}

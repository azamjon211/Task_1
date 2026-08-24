<?php

namespace App\Services;

use App\Models\Batch;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;

class PurchaseService
{
    public function purchase(int $providerId, array $lines, ?string $purchasedAt = null, ?string $referenceNo = null): Batch
    {
        if (empty($lines)) {
            throw new \InvalidArgumentException('A batch must contain at least one product line.');
        }

        return DB::transaction(function () use ($providerId, $lines, $purchasedAt, $referenceNo) {
            $batch = Batch::create([
                'provider_id' => $providerId,
                'purchased_at' => $purchasedAt ?? now()->toDateString(),
                'reference_no' => $referenceNo,
            ]);

            foreach ($lines as $s) {
                $this->assertProductBelongsToProvider($s['product_id'], $providerId);

                $batchItem = $batch->items()->create([
                    'product_id' => $s['product_id'],
                    'storage_id' => $s['storage_id'],
                    'qty' => $s['qty'],
                    'purchase_price' => $s['purchase_price'],
                    'remaining_qty' => $s['qty'],
                ]);

                StockMovement::create([
                    'storage_id' => $batchItem->storage_id,
                    'product_id' => $batchItem->product_id,
                    'qty' => $batchItem->qty,
                    'type' => StockMovement::TYPE_PURCHASE,
                    'source_id' => $batchItem->id,
                    'happened_at' => $batch->purchased_at,
                ]);
            }

            return $batch->load('items.product', 'items.storage');
        });
    }
    public function assertProductBelongsToProvider(int $productId, int $providerId):void
    {
        $product = Product::with('category')->findOrFail($productId);
        if($product->category->provider_id !== $providerId)
            throw new \DomainException("Product #{$productId} does not belong to provider #{$providerId }]");
    }
}



<?php

namespace App\Services;

use App\Models\Batch;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class PurchaseService
{
    public function purchase(int $providerId, array $lines, ?string $purchasedAt = null, ?string $referenceNo = null): Batch
    {
        if (empty($lines)) {
            throw new \InvalidArgumentException('A batch must contain at least one product line.');
        }

        $productIds = collect($lines)->pluck('product_id')->unique()->sort()->values();

        return DB::transaction(function () use ($providerId, $lines, $purchasedAt, $referenceNo, $productIds) {
            $products = Product::with('category')->whereIn('id', $productIds)->get()->keyBy('id');

            if ($products->count() !== $productIds->count()) {
                throw new ModelNotFoundException('One or more products were not found');
            }

            // Barcha qatorlarni bittada tekshiramiz: N ta qatordan oxirgisi provider'ga
            // tegishli bo'lmasa ham, oldingi qatorlar allaqachon yozilib ulgurmasligi uchun.
            $this->assertAllProductsBelongToProvider($products, $providerId);

            $batch = Batch::create([
                'provider_id' => $providerId,
                'purchased_at' => $purchasedAt ?? now()->toDateString(),
                'reference_no' => $referenceNo,
            ]);

            foreach ($lines as $s) {
                $batchItem = $batch->items()->create([
                    'product_id' => $s['product_id'],
                    'storage_id' => $s['storage_id'],
                    'qty' => $s['qty'],
                    'purchase_price' => $s['purchase_price'],
                    'remaining_qty' => $s['qty'],
                ]);
                $batchItem->setRelation('batch', $batch);

                StockMovement::recordPurchase($batchItem);
            }

            return $batch->load('items.product', 'items.storage');
        });
    }

    private function assertAllProductsBelongToProvider(Collection $products, int $providerId): void
    {
        $mismatched = $products->reject(fn (Product $product) => $product->category->provider_id === $providerId);

        if ($mismatched->isNotEmpty()) {
            $ids = $mismatched->pluck('id')->implode(', ');
            throw new \DomainException("Product(s) #{$ids} do not belong to provider #{$providerId}.");
        }
    }
}

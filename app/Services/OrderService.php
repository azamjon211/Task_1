<?php

namespace App\Services;

use App\Models\BatchItem;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

class OrderService
{
    public function createOrder(int $clientId, array $products){
        if(empty($products)){
            throw new \InvalidArgumentException('An order must contain at least one product');
        }
        return DB::transaction(function() use($clientId, $products){
            $order = Order::create(['client_id' => $clientId]);
            foreach($products as $product){
                $this->allocateProduct($order, (int)$product['id'], (int)$product['qty']);
            }
            return $order->load('items.product', 'items.batchItem', 'items.storage');
        });
    }
    private function allocateProduct(Order $order, int $productId, int $qty){
        $product = Product::findOrFail($productId);
        $batchItems = BatchItem::query()
            ->where('product_id', $productId)
            ->where('remaining_qty', '>', 0)
            ->join('batches', 'batches.id', '=', 'batch_items.batch_id')
            ->orderBy('batches.purchased_at')
            ->orderBy('batch_items.id')
            ->select('batch_items.*')
            ->lockForUpdate()
            ->get();
        $remainingToAllocate = $qty;
        foreach($batchItems as $item){
            if($remainingToAllocate <=0){
                break;
            }
            $take = min($item->remaining_qty, $remainingToAllocate);
            $order->items()->create([
                'product_id' => $productId,
                'batch_item_id' => $item->id,
                'storage_id' => $item->storage_id,
                'qty' => $take,
                'sale_price' => $product->price,
            ]);
            $item->decrement('remaining_qty', $take);
            $remainingToAllocate -= $take;
        }
        if ($remainingToAllocate > 0) {
            throw new \DomainException(
                "Not enough stock for product #{$productId}: requested {$qty}, short by {$remainingToAllocate}."
            );
        }
    }
}

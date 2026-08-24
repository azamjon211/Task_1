<?php

namespace App\Services;

use App\Models\BatchItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class OrderService
{
    public function createOrder(int $clientId, array $products)
    {
        if (empty($products)) {
            throw new \InvalidArgumentException('An order must contain at least one product');
        }

        // Bir xil product'larni birlashtiramiz va product_id bo'yicha sort qilamiz:
        // barcha so'rovlar bir xil (deterministik) tartibda qulf olishi kerak,
        // aks holda 2 ta parallel order bir-birini kutib qolib deadlock beradi.
        $requested = [];
        foreach ($products as $product) {
            $id = (int) $product['id'];
            $requested[$id] = ($requested[$id] ?? 0) + (int) $product['qty'];
        }
        ksort($requested);
        $productIds = array_keys($requested);

        return DB::transaction(function () use ($clientId, $requested, $productIds) {
            $order = Order::create(['client_id' => $clientId]);

            $productsById = Product::whereIn('id', $productIds)->get()->keyBy('id');
            if ($productsById->count() !== count($productIds)) {
                throw new ModelNotFoundException('One or more products were not found');
            }

            // Barcha productlar uchun FIFO tartibi — productlar soniga qaramasdan bitta (qulfsiz) so'rov
            $orderedIds = DB::table('batch_items')
                ->join('batches', 'batches.id', '=', 'batch_items.batch_id')
                ->whereIn('batch_items.product_id', $productIds)
                ->where('batch_items.remaining_qty', '>', 0)
                ->orderBy('batches.purchased_at')
                ->orderBy('batch_items.id')
                ->pluck('batch_items.id');

            // Shu id'larning barchasini bitta so'rovda qulflaymiz — faqat batch_items,
            // batches'ga tegmaymiz (keraksiz qulflashni oldini olish uchun).
            $batchItemsByProduct = BatchItem::whereIn('id', $orderedIds)
                ->where('remaining_qty', '>', 0)
                ->lockForUpdate()
                ->get()
                ->sortBy(fn ($item) => $orderedIds->search($item->id))
                ->values()
                ->groupBy('product_id');

            foreach ($requested as $productId => $qty) {
                $this->allocateProduct(
                    $order,
                    $productsById[$productId],
                    $batchItemsByProduct->get($productId, new Collection()),
                    $qty
                );
            }

            return $order->load('items.product', 'items.batchItem', 'items.storage');
        }, 3);
    }

    private function allocateProduct(Order $order, Product $product, $batchItems, int $qty): void
    {
        $remainingToAllocate = $qty;

        foreach ($batchItems as $item) {
            if ($remainingToAllocate <= 0) {
                break;
            }

            $take = min($item->remaining_qty, $remainingToAllocate);
            $orderItem = $order->items()->create([
                'product_id' => $product->id,
                'batch_item_id' => $item->id,
                'storage_id' => $item->storage_id,
                'qty' => $take,
                'sale_price' => $product->price,
            ]);

            StockMovement::create([
                'storage_id' => $item->storage_id,
                'product_id' => $product->id,
                'qty' => -$take,
                'type' => StockMovement::TYPE_SALE,
                'source_id' => $orderItem->id,
                'happened_at' => $order->created_at,
            ]);

            $item->decrement('remaining_qty', $take);
            $remainingToAllocate -= $take;
        }

        if ($remainingToAllocate > 0) {
            throw new \DomainException(
                "Not enough stock for product #{$product->id}: requested {$qty}, short by {$remainingToAllocate}."
            );
        }
    }
}

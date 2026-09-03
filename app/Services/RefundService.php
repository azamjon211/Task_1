<?php

namespace App\Services;

use App\Models\BatchItem;
use App\Models\OrderItem;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RefundService
{
    public function refundBatch(array $lines){
        if(empty($lines)){
            throw new \Exception('At least one refund line is required.');
        }
        return DB::transaction(function () use ($lines) {
            // Bir xil tartibda qulflash uchun id'larni sort qilamiz (OrderService'dagi kabi deadlock oldini olish uchun).
            $itemIds = collect($lines)->pluck('batch_item_id')->unique()->sort()->values();
            $items = BatchItem::whereIn('id', $itemIds)->lockForUpdate()->get()->keyBy('id');

            if ($items->count() !== $itemIds->count()) {
                throw new ModelNotFoundException('One or more batch items were not found');
            }

            // Barcha qatorlarni bittada tekshiramiz: 10 qatordan 9-tasi yozilib,
            // faqat oxirgisida xato chiqishining oldini olish uchun.
            $this->assertRefundLinesAreValid($lines, 'batch_item_id', $items->map->remaining_qty, 'Batch refund rejected');

            return collect($lines)->map(fn (array $line) => $this->refundSingleBatchItem($line, $items[$line['batch_item_id']]));
        });
    }

    public function refundSingleBatchItem(array $line, ?BatchItem $item = null){
        $item ??= BatchItem::lockForUpdate()->findOrFail($line['batch_item_id']);
        $qty = (int)$line['qty'];
        if($qty <= 0)
            throw new \Exception('Refund must be more than 0');
        if($qty > $item->remaining_qty)
            throw new \DomainException("cannot refund  {$qty} units of batch item # {$item->id}; only{$item->remaining_qty} remain in stock ");

        $refund = $item->refunds()->create([
            'qty' => $qty,
            'amount' => $qty*$item->purchase_price,
            'refunded_at' => now(),
        ]);

        StockMovement::recordBatchRefund($refund, $item);

        return $refund;
    }

    public function refundOrder(array $lines){
        if(empty($lines))
            throw new \InvalidArgumentException('At least one refund line is required');
        return DB::transaction(function() use ($lines) {
            $itemIds = collect($lines)->pluck('order_item_id')->unique()->sort()->values();
            $orderItems = OrderItem::whereIn('id', $itemIds)->lockForUpdate()->get()->keyBy('id');

            if ($orderItems->count() !== $itemIds->count()) {
                throw new ModelNotFoundException('One or more order items were not found');
            }

            $this->assertRefundLinesAreValid($lines, 'order_item_id', $orderItems->mapWithKeys(fn ($item) => [$item->id => $item->qty - $item->refunded_qty]), 'Order refund rejected');

            return collect($lines)->map(fn (array $line) => $this->refundSingleOrderItem($line, $orderItems[$line['order_item_id']]));
        });
    }

    private function refundSingleOrderItem(array $line, OrderItem $orderItem){
        $qty = (int)$line['qty'];

        $refund = $orderItem->refunds()->create([
            'qty' => $qty,
            'amount' => $qty*$orderItem->sale_price,
            'reason' => $line['reason'] ?? null,
            'refunded_at' => now(),
        ]);

        $batchItem = BatchItem::findOrFail($orderItem->batch_item_id);
        StockMovement::recordOrderRefund($refund, $orderItem, $batchItem);

        return $refund;
    }

    /**
     * Shared by refundBatch/refundOrder: walks every line against a running "remaining"
     * map (keyed by the id field named), so a shortage two lines apart in the same
     * request is still caught before anything is written, and reports all bad lines at once.
     */
    private function assertRefundLinesAreValid(array $lines, string $idField, Collection $remaining, string $rejectionPrefix): void
    {
        $errors = [];

        foreach ($lines as $i => $line) {
            $id = $line[$idField];
            $qty = (int) $line['qty'];

            if ($qty <= 0) {
                $errors[] = "line #{$i}: refund qty must be more than 0";
            } elseif ($qty > $remaining[$id]) {
                $errors[] = "line #{$i}: cannot refund {$qty} units of #{$id}; only {$remaining[$id]} available";
            } else {
                $remaining[$id] -= $qty;
            }
        }

        if (!empty($errors)) {
            throw new \DomainException("{$rejectionPrefix}: " . implode('; ', $errors) . '.');
        }
    }
}

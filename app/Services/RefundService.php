<?php

namespace App\Services;

use App\Models\BatchItem;
use App\Models\OrderItem;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;

class RefundService
{
    public function refundBatch(array $lines){
        if(empty($lines)){
            throw new \Exception('At least one refund line is required.');
        }
        return DB::transaction (function() use ($lines){
            return collect ($lines)->map(fn(array $line) => $this->refundSingleBatchItem($line));
        } );
    }
    public function refundSingleBatchItem(array $line){
        $item = BatchItem::lockForUpdate()->findOrFail($line['batch_item_id']);
        $qty = (int)$line['qty'];
        if($qty <= 0)
            throw new \Exception('Refund must be more than 0');
        if($qty > $item->remaining_qty)
            throw new \DomainException("cannot refund  {$qty} units of batch item # {$item->id}; only{$item->remaining_qty} remain in stock ");
        $item->decrement('remaining_qty', $qty);
        $item->increment('refunded_qty', $qty);
        $refund = $item->refunds()->create([
            'qty' => $qty,
            'amount' => $qty*$item->purchase_price,
            'refunded_at' => now(),
        ]);

        StockMovement::create([
            'storage_id' => $item->storage_id,
            'product_id' => $item->product_id,
            'qty' => -$qty,
            'type' => StockMovement::TYPE_BATCH_REFUND,
            'source_id' => $refund->id,
            'happened_at' => $refund->refunded_at,
        ]);

        return $refund;
    }
    public function refundOrder(array $lines){
        if(empty($lines))
            throw new \InvalidArgumentException('At least one refund line is required');
        return DB::transaction(function() use ($lines) {
            return collect($lines)->map(fn(array $line) => $this->refundSingleOrderItem($line));
        });
    }
    private function refundSingleOrderItem(array $line){
        $orderItem = OrderItem::lockForUpdate()->findOrFail($line['order_item_id']);
        $qty = (int)$line['qty'];
        $refundable = $orderItem->qty - $orderItem->refunded_qty;
        if($qty <= 0)
            throw new \InvalidArgumentException('Refund must be more than 0');
        if($qty > $refundable)
            throw new \DomainException(
                "Cannot refund {$qty} units of order item # {$orderItem->id}; only{$refundable} refunds in stock "
            );
        $orderItem->increment('refunded_qty', $qty);
        $refund = $orderItem->refunds()->create([
            'qty' => $qty,
            'amount' => $qty*$orderItem->sale_price,
            'reason' => $line['reason'] ?? null,
            'refunded_at' => now(),
        ]);
        BatchItem::where('id', $orderItem->batch_item_id)->increment('remaining_qty', $qty);

        StockMovement::create([
            'storage_id' => $orderItem->storage_id,
            'product_id' => $orderItem->product_id,
            'qty' => $qty,
            'type' => StockMovement::TYPE_ORDER_REFUND,
            'source_id' => $refund->id,
            'happened_at' => $refund->refunded_at,
        ]);

        return $refund;
        }


}

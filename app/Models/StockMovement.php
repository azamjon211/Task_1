<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    const TYPE_PURCHASE = 'purchase';
    const TYPE_BATCH_REFUND = 'batch_refund';
    const TYPE_SALE = 'sale';
    const TYPE_ORDER_REFUND = 'order_refund';

    protected $fillable = [
        'storage_id', 'product_id', 'qty', 'amount', 'type',
        'batch_id', 'order_id', 'source_id', 'happened_at',
    ];

    protected $casts = [
        'happened_at' => 'datetime',
        'amount' => 'decimal:2',
    ];

    public function storage(): BelongsTo
    {
        return $this->belongsTo(Storage::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * These four are the only place a stock_movements row is written or a
     * remaining_qty/refunded_qty counter is mutated, so the counters can't drift
     * from the ledger. amount is signed so profit-per-batch is just SUM(amount).
     */
    public static function recordPurchase(BatchItem $batchItem): self
    {
        return static::create([
            'storage_id' => $batchItem->storage_id,
            'product_id' => $batchItem->product_id,
            'qty' => $batchItem->qty,
            'amount' => -1 * $batchItem->qty * $batchItem->purchase_price,
            'type' => self::TYPE_PURCHASE,
            'batch_id' => $batchItem->batch_id,
            'source_id' => $batchItem->id,
            'happened_at' => $batchItem->batch->purchased_at,
        ]);
    }

    public static function recordSale(OrderItem $orderItem, BatchItem $batchItem): self
    {
        $movement = static::create([
            'storage_id' => $batchItem->storage_id,
            'product_id' => $orderItem->product_id,
            'qty' => -$orderItem->qty,
            'amount' => $orderItem->qty * $orderItem->sale_price,
            'type' => self::TYPE_SALE,
            'batch_id' => $batchItem->batch_id,
            'order_id' => $orderItem->order_id,
            'source_id' => $orderItem->id,
            'happened_at' => $orderItem->order->created_at,
        ]);

        $batchItem->decrement('remaining_qty', $orderItem->qty);

        return $movement;
    }

    public static function recordBatchRefund(BatchRefund $refund, BatchItem $batchItem): self
    {
        $movement = static::create([
            'storage_id' => $batchItem->storage_id,
            'product_id' => $batchItem->product_id,
            'qty' => -$refund->qty,
            'amount' => $refund->amount,
            'type' => self::TYPE_BATCH_REFUND,
            'batch_id' => $batchItem->batch_id,
            'source_id' => $refund->id,
            'happened_at' => $refund->refunded_at,
        ]);

        $batchItem->decrement('remaining_qty', $refund->qty);
        $batchItem->increment('refunded_qty', $refund->qty);

        return $movement;
    }

    public static function recordOrderRefund(OrderRefund $refund, OrderItem $orderItem, BatchItem $batchItem): self
    {
        $movement = static::create([
            'storage_id' => $orderItem->storage_id,
            'product_id' => $orderItem->product_id,
            'qty' => $refund->qty,
            'amount' => -1 * $refund->amount,
            'type' => self::TYPE_ORDER_REFUND,
            'batch_id' => $batchItem->batch_id,
            'order_id' => $orderItem->order_id,
            'source_id' => $refund->id,
            'happened_at' => $refund->refunded_at,
        ]);

        $orderItem->increment('refunded_qty', $refund->qty);
        $batchItem->increment('remaining_qty', $refund->qty);

        return $movement;
    }
}

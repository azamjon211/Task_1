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

    protected $fillable = ['storage_id', 'product_id', 'qty', 'type', 'source_id', 'happened_at'];

    protected $casts = [
        'happened_at' => 'datetime',
    ];

    public function storage(): BelongsTo
    {
        return $this->belongsTo(Storage::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}

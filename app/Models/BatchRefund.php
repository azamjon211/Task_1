<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BatchRefund extends Model
{
    use HasFactory;
    protected $fillable = ['batch_item_id', 'qty', 'amount', 'reason', 'refunded_at'];
    protected $casts = [
        'amount' => 'decimal:2',
        'refunded_at' => 'datetime'
    ];
    public function batchItem():BelongsTo{
        return $this->belongsTo(BatchItem::class);
    }
}

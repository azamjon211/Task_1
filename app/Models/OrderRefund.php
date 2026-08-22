<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderRefund extends Model
{
    use HasFactory;
    protected $fillable = ['order_item_id', 'qty', 'amount','reason', 'refunded_at'];
    protected $casts = [
        'amount' =>'decimal:2',
        'refunded_at' => 'datetime'
    ];
    public function orderItem():BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }
}

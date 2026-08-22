<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;
    protected $fillable = ['category_id', 'name', 'price'];
    protected $casts = [
        'price' =>'decimal:2',
    ];
    public function category():BelongsTo{
        return $this->belongsTo(Category::class);
    }
    public function batchItems():HasMany{
        return $this->hasMany(BatchItem::class);
    }
    public function orderItems():HasMany{
        return $this->hasMany(OrderItem::class);
    }

}

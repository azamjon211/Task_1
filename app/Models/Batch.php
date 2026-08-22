<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Batch extends Model
{
    protected $fillable = ['provider_id', 'purchased_at', 'reference_no'];
    protected $casts = [
        'purchased_at' => 'date'];
    public function provider():BelongsTo{
        return $this->belongsTo(Provider::class);}
    public function items():HasMany{
        return $this->hasMany(BatchItem::class);
    }

}

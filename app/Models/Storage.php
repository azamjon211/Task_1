<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Storage extends Model
{
    use HasFactory;
    protected $fillable = ['name', 'location'];
    public function batchesItems():HasMany{
        return $this->hasMany(BatchItem::class);
    }
}

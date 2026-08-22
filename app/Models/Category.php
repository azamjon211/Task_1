<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    protected $fillable = ['parent_id', 'provider_id', 'name'];
    public function parent():BelongsTo{
        return $this->belongsTo(Category::class);
    }

    public function provider():BelongsTo{
        return $this->belongsTo(Provider::class);
    }
    public function children():HasMany{
        return $this->hasMany(Category::class, 'parent_id');
    }
    public function isRoot():bool{
        return is_null($this->parent_id);
    }
    public function products():HasMany{
        return $this->hasMany(Product::class);
    }
}

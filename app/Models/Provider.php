<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Provider extends Model
{
    use HasFactory;
    protected $fillable = [
        'name', 'phone', 'email'];
    public function categories():HasMany{
        return $this->hasMany(Category::class);
    }
    public function batches():HasMany{
        return $this->hasMany(Batch::class);
    }
}

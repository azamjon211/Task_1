<?php

namespace App\Http\Controllers;

use App\Models\Product;

class ProductAvailabilityService
{
    public function available(){
        return Product::query()
            ->with('category')
            ->withSum('batchItems as qty', 'remaining_qty')
            ->having('qty', '>', 0)
            ->orderBy('name')
            ->get()
            ->map(fn(Product $product)=> [
                'id' => $product->id,
                'name' => $product->name,
                'category_name' => $product->category->name,
                'price'=>$product->price,
                'qty' => (int)$product->qty,
            ]);
    }
}

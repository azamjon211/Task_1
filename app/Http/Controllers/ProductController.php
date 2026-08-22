<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductRequest;
use App\Models\Product;

class ProductController extends Controller
{
    public function __construct(private ProductAvailabilityService  $availability)
    {
    }
    public function available(){
        return response()->json(['data' => $this->availability->available()]);
    }
    public function index()
    {
        return response()->json(['data' => Product::all()]);
    }
    public function store(StoreProductRequest $request)
    {
        $product = Product::create([
            'category_id' => $request->category_id,
            'name' => $request->name,
            'price' => $request->price,
        ]);
        return response()->json(['data' => $product], 201);
    }
}

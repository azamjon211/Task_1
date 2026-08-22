<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCategoryRequest;
use App\Models\Category;
use App\Services\CategoryService;

class CategoryController extends Controller
{
        public function __construct(private CategoryService $categoryService)
        {
        }
        public function index()
        {
            return response()->json(['data' => Category::all()]);
        }
        public function store(StoreCategoryRequest $request){
            $category = $this->categoryService->create($request->all());
            return response()->json(['data' => $category], 201);
        }
}

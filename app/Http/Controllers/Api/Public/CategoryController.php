<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Category;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = Category::withCount('activeProducts')->get();
        return CategoryResource::collection($categories);
    }

    public function show(Category $category)
    {
        $category->loadCount('activeProducts');
        return new CategoryResource($category);
    }
}

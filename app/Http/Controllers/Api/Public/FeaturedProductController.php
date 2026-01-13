<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\FeaturedProductResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;

class FeaturedProductController extends Controller
{
    /**
     * Get featured products for the featured page
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        $featuredProducts = Product::with(['category', 'primaryImage'])
            ->where('is_active', true)
            ->where('stock_quantity', '>', 0)
            ->latest('created_at')
            ->take(12)
            ->get();

        return response()->json([
            'success' => true,
            'data' => FeaturedProductResource::collection($featuredProducts),
        ]);
    }
}

<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;

class PageController extends Controller
{
    public function home(): JsonResponse
    {
        // Get featured products (latest 8 products)
        $featuredProducts = Product::with(['category', 'images'])
            ->where('is_active', true)
            ->where('stock_quantity', '>', 0)
            ->latest()
            ->take(8)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'featured_products' => ProductResource::collection($featuredProducts),
                'hero' => [
                    'title' => 'Discover Your Perfect Bag',
                    'subtitle' => 'Elegant and stylish bags for every occasion',
                    'cta_text' => 'Shop Now',
                ],
            ],
        ]);
    }

    public function about(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'title' => 'About Us',
                'content' => 'We are a premium bags store in Morocco, offering high-quality bags for women.',
                'mission' => 'To provide stylish and affordable bags that empower women.',
                'contact' => [
                    'email' => 'contact@bagsstore.ma',
                    'phone' => '+212 XXX XXX XXX',
                ],
            ],
        ]);
    }
}

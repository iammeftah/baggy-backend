<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\HomepageCategoryResource;
use App\Http\Resources\HomepageProductResource;
use App\Http\Resources\HomepageWebstoreResource;
use App\Models\Category;
use App\Models\Product;
use App\Models\WebstoreInfo;
use Illuminate\Http\JsonResponse;

class HomepageController extends Controller
{
    /**
     * Get all homepage data in a single request
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        // Get all categories
        $categories = Category::orderBy('name', 'asc')->get();

        // Get 5 latest products with their primary image
        $latestProducts = Product::with(['primaryImage'])
            ->where('is_active', true)
            ->where('stock_quantity', '>', 0)
            ->latest('created_at')
            ->take(5)
            ->get();

        // Get webstore information
        $webstoreInfo = WebstoreInfo::getActive();

        return response()->json([
            'success' => true,
            'data' => [
                'categories' => HomepageCategoryResource::collection($categories),
                'latest_products' => HomepageProductResource::collection($latestProducts),
                'webstore_info' => $webstoreInfo ? new HomepageWebstoreResource($webstoreInfo) : null,
                'hero' => [
                    'title' => 'Discover Your Perfect Bag',
                    'subtitle' => 'Elegant and stylish bags for every occasion',
                ],
            ],
        ]);
    }

    /**
     * Get only categories
     *
     * @return JsonResponse
     */
    public function categories(): JsonResponse
    {
        $categories = Category::orderBy('name', 'asc')->get();

        return response()->json([
            'success' => true,
            'data' => HomepageCategoryResource::collection($categories),
        ]);
    }

    /**
     * Get only latest products
     *
     * @return JsonResponse
     */
    public function latestProducts(): JsonResponse
    {
        $latestProducts = Product::with(['primaryImage'])
            ->where('is_active', true)
            ->where('stock_quantity', '>', 0)
            ->latest('created_at')
            ->take(5)
            ->get();

        return response()->json([
            'success' => true,
            'data' => HomepageProductResource::collection($latestProducts),
        ]);
    }

    /**
     * Get only webstore info (for footer)
     *
     * @return JsonResponse
     */
    public function webstoreInfo(): JsonResponse
    {
        $webstoreInfo = WebstoreInfo::getActive();

        if (!$webstoreInfo) {
            return response()->json([
                'success' => false,
                'message' => 'Webstore information not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => new HomepageWebstoreResource($webstoreInfo),
        ]);
    }
}

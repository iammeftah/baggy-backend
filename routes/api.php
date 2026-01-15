<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Auth\LoginController;
use App\Http\Controllers\Api\Auth\RegisterController;
use App\Http\Controllers\Api\Auth\LogoutController;
use App\Http\Controllers\Api\Public\ProductController as PublicProductController;
use App\Http\Controllers\Api\Public\CategoryController as PublicCategoryController;
use App\Http\Controllers\Api\Public\PageController;
use App\Http\Controllers\Api\Public\HomepageController;
use App\Http\Controllers\Api\Public\FeaturedProductController;
use App\Http\Controllers\Api\Customer\CartController;
use App\Http\Controllers\Api\Customer\OrderController as CustomerOrderController;
use App\Http\Controllers\Api\Customer\ProfileController;
use App\Http\Controllers\Api\Customer\ChatbotController;
use App\Http\Controllers\Api\Admin\DashboardController;
use App\Http\Controllers\Api\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Api\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Api\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Api\Admin\ProductImageController;
use App\Http\Controllers\Api\Admin\WebstoreInfoController;
use App\Http\Controllers\Api\Admin\AdminActivityController;
use App\Http\Controllers\Api\Admin\OrderReturnController as AdminOrderReturnController;
use App\Http\Controllers\Api\Customer\OrderReturnController as CustomerOrderReturnController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Authentication Routes
Route::prefix('auth')->group(function () {
    Route::post('/register', [RegisterController::class, 'register']);
    Route::post('/login', [LoginController::class, 'login']);
});

Route::get('/health', function () {
    return response()->json([
        'status' => 'healthy',
        'timestamp' => now()->toIso8601String()
    ], 200);
});

Route::get('/test-cloudinary', function() {
    return response()->json([
        'cloud_name' => env('CLOUDINARY_CLOUD_NAME'),
        'api_key' => env('CLOUDINARY_API_KEY') ? 'Set' : 'Not Set',
        'api_secret' => env('CLOUDINARY_API_SECRET') ? 'Set' : 'Not Set',
        'config' => config('filesystems.disks.cloudinary'),
    ]);
});

Route::get('/debug/cloudinary', function () {
    return response()->json([
        'cloudinary_disk_exists' => config('filesystems.disks.cloudinary') ? true : false,
        'cloud_name' => env('CLOUDINARY_CLOUD_NAME') ?? 'NOT SET',
        'api_key_set' => env('CLOUDINARY_API_KEY') ? 'YES' : 'NO',
        'api_secret_set' => env('CLOUDINARY_API_SECRET') ? 'YES' : 'NO',
        'default_disk' => config('filesystems.default'),
        'env_vars' => [
            'CLOUDINARY_CLOUD_NAME' => env('CLOUDINARY_CLOUD_NAME') ? 'SET' : 'NOT SET',
            'CLOUDINARY_API_KEY' => env('CLOUDINARY_API_KEY') ? 'SET' : 'NOT SET',
            'CLOUDINARY_API_SECRET' => env('CLOUDINARY_API_SECRET') ? 'SET' : 'NOT SET',
        ]
    ]);
});

// Homepage Routes (Public) - Single endpoint for all homepage data
Route::prefix('homepage')->group(function () {
    Route::get('/', [HomepageController::class, 'index']);
    Route::get('/categories', [HomepageController::class, 'categories']);
    Route::get('/latest-products', [HomepageController::class, 'latestProducts']);
    Route::get('/webstore-info', [HomepageController::class, 'webstoreInfo']);
});

Route::get('/featured-products', [FeaturedProductController::class, 'index']);

// Public Product & Category Routes - USE SLUG
Route::prefix('products')->group(function () {
    Route::get('/', [PublicProductController::class, 'index']);
    Route::get('/{product:slug}', [PublicProductController::class, 'show']); // ✅ Use slug
});

Route::prefix('categories')->group(function () {
    Route::get('/', [PublicCategoryController::class, 'index']);
    Route::get('/{category:slug}', [PublicCategoryController::class, 'show']); // ✅ Use slug for public
});

// Public Pages
Route::prefix('pages')->group(function () {
    Route::get('/home', [PageController::class, 'home']);
    Route::get('/about', [PageController::class, 'about']);
});

// Protected Routes
Route::middleware(['auth:sanctum'])->group(function () {

    Route::post('/auth/logout', [LogoutController::class, 'logout']);

    // Customer Routes
    Route::prefix('customer')->middleware(['role:customer'])->group(function () {

        // Cart
        Route::prefix('cart')->group(function () {
            Route::get('/', [CartController::class, 'index']);
            Route::post('/add', [CartController::class, 'add']);
            Route::put('/update/{cartItem}', [CartController::class, 'update']);
            Route::delete('/remove/{cartItem}', [CartController::class, 'remove']);
            Route::delete('/clear', [CartController::class, 'clear']);
        });

        // Orders
        Route::prefix('orders')->group(function () {
            Route::get('/', [CustomerOrderController::class, 'index']);
            Route::post('/', [CustomerOrderController::class, 'store']);
            Route::get('/{orderNumber}', [CustomerOrderController::class, 'show']);
        });

        // Profile
        Route::prefix('profile')->group(function () {
            Route::get('/', [ProfileController::class, 'show']);
            Route::put('/', [ProfileController::class, 'update']);
            Route::put('/password', [ProfileController::class, 'changePassword']);
        });

        // Chatbot
        Route::post('/chatbot', [ChatbotController::class, 'chat']);
        Route::get('/chatbot/history', [ChatbotController::class, 'history']);
        Route::delete('/chatbot/history', [ChatbotController::class, 'clearHistory']);


        Route::prefix('returns')->group(function () {
        Route::get('/', [CustomerOrderReturnController::class, 'index']);
        Route::post('/', [CustomerOrderReturnController::class, 'store']);
        Route::get('/{returnNumber}', [CustomerOrderReturnController::class, 'show']);
        Route::post('/{returnNumber}/cancel', [CustomerOrderReturnController::class, 'cancel']);
    });

        Route::get('/orders/{orderNumber}/return-eligibility', [CustomerOrderReturnController::class, 'checkEligibility']);
    });

    // Admin Routes - USE ID (default behavior)
    Route::prefix('admin')->middleware(['role:admin'])->group(function () {

        // Dashboard with Enhanced Stats
        Route::get('/dashboard', [DashboardController::class, 'index']);

        // Products Management with Activity Tracking
        Route::prefix('products')->group(function () {
            Route::get('/', [AdminProductController::class, 'index']);
            Route::get('/{product}', [AdminProductController::class, 'show']);
            Route::post('/', [AdminProductController::class, 'store']);
            Route::put('/{product}', [AdminProductController::class, 'update']);
            Route::delete('/{product}', [AdminProductController::class, 'destroy']);
            Route::post('/{product}/toggle-status', [AdminProductController::class, 'toggleStatus']);
            Route::patch('/{product}/adjust-stock', [AdminProductController::class, 'adjustStock']); // NEW

            // Product Images
            Route::post('/{product}/images', [ProductImageController::class, 'store']);
            Route::delete('/images/{image}', [ProductImageController::class, 'destroy']);
            Route::put('/images/{image}/set-primary', [ProductImageController::class, 'setPrimary']);
        });

        // Categories Management
        Route::prefix('categories')->group(function () {
            Route::get('/', [AdminCategoryController::class, 'index']);
            Route::get('/{category}', [AdminCategoryController::class, 'show']);
            Route::post('/', [AdminCategoryController::class, 'store']);
            Route::put('/{category}', [AdminCategoryController::class, 'update']);
            Route::post('/{category}', [AdminCategoryController::class, 'update']); // Support POST for FormData
            Route::delete('/{category}', [AdminCategoryController::class, 'destroy']);
        });

        // Orders Management with Activity Tracking
        Route::prefix('orders')->group(function () {
            Route::get('/', [AdminOrderController::class, 'index']);
            Route::get('/{orderNumber}', [AdminOrderController::class, 'show']);
            Route::patch('/{orderNumber}/status', [AdminOrderController::class, 'updateStatus']); // Changed to PATCH
            Route::get('/{orderNumber}/activity-history', [AdminOrderController::class, 'getActivityHistory']); // NEW
        });


        Route::prefix('returns')->group(function () {
            Route::get('/', [AdminOrderReturnController::class, 'index']);
            Route::get('/statistics', [AdminOrderReturnController::class, 'statistics']);
            Route::get('/{returnNumber}', [AdminOrderReturnController::class, 'show']);
            Route::post('/{returnNumber}/approve', [AdminOrderReturnController::class, 'approve']);
            Route::post('/{returnNumber}/reject', [AdminOrderReturnController::class, 'reject']);
            Route::post('/{returnNumber}/complete', [AdminOrderReturnController::class, 'complete']);
        });

        // Admin Activity Logs - NEW
        Route::prefix('activities')->group(function () {
            Route::get('/', [AdminActivityController::class, 'index']);
            Route::get('/summary', [AdminActivityController::class, 'summary']);
            Route::get('/statistics', [AdminActivityController::class, 'statistics']);
            Route::get('/entity/{entityType}/{entityId}', [AdminActivityController::class, 'entityTimeline']);
        });

        // Webstore Info Management
        Route::prefix('webstore-info')->group(function () {
            Route::get('/', [WebstoreInfoController::class, 'index']);
            Route::put('/', [WebstoreInfoController::class, 'update']);
            Route::post('/', [WebstoreInfoController::class, 'update']); // Support POST for FormData
            Route::post('/logo', [WebstoreInfoController::class, 'uploadLogo']);
            Route::delete('/logo', [WebstoreInfoController::class, 'deleteLogo']);
        });
    });
});

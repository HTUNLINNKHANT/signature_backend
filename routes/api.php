<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\WishlistController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\BrandController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\TownshipDeliveryPriceController;
use App\Http\Controllers\WholesaleApplicationController;
use App\Http\Controllers\ExchangeRequestController;
use App\Http\Controllers\CouponController;
use App\Http\Controllers\Api\AddressController;
use App\Http\Controllers\Api\CartStockController;
use App\Http\Controllers\Api\CheckoutController;
use App\Http\Controllers\Api\AdminOrderController;
use App\Http\Controllers\Api\UserOrderController;

// Health check endpoint for Render
Route::get('health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now(),
        'app' => config('app.name'),
        'version' => '1.0.0'
    ]);
});

// CORS preflight handling
Route::options('{any}', function () {
    return response('', 200)
        ->header('Access-Control-Allow-Origin', '*')
        ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
        ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization');
})->where('any', '.*');

// Public routes
Route::apiResource('products', ProductController::class)->only(['index', 'show']);
Route::get('products/slug/{slug}', [ProductController::class, 'showBySlug']);
Route::get('products/featured/list', [ProductController::class, 'featured']);
Route::get('products/best-sellers/list', [ProductController::class, 'bestSellers']);
Route::get('products/trending/list', [ProductController::class, 'trending']);
Route::get('products/categories/list', [ProductController::class, 'categories']);
Route::get('categories/public', [\App\Http\Controllers\Api\CategoryController::class, 'getPublicCategories']);
Route::get('brands/navigation', [BrandController::class, 'getBrandsWithCategories']);

// Contact form submission (public)
Route::post('contact', [ContactController::class, 'store']);

// Exchange request submission (public)
Route::post('exchange-requests', [ExchangeRequestController::class, 'store']);

// Coupon validation (public)
Route::post('coupons/validate', [CouponController::class, 'validateCoupon']);

// Location Data for Address Forms (public)
Route::get('locations/available', [TownshipDeliveryPriceController::class, 'getAvailableLocations']);
Route::get('locations/state-regions', [TownshipDeliveryPriceController::class, 'getStateRegions']);
Route::get('locations/townships', [TownshipDeliveryPriceController::class, 'getTownshipsByRegion']);


// Authentication routes
Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
    
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('user', [AuthController::class, 'me']);
        Route::put('profile', [AuthController::class, 'updateProfile']);
        Route::put('password', [AuthController::class, 'changePassword']);
    });
});

// Protected routes requiring authentication
Route::middleware('auth:sanctum')->group(function () {
    
    // Profile routes
    Route::prefix('profile')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\ProfileController::class, 'show']);
        Route::put('/', [\App\Http\Controllers\Api\ProfileController::class, 'update']);
        Route::put('/password', [\App\Http\Controllers\Api\ProfileController::class, 'updatePassword']);
        Route::post('/avatar', [\App\Http\Controllers\Api\ProfileController::class, 'uploadAvatar']);
        Route::delete('/account', [\App\Http\Controllers\Api\ProfileController::class, 'deleteAccount']);
    });
    
    // Customer routes
    Route::prefix('customer')->group(function () {
        // Orders
        Route::get('orders', [OrderController::class, 'index']);
        Route::post('orders', [OrderController::class, 'store']);
        Route::get('orders/{id}', [OrderController::class, 'show']);
        Route::put('orders/{id}/cancel', [OrderController::class, 'cancel']);
        
        // Wishlist
        Route::get('wishlist', [WishlistController::class, 'index']);
        Route::post('wishlist', [WishlistController::class, 'store']);
        Route::delete('wishlist/{productId}', [WishlistController::class, 'destroy']);
        Route::get('wishlist/check/{productId}', [WishlistController::class, 'check']);
        Route::delete('wishlist', [WishlistController::class, 'clear']);
    });

    // Address Management
    Route::apiResource('addresses', AddressController::class);
    Route::put('addresses/{id}/set-default', [AddressController::class, 'setDefault']);
    Route::get('addresses/{id}/delivery-fee', [AddressController::class, 'calculateDeliveryFee']);
    Route::get('townships/available', [AddressController::class, 'getAvailableTownships']);
    
    
    // Checkout
    Route::post('checkout/calculate-totals', [CheckoutController::class, 'calculateTotals']);
    Route::post('checkout/place-order', [CheckoutController::class, 'placeOrder']);
    
    // Orders (alias for checkout endpoints for frontend compatibility)
    Route::post('orders/calculate-totals', [CheckoutController::class, 'calculateTotals']);
    
    // Cart Stock Management
    Route::post('cart/reserve-stock', [CartStockController::class, 'reserveStock']);
    Route::post('cart/release-stock', [CartStockController::class, 'releaseStock']);
    Route::put('cart/update-reserved-stock', [CartStockController::class, 'updateReservedStock']);
    Route::post('cart/check-stock', [CartStockController::class, 'checkStockAvailability']);
    
    // User Orders
    Route::get('orders', [UserOrderController::class, 'index']);
    Route::get('orders/recent', [UserOrderController::class, 'recent']);
    Route::get('orders/{id}', [UserOrderController::class, 'show']);
    Route::get('orders/status/{orderNumber}', [UserOrderController::class, 'getOrderStatus']);
    
    // Admin routes - require admin role
    Route::middleware('role:super_admin,product_manager,order_manager')->prefix('admin')->group(function () {
        
        // Dashboard (all admin roles)
        Route::get('dashboard', [AdminController::class, 'dashboard']);
        
        // Product management (product_manager, super_admin)
        Route::middleware('role:super_admin,product_manager')->group(function () {
            // Full product CRUD for admin
            Route::get('products', [ProductController::class, 'index']); // Admin product list with filters
            Route::post('products', [ProductController::class, 'store']);
            Route::get('products/statistics', [ProductController::class, 'statistics']);
            Route::get('products/export', [ProductController::class, 'export']);
            Route::get('products/form-options', [ProductController::class, 'formOptions']);
            Route::post('products/upload-image', [ProductController::class, 'uploadImage']);
            Route::post('products/upload-multiple-images', [ProductController::class, 'uploadMultipleImages']);
            Route::post('products/bulk-update', [ProductController::class, 'bulkUpdate']);
            Route::get('products/{id}', [ProductController::class, 'show']);
            Route::put('products/{id}', [ProductController::class, 'update']);
            Route::delete('products/{id}', [ProductController::class, 'destroy']);
            Route::put('products/{id}/toggle-status', [ProductController::class, 'toggleStatus']);
            Route::put('products/{id}/toggle-featured', [ProductController::class, 'toggleFeatured']);
        });
        
        // Order management (order_manager, super_admin)
        Route::middleware('role:super_admin,order_manager')->group(function () {
            Route::get('orders', [AdminOrderController::class, 'index']);
            Route::get('orders/statistics', [AdminOrderController::class, 'statistics']);
            Route::get('orders/{id}', [AdminOrderController::class, 'show']);
            Route::put('orders/{id}/status', [AdminOrderController::class, 'updateStatus']);
            Route::put('orders/{id}/approve-payment', [AdminOrderController::class, 'approvePayment']);
            Route::put('orders/{id}/reject-payment', [AdminOrderController::class, 'rejectPayment']);
            Route::get('orders/{id}/payment-screenshot', [AdminOrderController::class, 'getPaymentScreenshot']);
            Route::get('orders/{id}/payment-screenshot/download', [AdminOrderController::class, 'downloadPaymentScreenshot']);
        });
        
        // Exchange Request management (super_admin, order_manager)
        Route::middleware('role:super_admin,order_manager')->group(function () {
            Route::get('exchange-requests', [ExchangeRequestController::class, 'index']);
            Route::get('exchange-requests/statistics', [ExchangeRequestController::class, 'statistics']);
            Route::get('exchange-requests/{id}', [ExchangeRequestController::class, 'show']);
            Route::put('exchange-requests/{id}', [ExchangeRequestController::class, 'update']);
            Route::delete('exchange-requests/{id}', [ExchangeRequestController::class, 'destroy']);
        });
        
        // Customer management (super_admin, order_manager)
        Route::middleware('role:super_admin,order_manager')->group(function () {
            Route::get('customers', [\App\Http\Controllers\Api\CustomerController::class, 'index']);
            Route::post('customers', [\App\Http\Controllers\Api\CustomerController::class, 'store']);
            Route::get('customers/statistics', [\App\Http\Controllers\Api\CustomerController::class, 'statistics']);
            Route::get('customers/export', [\App\Http\Controllers\Api\CustomerController::class, 'export']);
            Route::get('customers/{id}', [\App\Http\Controllers\Api\CustomerController::class, 'show']);
            Route::put('customers/{id}', [\App\Http\Controllers\Api\CustomerController::class, 'update']);
            Route::delete('customers/{id}', [\App\Http\Controllers\Api\CustomerController::class, 'destroy']);
            Route::put('customers/{id}/toggle-status', [\App\Http\Controllers\Api\CustomerController::class, 'toggleStatus']);
        });

        // Category management (super_admin, order_manager)
        Route::middleware('role:super_admin,order_manager')->group(function () {
            Route::get('categories', [\App\Http\Controllers\Api\CategoryController::class, 'index']);
            Route::post('categories', [\App\Http\Controllers\Api\CategoryController::class, 'store']);
            Route::get('categories/parents', [\App\Http\Controllers\Api\CategoryController::class, 'getParents']);
            Route::get('categories/hierarchical', [\App\Http\Controllers\Api\CategoryController::class, 'getHierarchical']);
            Route::get('categories/statistics', [\App\Http\Controllers\Api\CategoryController::class, 'statistics']);
            Route::get('categories/export', [\App\Http\Controllers\Api\CategoryController::class, 'export']);
            Route::get('categories/{id}', [\App\Http\Controllers\Api\CategoryController::class, 'show']);
            Route::put('categories/{id}', [\App\Http\Controllers\Api\CategoryController::class, 'update']);
            Route::delete('categories/{id}', [\App\Http\Controllers\Api\CategoryController::class, 'destroy']);
            Route::put('categories/{id}/toggle-status', [\App\Http\Controllers\Api\CategoryController::class, 'toggleStatus']);
        });

        // Brand management (super_admin, order_manager)
        Route::middleware('role:super_admin,order_manager')->group(function () {
            Route::get('brands', [BrandController::class, 'index']);
            Route::post('brands', [BrandController::class, 'store']);
            Route::get('brands/statistics', [BrandController::class, 'statistics']);
            Route::get('brands/export', [BrandController::class, 'export']);
            Route::get('brands/{brand}', [BrandController::class, 'show']);
            Route::put('brands/{brand}', [BrandController::class, 'update']);
            Route::delete('brands/{brand}', [BrandController::class, 'destroy']);
            Route::put('brands/{brand}/toggle-status', [BrandController::class, 'toggleStatus']);
        });


        // Promotion management (super_admin, product_manager)
        Route::middleware('role:super_admin,product_manager')->group(function () {
            Route::get('promotions', [\App\Http\Controllers\Api\PromotionController::class, 'index']);
            Route::post('promotions', [\App\Http\Controllers\Api\PromotionController::class, 'store']);
            Route::get('promotions/statistics', [\App\Http\Controllers\Api\PromotionController::class, 'statistics']);
            Route::get('promotions/export', [\App\Http\Controllers\Api\PromotionController::class, 'export']);
            Route::get('promotions/{id}', [\App\Http\Controllers\Api\PromotionController::class, 'show']);
            Route::put('promotions/{id}', [\App\Http\Controllers\Api\PromotionController::class, 'update']);
            Route::delete('promotions/{id}', [\App\Http\Controllers\Api\PromotionController::class, 'destroy']);
            Route::put('promotions/{id}/toggle-status', [\App\Http\Controllers\Api\PromotionController::class, 'toggleStatus']);
        });

        // Coupon management (super_admin, product_manager)
        Route::middleware('role:super_admin,product_manager')->group(function () {
            Route::get('coupons', [CouponController::class, 'index']);
            Route::post('coupons', [CouponController::class, 'store']);
            Route::get('coupons/statistics', [CouponController::class, 'getStats']);
            Route::get('coupons/{coupon}', [CouponController::class, 'show']);
            Route::put('coupons/{coupon}', [CouponController::class, 'update']);
            Route::delete('coupons/{coupon}', [CouponController::class, 'destroy']);
        });
        
        // Customer management (super_admin, order_manager)
        Route::middleware('role:super_admin,order_manager')->group(function () {
            Route::get('customers', [\App\Http\Controllers\Api\CustomerController::class, 'index']);
            Route::post('customers', [\App\Http\Controllers\Api\CustomerController::class, 'store']);
            Route::get('customers/statistics', [\App\Http\Controllers\Api\CustomerController::class, 'statistics']);
            Route::get('customers/export', [\App\Http\Controllers\Api\CustomerController::class, 'export']);
            Route::get('customers/{id}', [\App\Http\Controllers\Api\CustomerController::class, 'show']);
            Route::put('customers/{id}', [\App\Http\Controllers\Api\CustomerController::class, 'update']);
            Route::delete('customers/{id}', [\App\Http\Controllers\Api\CustomerController::class, 'destroy']);
            Route::put('customers/{id}/toggle-status', [\App\Http\Controllers\Api\CustomerController::class, 'toggleStatus']);
            
            // VIP Management
            Route::put('customers/{id}/toggle-vip', [\App\Http\Controllers\Api\CustomerController::class, 'toggleVipStatus']);
            Route::post('customers/bulk-vip-update', [\App\Http\Controllers\Api\CustomerController::class, 'bulkUpdateVipStatus']);
        });
        
        // Township Delivery Price management (super_admin, order_manager)
        Route::middleware('role:super_admin,order_manager')->group(function () {
            Route::apiResource('township-delivery-prices', TownshipDeliveryPriceController::class);
            Route::get('township-delivery-prices/delivery-price/get', [TownshipDeliveryPriceController::class, 'getDeliveryPrice']);
            Route::get('township-delivery-prices/state-regions/list', [TownshipDeliveryPriceController::class, 'getStateRegions']);
            Route::put('township-delivery-prices/bulk-status/update', [TownshipDeliveryPriceController::class, 'bulkUpdateStatus']);
        });
        
        // User management (super_admin only)
        Route::middleware('role:super_admin')->group(function () {
            Route::get('users', [AdminController::class, 'users']);
            Route::post('users', [AdminController::class, 'createUser']);
            Route::put('users/{id}', [AdminController::class, 'updateUser']);
            Route::put('users/{id}/reset-password', [AdminController::class, 'resetPassword']);
            
            // Role and permission management
            Route::get('roles-permissions', [AdminController::class, 'rolesAndPermissions']);
            Route::post('roles', [AdminController::class, 'createRole']);
            Route::put('roles/{id}', [AdminController::class, 'updateRole']);
        });
        
        // Wholesale Application management (super_admin, order_manager)
        Route::middleware('role:super_admin,order_manager')->group(function () {
            Route::get('wholesale-applications', [WholesaleApplicationController::class, 'index']);
            Route::get('wholesale-applications/statistics', [WholesaleApplicationController::class, 'statistics']);
            Route::get('wholesale-applications/{application}', [WholesaleApplicationController::class, 'show']);
            Route::put('wholesale-applications/{application}/status', [WholesaleApplicationController::class, 'updateStatus']);
            Route::delete('wholesale-applications/{application}', [WholesaleApplicationController::class, 'destroy']);
        });

        // Contact management (super_admin, order_manager)
        Route::middleware('role:super_admin,order_manager')->group(function () {

        });

        // Exchange Request management (super_admin, order_manager)
        Route::middleware('role:super_admin,order_manager')->group(function () {
            Route::get('exchange-requests', [ExchangeRequestController::class, 'index']);
            Route::get('exchange-requests/statistics', [ExchangeRequestController::class, 'statistics']);
            Route::get('exchange-requests/{id}', [ExchangeRequestController::class, 'show']);
            Route::put('exchange-requests/{id}/status', [ExchangeRequestController::class, 'updateStatus']);
            Route::put('exchange-requests/{id}', [ExchangeRequestController::class, 'update']);
            Route::delete('exchange-requests/{id}', [ExchangeRequestController::class, 'destroy']);
        });

    });
});

// Public wholesale application routes
Route::post('wholesale-application/submit', [WholesaleApplicationController::class, 'submit']);
Route::get('wholesale-application/business-types', [WholesaleApplicationController::class, 'getBusinessTypes']);
Route::get('wholesale-application/product-categories', [WholesaleApplicationController::class, 'getProductCategories']);

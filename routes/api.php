<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\AppConfigController;
use App\Http\Controllers\DeviceTokenController;
use App\Http\Controllers\SafetyController;
use App\Http\Controllers\SupportController;
use App\Http\Controllers\Chat\MessagesController;
use App\Http\Controllers\Admin\BoostPackageController;

// Vendor Controllers
use App\Http\Controllers\Vendor\{
    DashboardController,
    ProductController as VendorProductController,
    StockController as VendorStockController,
    CompanyController as VendorCompanyController,
    ServiceController as VendorServiceController,
    CategoryController as VendorCategoryController,
    VendorMapController,
    VendorTypeController,
    SubCategoryController as VendorSubCategoryController,
    ProductCategoryController as VendorProductCategoryController,
    OrderController as VendorOrderController,
    VendorBoostController,
    NotificationController as VendorNotificationController,
};

// User Controllers
use App\Http\Controllers\User\{
    HomeController,
    WishlistController as UserWishlistController,
    ProductController,
    OrderController as UserOrderController,
    CartController as UserCartController,
    CheckoutController as UserCheckoutController,
    PaymentController as UserPaymentController,
    BoostedProductsController,
    NotificationController as UserNotificationController,
    AddressController as UserAddressController,
    ReviewController as UserReviewController,
};

// ────────────────────────────────
// Test & Utility Routes
// ────────────────────────────────
Route::get('test', function () {
    return response()->json(['status' => 'testings']);
});

// Maintenance helper. This runs artisan cache commands, so it must never be
// public — admins only (role_id = 1), behind Sanctum.
Route::middleware(['auth:sanctum', 'role:1'])->get('cache-clear', function () {
    Artisan::call('cache:clear');
    Artisan::call('config:clear');
    Artisan::call('config:cache');
    Artisan::call('view:clear');

    return response()->json(['status' => true, 'message' => 'Caches cleared.']);
});

// ────────────────────────────────
// Public Auth Routes
//
// The credential/OTP endpoints carry the tighter 'auth' limiter (defined in
// AppServiceProvider): 5/min per IP+email and 20/min per IP, so the blanket
// 60/min API limit cannot be used to brute force a password or an OTP.
// ────────────────────────────────
Route::middleware('throttle:auth')->group(function () {
    Route::post('/signup', [AuthController::class, 'signup']);
    Route::post('/resend-otp', [AuthController::class, 'resendOtp']);
    Route::post('/otp-verification', [AuthController::class, 'otpVerification']);
    Route::post('/signin', [AuthController::class, 'signin']);
    Route::post('/forgot_password', [AuthController::class, 'forgotPassword']);
});

Route::get('/vendor_type', [VendorTypeController::class, 'vendorType']);

// ────────────────────────────────
// App configuration & legal documents (public)
//
// The app calls /app-config on launch, before anyone is signed in, to learn
// whether the build is still supported and where the privacy policy lives.
// Both stores require a reachable privacy policy; Apple additionally requires
// terms to be linked wherever an account can be created.
// ────────────────────────────────
Route::get('/app-config', [AppConfigController::class, 'config']);
Route::get('/legal/privacy-policy', [AppConfigController::class, 'privacyPolicy']);
Route::get('/legal/terms', [AppConfigController::class, 'terms']);

// ────────────────────────────────
// Protected Routes (Requires Sanctum Auth)
// ────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {

    // Active boost packages. Was public (BCR-11); signed-in users only now.
    Route::get('/admin/boost-packages', [BoostPackageController::class, 'index']);

    // ───────────── Auth & Profile ─────────────
    Route::post('/reset_password', [AuthController::class, 'resetPassword']);
    Route::get('/profile', [UserController::class, 'viewProfile']);
    Route::post('/update_profile', [UserController::class, 'updateProfile']);

    // ───────────── Account deletion ─────────────
    // Mandatory for both stores: App Store guideline 5.1.1(v) and the Google
    // Play data deletion policy require deleting an account from inside the
    // app. Open to every signed-in role.
    Route::get('/account/deletion-info', [AccountController::class, 'deletionInfo']);
    Route::delete('/account', [AccountController::class, 'destroy']);

    // ───────────── Trust & safety (App Store guideline 1.2) ─────────────
    // The app carries user generated content (chat, listings, reviews), so it
    // must ship reporting and blocking.
    Route::get('/report-reasons', [SafetyController::class, 'reportReasons']);
    Route::post('/report', [SafetyController::class, 'report']);
    Route::get('/my-reports', [SafetyController::class, 'myReports']);
    Route::post('/block-user', [SafetyController::class, 'block']);
    Route::post('/unblock-user', [SafetyController::class, 'unblock']);
    Route::get('/blocked-users', [SafetyController::class, 'blockedUsers']);

    // ───────────── Push notification devices ─────────────
    Route::post('/device-token', [DeviceTokenController::class, 'register']);
    Route::delete('/device-token', [DeviceTokenController::class, 'unregister']);

    // ───────────── Support ─────────────
    Route::post('/support/contact', [SupportController::class, 'contact']);

    // ───────────── Reviews (readable by any signed-in role) ─────────────
    Route::get('/reviews/product/{productId}', [UserReviewController::class, 'productReviews']);
    Route::get('/reviews/vendor/{vendorId}', [UserReviewController::class, 'vendorReviews']);

    // ───────────── Vendor APIs (role_id = 2 only) ─────────────
    Route::prefix('vendor')->middleware('role:2')->group(function () {

        // Dashboard
        Route::get('/dashboard', [DashboardController::class, 'dashboard']);

        // Category, Subcategory, Company, Product Category
        Route::get('categories', [VendorCategoryController::class, 'index']);
        Route::get('sub-categories/{categoryId?}', [VendorSubCategoryController::class, 'show']);
        Route::get('companies/{id?}', [VendorCompanyController::class, 'show']);
        Route::get('product-categories/{id?}', [VendorProductCategoryController::class, 'show']);

        // Product Management
        Route::post('/add_product', [VendorProductController::class, 'addProduct']);
        Route::get('/get-all-products/{id?}', [VendorProductController::class, 'getAllProduct'])->name('vendor.store');
        Route::get('/show_product/{id?}', [VendorProductController::class, 'showProduct']);
        Route::post('/update_product/{id?}', [VendorProductController::class, 'updateProduct']);
        Route::delete('/delete_product/{id?}', [VendorProductController::class, 'deleteProduct']);

        // Service Management
        Route::post('/add_service', [VendorServiceController::class, 'addService']);
        Route::get('/show_service/{id?}', [VendorServiceController::class, 'showService']);
        Route::post('/update_service/{id}', [VendorServiceController::class, 'updateService']);
        Route::delete('/delete_service/{id}', [VendorServiceController::class, 'deleteService']);

        // Stock Management
        Route::post('/update-stock', [VendorStockController::class, 'updateStock']);

        // Vendor Map APIs
        Route::prefix('map')->group(function () {
            Route::get('get-vendors', [VendorMapController::class, 'getVendors']);
            Route::post('update-vendor', [VendorMapController::class, 'updateVendor']);
            Route::post('nearest-vendors', [VendorMapController::class, 'getNearestVendors']);
        });

        // Earnings
        Route::get('/earnings', [DashboardController::class, 'earnings']);

        // Order Management
        Route::get('/order-view', [VendorOrderController::class, 'orderView']);
        Route::post('/order-manage', [VendorOrderController::class, 'orderManage']);

        // Notifications — the vendor half of the notifications table, which
        // previously had no endpoint at all.
        Route::get('/notifications', [VendorNotificationController::class, 'index']);
        Route::get('/notifications/unread-count', [VendorNotificationController::class, 'unreadCount']);
        Route::post('/notifications/mark-read', [VendorNotificationController::class, 'markAsRead']);
        Route::post('/notifications/mark-all-read', [VendorNotificationController::class, 'markAllAsRead']);

        // Boost Management
        Route::prefix('boost')->group(function () {
            Route::get('/packages', [VendorBoostController::class, 'getBoostPackages']);
            Route::get('/positions', [VendorBoostController::class, 'getBoostPositions']);
            Route::get('/pricing', [VendorBoostController::class, 'getBoostPricing']);
            Route::get('/price-preview', [VendorBoostController::class, 'getPricePreview']);
            // Payment happens outside the app; an admin approves in the panel.
            Route::post('/request', [VendorBoostController::class, 'requestBoost']);
            Route::post('/cancel', [VendorBoostController::class, 'cancelBoostPurchase']);
            Route::post('/delete', [VendorBoostController::class, 'deleteBoost']);
            Route::get('/my-boosts', [VendorBoostController::class, 'getMyBoosts']);
            Route::get('/my-boosted-products', [VendorBoostController::class, 'getMyBoostedProducts']);
        });
    });
    // ───────────── User APIs (role_id = 3 only) ─────────────
    Route::prefix('user')->middleware('role:3')->group(function () {
        // Home Page
        Route::get('/search', [HomeController::class, 'search']);
        Route::get('/home', [HomeController::class, 'getHomeScreen']);
        Route::get('/view-more-products', [HomeController::class, 'viewMorePproducts']);
        Route::get('/view-more-company-related-products/{productId?}', [HomeController::class, 'viewMoreConmpanyRelatedProdct']);
        Route::get('/view-more-product-related-company/{companyId?}', [HomeController::class, 'viewMoreProductRelatedCompany']);
        Route::get('/view-more-product-related-vendor/{vendorId?}', [HomeController::class, 'viewMoreProductRelatedVendor']);
        Route::get('/view-more-companies', [HomeController::class, 'viewMoreCompines']);
        Route::get('/view-more-vendors', [HomeController::class, 'viewMoreVendors']);
        Route::get('/get-companies-by-product-category', [HomeController::class, 'getCompaniesByProductCategory']);
        Route::get('/get-companies-product', [HomeController::class, 'getCompanyProducts']);

        // Product Filter
        Route::get('/get-product-details/{id?}', [ProductController::class, 'getProductDetails'])->name('product.details');
        Route::get('/get-all-companies-products-categories', [ProductController::class, 'getAllCompaniesProductCategory']);
        Route::get('/get-all-related-companies-products-categories', [ProductController::class, 'getAllRelatedCompaniesProductCategory']);
        Route::get('/get-all-modal-companies-products-categories', [ProductController::class, 'getModalNumbersByCategoryAndCompany']);
        Route::get('/get-all-product-companies-products-categories', [ProductController::class, 'getProductsByCompanyCategoryModal']);

        // Cart
        Route::post('/add-to-cart', [UserCartController::class, 'addOrUpdateCart']);
        Route::get('/view-cart', [UserCartController::class, 'viewCart']);
        Route::get('/cart_product_details', [UserCartController::class, 'cartProductDetails']);
        Route::delete('/remove-cart-item', [UserCartController::class, 'removeCartItem']);

        // Checkout & Order
        Route::get('/cart-products', [UserCheckoutController::class, 'getAllCartProducts']);
        Route::post('/add-delivery-address', [UserCheckoutController::class, 'addDeliveryAddress']);
        Route::post('/checkout-products', [UserCheckoutController::class, 'checkOutProducts']);
        Route::post('/buy-now-product-details', [UserCheckoutController::class, 'buyNowProductDetails']);
        Route::post('/order-create', [UserOrderController::class, 'orderCreate']);
        Route::get('/order-status', [UserOrderController::class, 'orderStatus']);
        Route::get('/order-history', [UserOrderController::class, 'orderHistory']);

        // Wishlist
        Route::get('/wishlist', [UserWishlistController::class, 'getWishlist']);
        Route::post('/store-wishlist', [UserWishlistController::class, 'storeWishlist']);
        Route::delete('/remove-wishlist', [UserWishlistController::class, 'removeWishlist']);

        // Saved Addresses
        Route::get('/addresses', [UserAddressController::class, 'index']);
        Route::post('/addresses', [UserAddressController::class, 'store']);
        Route::put('/addresses/{id}', [UserAddressController::class, 'update']);
        Route::delete('/addresses/{id}', [UserAddressController::class, 'destroy']);

        // Reviews & ratings
        Route::get('/reviews', [UserReviewController::class, 'index']);
        Route::get('/reviews/pending', [UserReviewController::class, 'pending']);
        Route::post('/reviews', [UserReviewController::class, 'store']);
        Route::put('/reviews/{id}', [UserReviewController::class, 'update']);
        Route::delete('/reviews/{id}', [UserReviewController::class, 'destroy']);

        // Notifications
        Route::get('/notifications', [UserNotificationController::class, 'index']);
        Route::get('/notifications/unread-count', [UserNotificationController::class, 'unreadCount']);
        Route::post('/notifications/mark-read', [UserNotificationController::class, 'markAsRead']);
        Route::post('/notifications/mark-all-read', [UserNotificationController::class, 'markAllAsRead']);

        // Payment — commented out: client requirement is payment handled outside the app
        // Kept for future use if in-app payment is needed
        // Route::post('payment/initiate', [UserPaymentController::class, 'initiatePayment']);
        // Route::post('payment/confirm', [UserPaymentController::class, 'confirmPayment']);
        // Route::post('payment/stripe/cancel', [UserPaymentController::class, 'stripeCancel']);
        // Route::post('payment/stripe/success', [UserPaymentController::class, 'stripeSuccess']);
        // Route::get('payment/paypal/success', [UserPaymentController::class, 'paypalSuccess'])->name('paypal.success');
        // Route::get('payment/paypal/cancel', [UserPaymentController::class, 'paypalCancel'])->name('paypal.cancel');

        // Boosted Products (User View)
        Route::get('/boosted-products/slider', [BoostedProductsController::class, 'getBoostedProductsSlider']);
        Route::get('/boosted-products/all', [BoostedProductsController::class, 'getAllBoostedProducts']);
    });

    // ───────────── Chatify Routes (User & Vendor Chat) ─────────────
    Route::post('/sendMessage', [MessagesController::class, 'send'])->name('api.send.message');
    Route::post('/chat/auth', [MessagesController::class, 'pusherAuth'])->name('api.pusher.auth');
    Route::post('/idInfo', [MessagesController::class, 'idFetchData'])->name('api.idInfo');
    Route::post('/fetchMessages', [MessagesController::class, 'fetch'])->name('api.fetch.messages');
    Route::get('/download/{fileName}', [MessagesController::class, 'download'])->name('api.download');
    Route::post('/makeSeen', [MessagesController::class, 'seen'])->name('api.messages.seen');
    Route::get('/getContacts', [MessagesController::class, 'getContacts'])->name('api.contacts.get');
    Route::post('/star', [MessagesController::class, 'favorite'])->name('api.star');
    Route::post('/favorites', [MessagesController::class, 'getFavorites'])->name('api.favorites');
    Route::get('/search', [MessagesController::class, 'search'])->name('api.search');
    Route::post('/shared', [MessagesController::class, 'sharedPhotos'])->name('api.shared');
    Route::post('/deleteConversation', [MessagesController::class, 'deleteConversation'])->name('api.conversation.delete');
    Route::post('/updateSettings', [MessagesController::class, 'updateSettings'])->name('api.avatar.update');
    Route::post('/setActiveStatus', [MessagesController::class, 'setActiveStatus'])->name('api.activeStatus.set');

    // ───────────── Logout ─────────
    Route::post('/logout', [AuthController::class, 'logout']);

});

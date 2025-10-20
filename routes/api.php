<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Artisan;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use Chatify\Http\Controllers\Api\MessagesController;

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
};

// ────────────────────────────────
// Test Route
// ────────────────────────────────
Route::get('test', function () {
    return response()->json(['status' => 'testings']);
});

Route::get('cache-clear', function() {
    Artisan::call('cache:clear');
    Artisan::call('config:clear');
    Artisan::call('config:cache');
    Artisan::call('view:clear');
    return "Cleared!";
});

// ────────────────────────────────
// Public Auth Routes
// ────────────────────────────────
Route::post('/signup', [AuthController::class, 'signup']);                      //✅ Done
Route::post('/mail_testing', [AuthController::class, 'mailTesting']);           //✅ Done
Route::post('/resend-otp', [AuthController::class, 'resendOtp']);               //✅ Done
Route::post('/otp-verification', [AuthController::class, 'otpVerification']);   //✅ Done
Route::post('/signin', [AuthController::class, 'signin']);                      //✅ Done (token generation pending)
Route::post('/forgot_password', [AuthController::class, 'forgotPassword']);     //✅ Done
Route::get('/vendor_type', [VendorTypeController::class, 'vendorType']);        //⏳ Not Implemented (NI)

// ────────────────────────────────
// Protected Routes (Requires Sanctum Auth)
// ────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {

    // ───────────── Auth & Profile ─────────────
    Route::post('/reset_password', [AuthController::class, 'resetPassword']);       // ✅ Done
    Route::get('/profile', [UserController::class, 'viewProfile']);                 // ✅ Done
    Route::post('/update_profile', [UserController::class, 'updateProfile']);       // ✅ Done

    // ───────────── Vendor APIs ─────────────
    Route::prefix('vendor')->group(function () {

        // Category, Subcategory, Company, Product Category
        Route::get('categories', [VendorCategoryController::class, 'index']);
        Route::get('sub-categories/{categoryId?}', [VendorSubCategoryController::class, 'show']);
        Route::get('companies/{id?}', [VendorCompanyController::class, 'show']);
        Route::get('product-categories/{id?}', [VendorProductCategoryController::class, 'show']);

        // Product Management
        Route::post('/add_product', [VendorProductController::class, 'addProduct']);
        Route::get('/get-all-products/{id?}', [VendorProductController::class, 'getAllProduct'])->name('vendor.store');
        Route::get('/show_product/{id?}', [VendorProductController::class, 'showProduct']);             // ⏳ NI
        Route::post('/update_product/{id?}', [VendorProductController::class, 'updateProduct']);        // ⏳ NI
        Route::delete('/delete_product/{id?}', [VendorProductController::class, 'deleteProduct']);
        Route::get('/get_product_home_screen', [VendorProductController::class, 'get_product_home_screen']);

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

        // Order Management
        Route::get('/order-view', [VendorOrderController::class, 'orderView']);
        Route::post('/order-manage', [VendorOrderController::class, 'order-Manage']);
    });

    // ───────────── User Home APIs ─────────────
    Route::prefix('user')->group(function () {
        // Home Page
        Route::get('/search', [HomeController::class, 'search']);
        Route::get('/home', [HomeController::class, 'getHomeScreen']);
        Route::get('/view-more-prdoucts', [HomeController::class, 'viewMorePproducts']);
        Route::get('/view-more-company-related-proucts/{productId?}', [HomeController::class, 'viewMoreConmpanyRelatedProdct']);
        Route::get('/view-more-product-related-company/{companyId?}', [HomeController::class, 'viewMoreProductRelatedCompany']);
        Route::get('/view-more-product-related-vendor/{vendorId?}', [HomeController::class, 'viewMoreProductRelatedVendor']);
        Route::get('/view-more-companies', [HomeController::class, 'viewMoreCompines']);
        Route::get('/view-more-vendors', [HomeController::class, 'viewMoreVendors']);
        Route::get('/get-companies-by-product-categtegory', [HomeController::class, 'getCompaniesByProductCategory']);
        Route::get('/get-companies-product', [HomeController::class, 'getCompanyProducts']);

        // Product Filter
        Route::get('/get-product-details/{id?}', [ProductController::class, 'getProductDetails'])->name('product.details');
        Route::get('/get-all-companies-products-categoris', [ProductController::class, 'getAllCompaniesProductCategory']);
        Route::get('/get-all-related-companies-products-categoris', [ProductController::class, 'getAllRelatedCompaniesProductCategory']);
        Route::get('/get-all-modal-companies-products-categoris', [ProductController::class, 'getModalNumbersByCategoryAndCompany']);
        Route::get('/get-all-product-companies-products-categoris', [ProductController::class, 'getProductsByCompanyCategoryModal']);

        // Cart
        Route::post('/add-to-cart', [UserCartController::class, 'addOrUpdateCart']);
        Route::get('/view-cart', [UserCartController::class, 'viewCart']);
        Route::get('/cart_product_details', [UserCartController::class, 'cartProductDetails']);

        // Checkout & Order
        Route::post('/add-delivery-addresss', [UserCheckoutController::class, 'addDeliveryAddress']);
        Route::get('/checkout_out_products', [UserCheckoutController::class, 'checkOutProducts']);
        Route::post('/buy_now_product_details', [UserCheckoutController::class, 'buyNowProductDeftails']);
        Route::post('/order-create', [UserOrderController::class, 'orderCreate']);
        Route::get('/order-status', [UserOrderController::class, 'orderStatus']);

        // Wishlist
        Route::post('/store-wishlist', [UserWishlistController::class, 'storeWishlist']);

        // Payment
        Route::post('payment/initiate', [UserPaymentController::class, 'initiatePayment']);
        Route::post('payment/confirm', [UserPaymentController::class, 'confirmPayment']);
        Route::post('payment/stripe/cancel', [UserPaymentController::class, 'stripeCancel']);
        Route::post('payment/stripe/success', [UserPaymentController::class, 'stripeSuccess']);
        Route::get('payment/paypal/success', [UserPaymentController::class, 'paypalSuccess'])->name('paypal.success');
        Route::get('payment/paypal/cancel', [UserPaymentController::class, 'paypalCancel'])->name('paypal.cancel');

    });

    // ───────────── Chatify Routes ─────────────
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
    Route::post('/logout', [AuthController::class, 'logout']); // ✅ Done

});

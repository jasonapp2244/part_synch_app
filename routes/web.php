<?php
use App\Http\Controllers\Admin\{
    AuthController,
    DashboardController,
    VendorController,
    UserController,
    FeaturedController,
    CategoryController,
    SubCategoryController,
    CompanyController,
    ProductCategoryController,
    ProductController,
    EarningController,
    ExportController,
    SettingsController,
    LegalPageController,
    ProfileController,
    BoostPackageController,
    BoostRequestController,
    ReportController,
    ReviewController
};

use App\Http\Controllers\PublicPageController;
use Illuminate\Support\Facades\Route;


Route::get('/test', function () {
    return "Route is working!";
});

/*
|--------------------------------------------------------------------------
| Public pages (no sign-in): the URLs the App Store and Play Store listings
| link to. Their text is edited in the admin panel under Legal & Support.
|--------------------------------------------------------------------------
*/
Route::get('/legal/privacy-policy', [PublicPageController::class, 'privacyPolicy'])->name('legal.privacy');
Route::get('/legal/terms', [PublicPageController::class, 'terms'])->name('legal.terms');
Route::get('/support', [PublicPageController::class, 'support'])->name('support.page');
Route::get('/account/delete', [PublicPageController::class, 'accountDeletion'])->name('account.delete.info');

/*
|--------------------------------------------------------------------------
| Admin Authentication (guest)
|--------------------------------------------------------------------------
*/
Route::get('/login', [AuthController::class, 'showLogin'])->name('admin.login');
Route::post('/login', [AuthController::class, 'login'])->name('admin.login.submit');
Route::post('/logout', [AuthController::class, 'logout'])->name('admin.logout');

/*
|--------------------------------------------------------------------------
| Admin Panel (protected: authenticated admins only)
|--------------------------------------------------------------------------
*/
Route::middleware('admin')->group(function () {

    Route::get('/dashboard', [DashboardController::class, 'dashboard'])->name('admin.dashboard');

    // Vendor Management
    Route::get('/vendor-records', [VendorController::class, 'VendorRecords'])->name('vendor.records');
    Route::post('/vendor/{id}/toggle-status', [VendorController::class, 'toggleStatus'])->name('vendor.toggle.status');
    Route::post('/vendor/{id}/toggle-approval', [VendorController::class, 'toggleApproval'])->name('vendor.toggle.approval');
    Route::delete('/vendor/{id}', [VendorController::class, 'destroy'])->name('vendor.destroy');

    // User Management
    Route::get('/user-records', [UserController::class, 'UserRecords'])->name('user.records');
    Route::post('/user/{id}/toggle-status', [UserController::class, 'toggleStatus'])->name('user.toggle.status');
    Route::delete('/user/{id}', [UserController::class, 'destroy'])->name('user.destroy');

    // Featured
    Route::get('/featured-records', [FeaturedController::class, 'FeaturedRecords'])->name('featured.records');
    Route::post('/featured/{id}/toggle', [FeaturedController::class, 'toggleFeatured'])->name('featured.toggle');

    // Category CRUD
    Route::get('/category-records', [CategoryController::class, 'CategoryRecords'])->name('category.records');
    Route::post('/category', [CategoryController::class, 'store'])->name('category.store');
    Route::put('/category/{id}', [CategoryController::class, 'update'])->name('category.update');
    Route::delete('/category/{id}', [CategoryController::class, 'destroy'])->name('category.destroy');

    // Sub Category CRUD
    Route::get('/sub-category-records', [SubCategoryController::class, 'SubCategoryRecords'])->name('sub.category.records');
    Route::post('/sub-category', [SubCategoryController::class, 'store'])->name('sub.category.store');
    Route::put('/sub-category/{id}', [SubCategoryController::class, 'update'])->name('sub.category.update');
    Route::post('/sub-category/{id}/toggle-status', [SubCategoryController::class, 'toggleStatus'])->name('sub.category.toggle.status');
    Route::delete('/sub-category/{id}', [SubCategoryController::class, 'destroy'])->name('sub.category.destroy');

    // Company CRUD
    Route::get('/company-records', [CompanyController::class, 'CompanyRecords'])->name('company.records');
    Route::post('/company', [CompanyController::class, 'store'])->name('company.store');
    Route::put('/company/{id}', [CompanyController::class, 'update'])->name('company.update');
    Route::post('/company/{id}/toggle-status', [CompanyController::class, 'toggleStatus'])->name('company.toggle.status');
    Route::delete('/company/{id}', [CompanyController::class, 'destroy'])->name('company.destroy');

    // Product Category CRUD (per company; vendors pick one when adding a product)
    Route::get('/product-category-records', [ProductCategoryController::class, 'index'])->name('product.category.records');
    Route::post('/product-category', [ProductCategoryController::class, 'store'])->name('product.category.store');
    Route::put('/product-category/{id}', [ProductCategoryController::class, 'update'])->name('product.category.update');
    Route::post('/product-category/{id}/toggle-status', [ProductCategoryController::class, 'toggleStatus'])->name('product.category.toggle.status');
    Route::delete('/product-category/{id}', [ProductCategoryController::class, 'destroy'])->name('product.category.destroy');

    // Product Management
    Route::get('/product-records', [ProductController::class, 'ProductRecords'])->name('product.records');
    Route::post('/product/{id}/toggle-status', [ProductController::class, 'toggleStatus'])->name('product.toggle.status');
    Route::delete('/product/{id}', [ProductController::class, 'destroy'])->name('product.destroy');

    // Order Management
    Route::get('/earning-records', [EarningController::class, 'EarningRecords'])->name('earning.records');
    Route::get('/order/{id}', [EarningController::class, 'orderDetails'])->name('order.details');
    Route::get('/order/{id}/invoice', [EarningController::class, 'invoice'])->name('order.invoice');
    Route::post('/order/{id}/update-status', [EarningController::class, 'updateOrderStatus'])->name('order.update.status');

    // Boost Package Management
    Route::get('/boost-packages', [BoostPackageController::class, 'list'])->name('boost.packages');
    Route::post('/boost-package', [BoostPackageController::class, 'store'])->name('boost.package.store');
    Route::put('/boost-package/{id}', [BoostPackageController::class, 'update'])->name('boost.package.update');
    Route::post('/boost-package/{id}/toggle-status', [BoostPackageController::class, 'toggleStatus'])->name('boost.package.toggle.status');
    Route::delete('/boost-package/{id}', [BoostPackageController::class, 'destroy'])->name('boost.package.destroy');
    Route::put('/boost-position/{id}', [BoostPackageController::class, 'updatePosition'])->name('boost.position.update');

    // Boost requests: vendors request in the app, pay outside it, admin approves
    Route::get('/boost-requests', [BoostRequestController::class, 'index'])->name('admin.boosts');
    Route::post('/boost-requests', [BoostRequestController::class, 'store'])->name('admin.boosts.store');
    Route::get('/boost-requests/vendor/{vendorId}/products', [BoostRequestController::class, 'vendorProducts'])->whereNumber('vendorId')->name('admin.boosts.vendor.products');
    Route::post('/boost-requests/{id}/approve', [BoostRequestController::class, 'approve'])->whereNumber('id')->name('admin.boosts.approve');
    Route::post('/boost-requests/{id}/reject', [BoostRequestController::class, 'reject'])->whereNumber('id')->name('admin.boosts.reject');
    Route::post('/boost-requests/{id}/cancel', [BoostRequestController::class, 'cancel'])->whereNumber('id')->name('admin.boosts.cancel');

    // Moderation — reported content and product reviews.
    // The reports queue is what App Store review looks for as proof that
    // reported user generated content is acted on.
    Route::get('/reports', [ReportController::class, 'index'])->name('admin.reports');
    Route::put('/report/{id}', [ReportController::class, 'update'])->name('admin.reports.update');
    Route::post('/report/{id}/take-down', [ReportController::class, 'takeDown'])->name('admin.reports.takedown');

    Route::get('/reviews', [ReviewController::class, 'index'])->name('admin.reviews');
    Route::post('/review/{id}/status', [ReviewController::class, 'updateStatus'])->name('admin.reviews.status');
    Route::delete('/review/{id}', [ReviewController::class, 'destroy'])->name('admin.reviews.destroy');

    // PDF / Excel download of any admin list (keys in App\Support\AdminExports)
    Route::get('/export/{list}.{format}', [ExportController::class, 'download'])
        ->where(['list' => '[a-z-]+', 'format' => 'pdf|xlsx'])
        ->name('admin.export');

    // Settings
    Route::get('/settings', [SettingsController::class, 'index'])->name('admin.settings');
    Route::post('/settings', [SettingsController::class, 'update'])->name('admin.settings.update');

    // Legal & Support: privacy policy, terms, support and account-deletion pages
    Route::get('/legal-support', [LegalPageController::class, 'index'])->name('admin.legal');
    Route::post('/legal-support', [LegalPageController::class, 'update'])->name('admin.legal.update');

    // The signed-in admin's own account: profile details and password
    Route::get('/profile', [ProfileController::class, 'show'])->name('admin.profile');
    Route::post('/profile', [ProfileController::class, 'update'])->name('admin.profile.update');
    Route::post('/profile/password', [ProfileController::class, 'updatePassword'])->name('admin.profile.password');
    Route::post('/profile/photo', [ProfileController::class, 'updatePhoto'])->name('admin.profile.photo');
    Route::delete('/profile/photo', [ProfileController::class, 'destroyPhoto'])->name('admin.profile.photo.destroy');

});

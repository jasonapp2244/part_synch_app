<?php
use App\Http\Controllers\Admin\{
    DashboardController,
    VendorController,
    UserController,
    FeaturedController,
    CategoryController,
    SubCategoryController

};

use Illuminate\Support\Facades\Route;
use SebastianBergmann\CodeCoverage\Report\Html\Dashboard;


Route::get('/test', function () {
    return "Route is working!";
});

Route::get('/dashboard', [DashboardController::class, 'dashboard'])->name('admin.dashboard');


Route::get('/vendor-records', [VendorController::class, 'VendorRecords'])->name('vendor.records');


Route::get('/user-records', [UserController::class, 'UserRecords'])->name('user.records');


Route::get('a/featured-records', [FeaturedController::class, 'FeaturedRecords'])->name('featured.records');


Route::get('/category-records', [CategoryController::class, 'CategoryRecords'])->name('category.records');


Route::get('sub-category-records', [SubCategoryController::class, 'SubCategoryRecords'])->name('sub.category.records');

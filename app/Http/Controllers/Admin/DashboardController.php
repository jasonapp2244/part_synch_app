<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Company;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\SubCategory;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function dashboard()
    {
        $totalOrders = Order::count();
        $totalRevenue = OrderItem::earned()->sum('total_price');
        $totalVendors = User::where('role_id', 2)->count();
        $activeVendors = User::where('role_id', 2)->where('status', 'active')->count();
        $totalCustomers = User::where('role_id', 3)->count();
        $activeCustomers = User::where('role_id', 3)->where('status', 'active')->count();
        $totalProducts = Product::count();
        $totalCategories = Category::count();
        $totalCompanies = Company::count();

        // Order status breakdown for pie chart
        $pendingOrders = Order::where('order_status', 'pending')->count();
        $paymentOrders = Order::where('order_status', 'payment')->count();
        $deliveredOrders = Order::whereIn('order_status', ['completed', 'delivered'])->count();
        $cancelledOrders = Order::where('order_status', 'cancel')->count();

        // Monthly revenue for bar chart (last 6 months)
        $monthlyRevenue = OrderItem::earned()
            ->select(
                DB::raw('YEAR(created_at) as year'),
                DB::raw('MONTH(created_at) as month'),
                DB::raw('SUM(total_price) as revenue')
            )
            ->where('created_at', '>=', now()->subMonths(6))
            ->groupBy(DB::raw('YEAR(created_at)'), DB::raw('MONTH(created_at)'))
            ->orderBy('year')
            ->orderBy('month')
            ->get();

        $chartLabels = $monthlyRevenue->map(fn($item) => date('M', mktime(0, 0, 0, $item->month, 1)));
        $chartData = $monthlyRevenue->map(fn($item) => round($item->revenue, 2));

        // Top 5 vendors by order count
        $topVendors = User::where('role_id', 2)
            ->withCount('products')
            ->orderByDesc('products_count')
            ->take(5)
            ->get();

        $recentOrders = Order::with(['orderItems.product'])
            ->latest()
            ->take(8)
            ->get();

        return view('admin.dashboard', compact(
            'totalOrders', 'totalRevenue', 'totalVendors', 'activeVendors',
            'totalCustomers', 'activeCustomers', 'totalProducts', 'recentOrders',
            'totalCategories', 'totalCompanies',
            'pendingOrders', 'paymentOrders', 'deliveredOrders', 'cancelledOrders',
            'chartLabels', 'chartData', 'topVendors'
        ));
    }
}

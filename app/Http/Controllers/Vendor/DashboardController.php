<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function dashboard()
    {
        $vendorId = Auth::id();

        $totalProducts = Product::where('user_id', $vendorId)->count();
        $activeProducts = Product::where('user_id', $vendorId)->where('status', 'active')->count();
        $outOfStockProducts = Product::where('user_id', $vendorId)->where('stock_quantity', '<=', 0)->count();

        $totalOrders = Order::where('vendor_id', $vendorId)->count();
        // Every order still to be finished, not only ones nobody has touched:
        // an accepted but unpaid order used to show as 0 pending (L17).
        $pendingOrders = Order::where('vendor_id', $vendorId)
            ->whereNotIn('order_status', ['completed', 'delivered', 'cancel'])
            ->count();
        $completedOrders = Order::where('vendor_id', $vendorId)->whereIn('order_status', ['completed', 'delivered'])->count();

        $totalRevenue = (float) OrderItem::earned($vendorId)->sum('total_price');

        $recentOrders = Order::with(['orderItems.product'])
            ->where('vendor_id', $vendorId)
            ->latest()
            ->take(10)
            ->get()
            ->map(function ($order) {
                return [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'items_count' => $order->orderItems->count(),
                    'total' => $order->orderItems->sum('total_price'),
                    'status' => $order->order_status,
                    'date' => $order->created_at?->format('d M Y'),
                ];
            });

        return response()->json([
            'status' => true,
            'message' => 'Vendor dashboard data',
            'data' => [
                'products' => [
                    'total' => $totalProducts,
                    'active' => $activeProducts,
                    'out_of_stock' => $outOfStockProducts,
                ],
                'orders' => [
                    'total' => $totalOrders,
                    'pending' => $pendingOrders,
                    'completed' => $completedOrders,
                ],
                'revenue' => [
                    'total' => round($totalRevenue, 2),
                ],
                'recent_orders' => $recentOrders,
            ]
        ]);
    }

    public function earnings()
    {
        $vendorId = Auth::id();

        $totalRevenue = (float) OrderItem::earned($vendorId)->sum('total_price');

        $monthlyRevenue = OrderItem::earned($vendorId)
        ->selectRaw('YEAR(created_at) as year, MONTH(created_at) as month, SUM(total_price) as revenue, SUM(quantity) as items_sold')
        ->groupByRaw('YEAR(created_at), MONTH(created_at)')
        ->orderByRaw('YEAR(created_at) DESC, MONTH(created_at) DESC')
        ->take(12)
        ->get();

        $topProducts = DB::table('order_items as oi')
            ->join('orders as o', 'oi.order_id', '=', 'o.id')
            ->join('products as p', 'oi.product_id', '=', 'p.id')
            ->where('o.vendor_id', $vendorId)
            ->where('o.order_status', '!=', 'cancel')
            ->select('p.id', 'p.name', DB::raw('SUM(oi.quantity) as total_sold'), DB::raw('SUM(oi.total_price) as total_revenue'))
            ->groupBy('p.id', 'p.name')
            ->orderByDesc('total_revenue')
            ->take(10)
            ->get();

        return response()->json([
            'status' => true,
            'message' => 'Vendor earnings data',
            'data' => [
                'total_revenue' => round($totalRevenue, 2),
                'monthly_revenue' => $monthlyRevenue,
                'top_products' => $topProducts,
            ]
        ]);
    }
}

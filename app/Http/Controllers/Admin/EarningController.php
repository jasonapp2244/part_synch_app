<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Setting;
use App\Models\User;
use App\Support\OrderTotals;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class EarningController extends Controller
{
    public function EarningRecords()
    {
        $orders = Order::with(['orderItems', 'orderItems.product'])->latest()->get();

        $totalOrders = $orders->count();
        $totalRevenue = OrderItem::earned()->sum('total_price');
        $pendingOrders = $orders->where('order_status', 'pending')->count();
        $completedOrders = $orders->whereIn('order_status', ['delivered', 'payment', 'completed'])->count();

        return view('admin.view_earning_records', compact(
            'orders', 'totalOrders', 'totalRevenue', 'pendingOrders', 'completedOrders'
        ));
    }

    public function orderDetails($id)
    {
        $order = Order::with([
            'orderItems.product.productImages',
            'deliveryAddress'
        ])->findOrFail($id);

        $customer = User::find($order->user_id);
        $vendor = User::find($order->vendor_id);

        $totals = OrderTotals::of($order);

        return view('admin.view_order_details', compact('order', 'customer', 'vendor', 'totals'));
    }

    public function invoice($id)
    {
        $order = Order::with(['orderItems.product', 'deliveryAddress'])->findOrFail($id);

        return Pdf::loadView('admin.pdf.invoice', [
            'order' => $order,
            'customer' => User::find($order->user_id),
            'vendor' => User::find($order->vendor_id),
            'totals' => OrderTotals::of($order),
            'currency' => Setting::get('currency_symbol', '$'),
            'siteName' => Setting::get('site_name', config('app.name')),
            'siteEmail' => Setting::get('site_email'),
            'sitePhone' => Setting::get('site_phone'),
        ])->setOption('isFontSubsettingEnabled', true)
            ->download('invoice-' . Str::slug($order->order_number ?: (string) $order->id) . '.pdf');
    }

    public function updateOrderStatus(Request $request, $id)
    {
        $request->validate([
            'order_status' => ['required', Rule::in(['pending', 'accept', 'payment', 'order_packed', 'shipping', 'completed', 'delivered', 'cancel'])],
        ]);

        $order = Order::findOrFail($id);
        $order->order_status = $request->order_status;

        if ($request->order_status === 'delivered') {
            $order->delivery_date = now();
        }

        $order->save();

        return redirect()->route('order.details', $id)->with('success', 'Order status updated successfully.');
    }
}

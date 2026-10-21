@php
    $money = fn ($value) => $currency . number_format((float) $value, 2);
    $person = fn ($user) => $user ? (trim($user->first_name . ' ' . $user->last_name) ?: $user->email) : null;
    $address = $order->deliveryAddress;
    $logoPath = public_path('admin/images/favicon-32x32.png');
    $logo = is_file($logoPath) ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath)) : null;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $order->order_number }}</title>
    <style>
        @page { margin: 28px 34px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #222; }
        table { width: 100%; border-collapse: collapse; }
        .muted { color: #6b7280; }
        .header td { vertical-align: top; }
        .header h1 { margin: 0; font-size: 24px; letter-spacing: 1px; color: #111827; }
        .meta td { padding: 2px 0; }
        .meta td:first-child { color: #6b7280; width: 90px; }
        .parties { margin-top: 22px; }
        .parties td { vertical-align: top; width: 33%; padding-right: 12px; }
        .label { font-size: 9px; text-transform: uppercase; letter-spacing: 1px; color: #6b7280; margin-bottom: 4px; }
        .items { margin-top: 22px; }
        .items th { background: #111827; color: #fff; text-align: left; padding: 7px 8px; font-size: 10px; }
        .items td { padding: 7px 8px; border-bottom: 1px solid #e5e7eb; }
        .items .num { text-align: right; white-space: nowrap; }
        .totals { width: 45%; margin-left: 55%; margin-top: 12px; }
        .totals td { padding: 4px 8px; }
        .totals td:last-child { text-align: right; }
        .totals .grand td { border-top: 2px solid #111827; font-weight: bold; font-size: 13px; padding-top: 8px; }
        .status { display: inline-block; padding: 2px 8px; border-radius: 8px; background: #eef2ff; color: #3730a3; font-size: 10px; }
        .footer { margin-top: 36px; text-align: center; font-size: 9px; color: #9ca3af; }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td>
                @if($logo)<img src="{{ $logo }}" alt="" style="height: 56px; margin-bottom: 6px;"><br>@endif
                <strong style="font-size: 18px;">{{ $siteName }}</strong><br>
                @if($siteEmail)<span class="muted">{{ $siteEmail }}</span><br>@endif
                @if($sitePhone)<span class="muted">{{ $sitePhone }}</span>@endif
            </td>
            <td style="text-align: right;">
                <h1>INVOICE</h1>
                <table class="meta" style="width: auto; margin-left: auto; margin-top: 6px;">
                    <tr><td>Invoice no.</td><td><strong>{{ $order->order_number ?? $order->id }}</strong></td></tr>
                    <tr><td>Order date</td><td>{{ $order->created_at ? $order->created_at->format('d M Y') : 'N/A' }}</td></tr>
                    <tr><td>Payment</td><td>{{ $order->paymentMethodLabel() }}</td></tr>
                    <tr><td>Status</td><td><span class="status">{{ ucfirst(str_replace('_', ' ', (string) $order->order_status)) }}</span></td></tr>
                    @if($order->delivery_date)
                        <tr><td>Delivered</td><td>{{ \Carbon\Carbon::parse($order->delivery_date)->format('d M Y') }}</td></tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>

    <table class="parties">
        <tr>
            <td>
                <div class="label">Bill to</div>
                @if($customer)
                    <strong>{{ $person($customer) }}</strong><br>
                    {{ $customer->email }}<br>
                    @if($customer->phone_number){{ $customer->phone_number }}@endif
                @else
                    <span class="muted">Customer account deleted</span>
                @endif
            </td>
            <td>
                <div class="label">Ship to</div>
                @if($address)
                    <strong>{{ $address->full_name }}</strong><br>
                    {{ $address->address_line1 }}@if($address->address_line2), {{ $address->address_line2 }}@endif<br>
                    {{ collect([$address->city, $address->state, $address->postal_code])->filter()->implode(', ') }}<br>
                    {{ $address->country }}
                    @if($address->phone_number)<br>{{ $address->phone_number }}@endif
                @else
                    <span class="muted">No delivery address</span>
                @endif
            </td>
            <td>
                <div class="label">Sold by</div>
                @if($vendor)
                    <strong>{{ $vendor->business_name ?: $person($vendor) }}</strong><br>
                    {{ $vendor->email }}<br>
                    @if($vendor->phone_number){{ $vendor->phone_number }}@endif
                @else
                    <span class="muted">Vendor account deleted</span>
                @endif
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th style="width: 28px;">#</th>
                <th>Product</th>
                <th class="num">Qty</th>
                <th class="num">Unit price</th>
                <th class="num">Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse($order->orderItems as $index => $item)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $item->product->name ?? 'Product deleted' }}</td>
                    <td class="num">{{ $item->quantity }}</td>
                    <td class="num">{{ $money($item->price) }}</td>
                    <td class="num">{{ $money($item->total_price) }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="muted" style="text-align: center;">No items on this order.</td></tr>
            @endforelse
        </tbody>
    </table>

    <table class="totals">
        <tr><td>Subtotal</td><td>{{ $money($totals['subtotal']) }}</td></tr>
        @if($totals['shipping'] > 0)
            <tr><td>Shipping</td><td>{{ $money($totals['shipping']) }}</td></tr>
        @endif
        @if($totals['discount'] > 0)
            <tr><td>Discount</td><td>-{{ $money($totals['discount']) }}</td></tr>
        @endif
        @if($totals['tax'] > 0)
            <tr><td>Tax</td><td>{{ $money($totals['tax']) }}</td></tr>
        @endif
        <tr class="grand"><td>Grand total</td><td>{{ $money($totals['grand_total']) }}</td></tr>
    </table>

    <div class="footer">
        Thank you for your order. &middot; {{ $siteName }} &middot; Generated {{ now()->format('d M Y H:i') }}
    </div>
</body>
</html>

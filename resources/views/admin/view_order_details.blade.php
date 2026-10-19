@extends('layouts.admin')
@section('title', 'Order Details')

@section('content')
    <div class="page-wrapper">
        <div class="page-content">
            <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
                <div class="breadcrumb-title pe-3">Order</div>
                <div class="ps-3">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0 p-0">
                            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}"><i class="bx bx-home-alt"></i></a></li>
                            <li class="breadcrumb-item"><a href="{{ route('earning.records') }}">Orders</a></li>
                            <li class="breadcrumb-item active" aria-current="page">{{ $order->order_number }}</li>
                        </ol>
                    </nav>
                </div>
                <div class="ms-auto d-flex gap-2">
                    <a href="{{ route('order.invoice', $order->id) }}" class="btn btn-danger">
                        <i class="bx bxs-file-pdf"></i> Download Invoice
                    </a>
                    <a href="{{ route('earning.records') }}" class="btn btn-secondary">
                        <i class="bx bx-arrow-back"></i> Back to Orders
                    </a>
                </div>
            </div>

            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            <div class="row">
                <!-- Order Info -->
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0">Order {{ $order->order_number }}</h6>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <strong>Order Date:</strong><br>
                                    {{ $order->created_at ? $order->created_at->format('d M Y H:i') : 'N/A' }}
                                </div>
                                <div class="col-md-4">
                                    <strong>Payment Method:</strong><br>
                                    {{ ucfirst($order->payment_method ?? 'N/A') }}
                                </div>
                                <div class="col-md-4">
                                    <strong>Delivery Date:</strong><br>
                                    {{ $order->delivery_date ? \Carbon\Carbon::parse($order->delivery_date)->format('d M Y') : 'Not delivered' }}
                                </div>
                            </div>

                            <h6 class="mt-4">Order Items</h6>
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <thead class="table-light">
                                        <tr>
                                            <th>#</th>
                                            <th>Product</th>
                                            <th>Qty</th>
                                            <th>Price</th>
                                            <th>Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($order->orderItems as $index => $item)
                                        <tr>
                                            <td>{{ $index + 1 }}</td>
                                            <td>
                                                @if($item->product)
                                                    {{ $item->product->name }}
                                                    @if($item->product->productImages && $item->product->productImages->first())
                                                        <br><img src="{{ $item->product->productImages->first()->url() }}" width="50" class="rounded mt-1" onerror="this.remove()">
                                                    @endif
                                                @else
                                                    <span class="text-muted">Product deleted</span>
                                                @endif
                                            </td>
                                            <td>{{ $item->quantity }}</td>
                                            <td>${{ number_format($item->price, 2) }}</td>
                                            <td>${{ number_format($item->total_price, 2) }}</td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <td colspan="4" class="text-end"><strong>Subtotal</strong></td>
                                            <td><strong>${{ number_format($totals['subtotal'], 2) }}</strong></td>
                                        </tr>
                                        @if($totals['shipping'] > 0)
                                        <tr>
                                            <td colspan="4" class="text-end">Shipping</td>
                                            <td>${{ number_format($totals['shipping'], 2) }}</td>
                                        </tr>
                                        @endif
                                        @if($totals['discount'] > 0)
                                        <tr>
                                            <td colspan="4" class="text-end">Discount</td>
                                            <td>-${{ number_format($totals['discount'], 2) }}</td>
                                        </tr>
                                        @endif
                                        @if($totals['tax'] > 0)
                                        <tr>
                                            <td colspan="4" class="text-end">Tax</td>
                                            <td>${{ number_format($totals['tax'], 2) }}</td>
                                        </tr>
                                        @endif
                                        <tr>
                                            <td colspan="4" class="text-end"><strong>Grand Total</strong></td>
                                            <td><strong>${{ number_format($totals['grand_total'], 2) }}</strong></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sidebar -->
                <div class="col-md-4">
                    <!-- Status Update -->
                    <div class="card mb-3">
                        <div class="card-header">
                            <h6 class="mb-0">Order Status</h6>
                        </div>
                        <div class="card-body">
                            @php
                                $statusColors = [
                                    'pending' => 'bg-warning text-dark',
                                    'accept' => 'bg-primary',
                                    'payment' => 'bg-info',
                                    'order_packed' => 'bg-info',
                                    'shipping' => 'bg-primary',
                                    'completed' => 'bg-success',
                                    'delivered' => 'bg-success',
                                    'cancel' => 'bg-danger',
                                ];
                                $color = $statusColors[$order->order_status] ?? 'bg-secondary';
                            @endphp
                            <p>Current: <span class="badge {{ $color }} fs-6">{{ ucfirst(str_replace('_', ' ', $order->order_status)) }}</span></p>

                            <form action="{{ route('order.update.status', $order->id) }}" method="POST">
                                @csrf
                                <div class="mb-3">
                                    <label class="form-label">Update Status</label>
                                    <select class="form-select" name="order_status" required>
                                        @foreach(['pending', 'accept', 'payment', 'order_packed', 'shipping', 'completed', 'delivered', 'cancel'] as $status)
                                            <option value="{{ $status }}" {{ $order->order_status === $status ? 'selected' : '' }}>
                                                {{ ucfirst(str_replace('_', ' ', $status)) }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-primary w-100">Update Status</button>
                            </form>
                        </div>
                    </div>

                    <!-- Customer Info -->
                    <div class="card mb-3">
                        <div class="card-header">
                            <h6 class="mb-0">Customer</h6>
                        </div>
                        <div class="card-body">
                            @if($customer)
                                <p><strong>{{ $customer->first_name }} {{ $customer->last_name }}</strong></p>
                                <p><i class="bx bx-envelope"></i> {{ $customer->email ?? 'N/A' }}</p>
                                <p><i class="bx bx-phone"></i> {{ $customer->phone_number ?? 'N/A' }}</p>
                            @else
                                <p class="text-muted">Customer not found</p>
                            @endif
                        </div>
                    </div>

                    <!-- Vendor Info -->
                    <div class="card mb-3">
                        <div class="card-header">
                            <h6 class="mb-0">Vendor</h6>
                        </div>
                        <div class="card-body">
                            @if($vendor)
                                <p><strong>{{ $vendor->first_name }} {{ $vendor->last_name }}</strong></p>
                                <p><i class="bx bx-envelope"></i> {{ $vendor->email ?? 'N/A' }}</p>
                                <p><i class="bx bx-store-alt"></i> {{ $vendor->business_type ?? 'N/A' }}</p>
                            @else
                                <p class="text-muted">Vendor not found</p>
                            @endif
                        </div>
                    </div>

                    <!-- Delivery Address -->
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0">Delivery Address</h6>
                        </div>
                        <div class="card-body">
                            @if($order->deliveryAddress)
                                <p><strong>{{ $order->deliveryAddress->full_name }}</strong></p>
                                <p>{{ $order->deliveryAddress->address_line1 }}</p>
                                @if($order->deliveryAddress->address_line2)
                                    <p>{{ $order->deliveryAddress->address_line2 }}</p>
                                @endif
                                <p>{{ $order->deliveryAddress->city }}, {{ $order->deliveryAddress->state }}</p>
                                <p>{{ $order->deliveryAddress->country }} - {{ $order->deliveryAddress->postal_code }}</p>
                                <p><i class="bx bx-phone"></i> {{ $order->deliveryAddress->phone_number }}</p>
                            @else
                                <p class="text-muted">No address found</p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

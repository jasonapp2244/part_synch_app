@extends('layouts.admin')
@section('title', 'Earnings')

@section('content')
    <div class="page-wrapper">
        <div class="page-content">
            <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
                <div class="breadcrumb-title pe-3">Finance</div>
                <div class="ps-3">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0 p-0">
                            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}"><i class="bx bx-home-alt"></i></a></li>
                            <li class="breadcrumb-item active" aria-current="page">Earnings</li>
                        </ol>
                    </nav>
                </div>
            </div>

            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            <!-- Summary Cards -->
            <div class="row row-cols-1 row-cols-md-2 row-cols-xl-4 mb-2">
                <div class="col">
                    <div class="card radius-10 overflow-hidden">
                        <div class="card-body p-0">
                            <div class="bg-gradient-deepblue p-4">
                                <div class="d-flex align-items-center">
                                    <div>
                                        <p class="mb-1 text-white" style="opacity:0.85">Total Orders</p>
                                        <h3 class="mb-0 text-white">{{ number_format($totalOrders) }}</h3>
                                    </div>
                                    <div class="ms-auto"><i class='bx bxs-cart font-30 text-white'></i></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col">
                    <div class="card radius-10 overflow-hidden">
                        <div class="card-body p-0">
                            <div class="bg-gradient-ohhappiness p-4">
                                <div class="d-flex align-items-center">
                                    <div>
                                        <p class="mb-1 text-white" style="opacity:0.85">Total Revenue</p>
                                        <h3 class="mb-0 text-white">${{ number_format($totalRevenue, 2) }}</h3>
                                    </div>
                                    <div class="ms-auto"><i class='bx bx-dollar-circle font-30 text-white'></i></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col">
                    <div class="card radius-10 overflow-hidden">
                        <div class="card-body p-0">
                            <div class="bg-gradient-blooker p-4">
                                <div class="d-flex align-items-center">
                                    <div>
                                        <p class="mb-1 text-white" style="opacity:0.85">Pending Orders</p>
                                        <h3 class="mb-0 text-white">{{ number_format($pendingOrders) }}</h3>
                                    </div>
                                    <div class="ms-auto"><i class='bx bx-time font-30 text-white'></i></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col">
                    <div class="card radius-10 overflow-hidden">
                        <div class="card-body p-0">
                            <div class="bg-gradient-quepal p-4">
                                <div class="d-flex align-items-center">
                                    <div>
                                        <p class="mb-1 text-white" style="opacity:0.85">Completed</p>
                                        <h3 class="mb-0 text-white">{{ number_format($completedOrders) }}</h3>
                                    </div>
                                    <div class="ms-auto"><i class='bx bx-check-circle font-30 text-white'></i></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Orders Table -->
            <div class="card radius-10 overflow-hidden">
                <div class="card-header bg-gradient-burning p-3">
                    <div class="d-flex align-items-center">
                        <div>
                            <h6 class="mb-0 text-white"><i class="bx bx-receipt me-2"></i>All Orders</h6>
                        </div>
                        <div class="ms-auto">
                            @include('admin.partials.export_buttons', ['list' => 'orders', 'onDark' => true])
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="example2" class="table table-striped table-bordered">
                            <thead>
                                <tr>
                                    <th>S.no</th>
                                    <th>Order #</th>
                                    <th>Items</th>
                                    <th>Total</th>
                                    <th>Payment Method</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($orders as $index => $order)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td><span class="fw-bold">{{ $order->order_number ?? 'N/A' }}</span></td>
                                    <td>{{ $order->orderItems->count() }}</td>
                                    <td><span class="fw-bold">${{ number_format($order->orderItems->sum('total_price'), 2) }}</span></td>
                                    <td>{{ $order->paymentMethodLabel() }}</td>
                                    <td>
                                        @php
                                            $statusColors = [
                                                'delivered'    => 'bg-gradient-quepal',
                                                'completed'    => 'bg-gradient-quepal',
                                                'pending'      => 'bg-gradient-blooker',
                                                'payment'      => 'bg-gradient-blues',
                                                'accept'       => 'bg-gradient-deepblue',
                                                'order_packed' => 'bg-gradient-blues',
                                                'shipping'     => 'bg-gradient-deepblue',
                                                'cancel'       => 'bg-gradient-bloody',
                                                'cancelled'    => 'bg-gradient-bloody',
                                            ];
                                            $color = $statusColors[$order->order_status] ?? 'bg-secondary';
                                        @endphp
                                        <span class="badge {{ $color }} text-white rounded-pill px-3 shadow-sm">{{ ucfirst(str_replace('_', ' ', $order->order_status)) }}</span>
                                    </td>
                                    <td>{{ $order->created_at ? $order->created_at->format('d M Y') : 'N/A' }}</td>
                                    <td>
                                        <a href="{{ route('order.details', $order->id) }}" class="btn btn-sm btn-inverse-primary">
                                            <i class="bx bx-show me-0"></i> View
                                        </a>
                                        <a href="{{ route('order.invoice', $order->id) }}" class="btn btn-sm btn-inverse-danger" title="Download invoice (PDF)">
                                            <i class="bx bxs-file-pdf me-0"></i>
                                        </a>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">No order records found.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

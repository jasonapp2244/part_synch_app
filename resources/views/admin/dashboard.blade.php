@extends('layouts.admin')
@section('title', 'Dashboard')

@section('content')
    <!--start page wrapper -->
    <div class="page-wrapper">
        <div class="page-content">

            <!-- Welcome Banner -->
            <div class="card radius-10 overflow-hidden">
                <div class="card-body p-0">
                    <div class="bg-gradient-cosmic p-4">
                        <div class="d-flex align-items-center">
                            <div>
                                <h5 class="mb-1 text-white">
                                    @php
                                        $hour = date('H');
                                        $greeting = $hour < 12 ? 'Good Morning' : ($hour < 17 ? 'Good Afternoon' : 'Good Evening');
                                    @endphp
                                    {{ $greeting }}, {{ auth()->user()?->first_name ?? 'Admin' }}
                                </h5>
                                <p class="mb-0 text-white" style="opacity: 0.85;">Here's what's happening with your marketplace today.</p>
                            </div>
                            <div class="ms-auto text-white">
                                <h6 class="mb-0 text-white"><i class="bx bx-calendar me-1"></i>{{ date('l, d M Y') }}</h6>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Stat Cards Row 1 -->
            <div class="row row-cols-1 row-cols-md-2 row-cols-xl-4">
                <div class="col">
                    <div class="card radius-10 overflow-hidden">
                        <div class="card-body p-0">
                            <div class="bg-gradient-deepblue p-4">
                                <div class="d-flex align-items-center">
                                    <div>
                                        <p class="mb-1 text-white" style="opacity:0.85">Total Orders</p>
                                        <h3 class="mb-0 text-white font-weight-bold">{{ number_format($totalOrders) }}</h3>
                                    </div>
                                    <div class="ms-auto">
                                        <i class='bx bxs-cart font-30 text-white'></i>
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer bg-transparent border-0 py-2 px-4">
                                <p class="mb-0 font-13 text-muted"><i class="bx bx-package me-1"></i>{{ $totalProducts }} products listed</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col">
                    <div class="card radius-10 overflow-hidden">
                        <div class="card-body p-0">
                            <div class="bg-gradient-burning p-4">
                                <div class="d-flex align-items-center">
                                    <div>
                                        <p class="mb-1 text-white" style="opacity:0.85">Total Revenue</p>
                                        <h3 class="mb-0 text-white font-weight-bold">${{ number_format($totalRevenue, 2) }}</h3>
                                    </div>
                                    <div class="ms-auto">
                                        <i class='bx bx-dollar-circle font-30 text-white'></i>
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer bg-transparent border-0 py-2 px-4">
                                <p class="mb-0 font-13 text-muted"><i class="bx bx-trending-up me-1"></i>From {{ $totalOrders }} orders</p>
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
                                        <p class="mb-1 text-white" style="opacity:0.85">Vendors</p>
                                        <h3 class="mb-0 text-white font-weight-bold">{{ number_format($totalVendors) }}</h3>
                                    </div>
                                    <div class="ms-auto">
                                        <i class='bx bx-store font-30 text-white'></i>
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer bg-transparent border-0 py-2 px-4">
                                <p class="mb-0 font-13 text-muted"><i class="bx bx-check-circle me-1 text-success"></i>{{ $activeVendors }} active</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col">
                    <div class="card radius-10 overflow-hidden">
                        <div class="card-body p-0">
                            <div class="bg-gradient-ibiza p-4">
                                <div class="d-flex align-items-center">
                                    <div>
                                        <p class="mb-1 text-white" style="opacity:0.85">Customers</p>
                                        <h3 class="mb-0 text-white font-weight-bold">{{ number_format($totalCustomers) }}</h3>
                                    </div>
                                    <div class="ms-auto">
                                        <i class='bx bx-group font-30 text-white'></i>
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer bg-transparent border-0 py-2 px-4">
                                <p class="mb-0 font-13 text-muted"><i class="bx bx-check-circle me-1 text-success"></i>{{ $activeCustomers }} active</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div><!--end row-->

            <!-- Charts Row -->
            <div class="row">
                <div class="col-xl-8 col-lg-7">
                    <div class="card radius-10">
                        <div class="card-header bg-transparent">
                            <div class="d-flex align-items-center">
                                <div>
                                    <h6 class="mb-0"><i class="bx bx-bar-chart-alt-2 me-1"></i> Monthly Revenue</h6>
                                </div>
                                <div class="ms-auto">
                                    <span class="badge bg-gradient-lush text-white rounded-pill px-3 py-2">Last 6 Months</span>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div style="height: 280px;">
                                <canvas id="revenueChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-4 col-lg-5">
                    <div class="card radius-10">
                        <div class="card-header bg-transparent">
                            <div class="d-flex align-items-center">
                                <div>
                                    <h6 class="mb-0"><i class="bx bx-pie-chart-alt me-1"></i> Order Status</h6>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div style="height: 210px;">
                                <canvas id="orderStatusChart"></canvas>
                            </div>
                            <div class="mt-3">
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="font-13"><i class="bx bxs-circle me-1" style="color:#ffdf40"></i>Pending</span>
                                    <span class="font-13 fw-bold">{{ $pendingOrders }}</span>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="font-13"><i class="bx bxs-circle me-1" style="color:#56ccf2"></i>Payment</span>
                                    <span class="font-13 fw-bold">{{ $paymentOrders }}</span>
                                </div>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="font-13"><i class="bx bxs-circle me-1" style="color:#42e695"></i>Completed</span>
                                    <span class="font-13 fw-bold">{{ $deliveredOrders }}</span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span class="font-13"><i class="bx bxs-circle me-1" style="color:#f54ea2"></i>Cancelled</span>
                                    <span class="font-13 fw-bold">{{ $cancelledOrders }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div><!--end row-->

            <!-- Quick Stats Row -->
            <div class="row row-cols-1 row-cols-md-3 row-cols-xl-6 mb-2">
                <div class="col">
                    <div class="card radius-10 border-start border-0 border-4 border-info">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center">
                                <div class="widgets-icons rounded-circle bg-light-info text-info"><i class='bx bx-category'></i></div>
                                <div class="ms-3">
                                    <h6 class="mb-0">{{ $totalCategories }}</h6>
                                    <p class="mb-0 font-13 text-muted">Categories</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col">
                    <div class="card radius-10 border-start border-0 border-4 border-success">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center">
                                <div class="widgets-icons rounded-circle bg-light-success text-success"><i class='bx bx-buildings'></i></div>
                                <div class="ms-3">
                                    <h6 class="mb-0">{{ $totalCompanies }}</h6>
                                    <p class="mb-0 font-13 text-muted">Companies</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col">
                    <div class="card radius-10 border-start border-0 border-4 border-warning">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center">
                                <div class="widgets-icons rounded-circle bg-light-warning text-warning"><i class='bx bx-package'></i></div>
                                <div class="ms-3">
                                    <h6 class="mb-0">{{ $totalProducts }}</h6>
                                    <p class="mb-0 font-13 text-muted">Products</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col">
                    <div class="card radius-10 border-start border-0 border-4 border-danger">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center">
                                <div class="widgets-icons rounded-circle bg-light-danger text-danger"><i class='bx bx-time-five'></i></div>
                                <div class="ms-3">
                                    <h6 class="mb-0">{{ $pendingOrders }}</h6>
                                    <p class="mb-0 font-13 text-muted">Pending</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col">
                    <div class="card radius-10 border-start border-0 border-4 border-primary">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center">
                                <div class="widgets-icons rounded-circle bg-light-primary text-primary"><i class='bx bx-check-double'></i></div>
                                <div class="ms-3">
                                    <h6 class="mb-0">{{ $deliveredOrders }}</h6>
                                    <p class="mb-0 font-13 text-muted">Completed</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col">
                    <div class="card radius-10 border-start border-0 border-4 border-secondary position-relative">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center">
                                <div class="widgets-icons rounded-circle bg-light-secondary text-secondary"><i class='bx bx-wrench'></i></div>
                                <div class="ms-3">
                                    <h6 class="mb-0">{{ $openServiceRequests }}</h6>
                                    <a href="{{ route('admin.service.requests') }}" class="mb-0 font-13 text-muted stretched-link">Open service requests</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bottom Row: Recent Orders + Top Vendors -->
            <div class="row">
                <div class="col-xl-8 col-lg-7">
                    <div class="card radius-10">
                        <div class="card-header bg-transparent">
                            <div class="d-flex align-items-center">
                                <div>
                                    <h6 class="mb-0"><i class="bx bx-receipt me-1"></i> Recent Orders</h6>
                                </div>
                                <div class="ms-auto">
                                    <a href="{{ route('earning.records') }}" class="btn btn-sm btn-outline-primary radius-30 px-3">View All</a>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>#</th>
                                            <th>Order Number</th>
                                            <th>Items</th>
                                            <th>Total</th>
                                            <th>Status</th>
                                            <th>Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($recentOrders as $index => $order)
                                        <tr>
                                            <td>{{ $index + 1 }}</td>
                                            <td><strong>{{ $order->order_number ?? 'N/A' }}</strong></td>
                                            <td>{{ $order->orderItems->count() }} item(s)</td>
                                            <td>${{ number_format($order->orderItems->sum('total_price'), 2) }}</td>
                                            <td>
                                                @if(in_array($order->order_status, ['completed', 'delivered'], true))
                                                    <span class="badge bg-gradient-quepal text-white shadow-sm w-100 rounded-pill">{{ ucfirst(str_replace('_', ' ', $order->order_status)) }}</span>
                                                @elseif($order->order_status === 'pending')
                                                    <span class="badge bg-gradient-blooker text-white shadow-sm w-100 rounded-pill">Pending</span>
                                                @elseif($order->order_status === 'payment')
                                                    <span class="badge bg-gradient-blues text-white shadow-sm w-100 rounded-pill">Payment</span>
                                                @elseif($order->order_status === 'cancel')
                                                    <span class="badge bg-gradient-bloody text-white shadow-sm w-100 rounded-pill">Cancelled</span>
                                                @else
                                                    <span class="badge bg-secondary text-white shadow-sm w-100 rounded-pill">{{ ucfirst(str_replace('_', ' ', $order->order_status)) }}</span>
                                                @endif
                                            </td>
                                            <td>{{ $order->created_at ? $order->created_at->format('d M Y') : 'N/A' }}</td>
                                        </tr>
                                        @empty
                                        <tr>
                                            <td colspan="6" class="text-center text-muted py-4">No recent orders found.</td>
                                        </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-xl-4 col-lg-5">
                    <div class="card radius-10">
                        <div class="card-header bg-transparent">
                            <div class="d-flex align-items-center">
                                <div>
                                    <h6 class="mb-0"><i class="bx bx-trophy me-1"></i> Top Vendors</h6>
                                </div>
                                <div class="ms-auto">
                                    <a href="{{ route('vendor.records') }}" class="btn btn-sm btn-outline-primary radius-30 px-3">View All</a>
                                </div>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div class="list-group list-group-flush radius-10">
                                @forelse($topVendors as $index => $vendor)
                                <div class="list-group-item d-flex align-items-center py-3 px-4">
                                    <div class="d-flex align-items-center">
                                        <div class="widgets-icons rounded-circle bg-gradient-{{ ['scooter','cosmic','ibiza','ohhappiness','burning'][$index % 5] }} text-white me-3" style="width:40px;height:40px;font-size:16px;">
                                            {{ strtoupper(substr($vendor->first_name ?? 'V', 0, 1)) }}
                                        </div>
                                        <div>
                                            <h6 class="mb-0 font-14">{{ $vendor->first_name }} {{ $vendor->last_name }}</h6>
                                            <p class="mb-0 font-13 text-muted">{{ $vendor->business_type ?? 'Vendor' }}</p>
                                        </div>
                                    </div>
                                    <div class="ms-auto">
                                        <span class="badge bg-light-info text-info rounded-pill px-3">{{ $vendor->products_count }} products</span>
                                    </div>
                                </div>
                                @empty
                                <div class="list-group-item text-center text-muted py-4">No vendors found.</div>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>
            </div><!--end row-->

        </div>
    </div>
    <!--end page wrapper -->
@endsection

@section('scripts')
<script>
    // Revenue Bar Chart
    var revenueCtx = document.getElementById('revenueChart');
    if (revenueCtx) {
        var gradient = revenueCtx.getContext('2d').createLinearGradient(0, 0, 0, 280);
        gradient.addColorStop(0, 'rgba(106, 17, 203, 0.8)');
        gradient.addColorStop(1, 'rgba(37, 117, 252, 0.4)');

        new Chart(revenueCtx, {
            type: 'bar',
            data: {
                labels: {!! json_encode($chartLabels) !!},
                datasets: [{
                    label: 'Revenue ($)',
                    data: {!! json_encode($chartData) !!},
                    backgroundColor: gradient,
                    borderRadius: 8,
                    borderSkipped: false,
                    barThickness: 40,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: 'rgba(0,0,0,0.05)' },
                        ticks: {
                            callback: function(value) { return '$' + value.toLocaleString(); }
                        }
                    },
                    x: {
                        grid: { display: false }
                    }
                }
            }
        });
    }

    // Order Status Doughnut Chart
    var statusCtx = document.getElementById('orderStatusChart');
    if (statusCtx) {
        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: ['Pending', 'Payment', 'Completed', 'Cancelled'],
                datasets: [{
                    data: [{{ $pendingOrders }}, {{ $paymentOrders }}, {{ $deliveredOrders }}, {{ $cancelledOrders }}],
                    backgroundColor: ['#ffdf40', '#56ccf2', '#42e695', '#f54ea2'],
                    borderWidth: 0,
                    hoverOffset: 8,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '70%',
                plugins: {
                    legend: { display: false }
                }
            }
        });
    }
</script>
@endsection

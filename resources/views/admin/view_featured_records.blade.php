@extends('layouts.admin')
@section('title', 'Featured Products')

@section('content')
    <div class="page-wrapper">
        <div class="page-content">
            <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
                <div class="breadcrumb-title pe-3">Featured</div>
                <div class="ps-3">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0 p-0">
                            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}"><i class="bx bx-home-alt"></i></a></li>
                            <li class="breadcrumb-item active" aria-current="page">Featured Products</li>
                        </ol>
                    </nav>
                </div>
                <div class="ms-auto">
                    <span class="badge bg-gradient-kyoto text-white rounded-pill px-3 py-2 shadow-sm">{{ $products->count() }} Featured</span>
                </div>
            </div>

            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            <div class="card radius-10 overflow-hidden">
                <div class="card-header bg-gradient-blooker p-3">
                    <div class="d-flex align-items-center">
                        <div>
                            <h6 class="mb-0 text-white"><i class="bx bx-star me-2"></i>Featured Products</h6>
                        </div>
                        <div class="ms-auto">
                            @include('admin.partials.export_buttons', ['list' => 'featured', 'onDark' => true])
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="example2" class="table table-striped table-bordered">
                            <thead>
                                <tr>
                                    <th>S.no</th>
                                    <th>Product Name</th>
                                    <th>Vendor</th>
                                    <th>Price</th>
                                    <th>Stock</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($products as $index => $product)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td>{{ $product->name ?? 'N/A' }}</td>
                                    <td>{{ $product->user ? $product->user->first_name . ' ' . $product->user->last_name : 'N/A' }}</td>
                                    <td><span class="fw-bold">${{ number_format($product->price ?? 0, 2) }}</span></td>
                                    <td>
                                        @if(($product->stock_quantity ?? 0) <= 0)
                                            <span class="badge bg-gradient-bloody text-white rounded-pill px-3 shadow-sm">Out of Stock</span>
                                        @else
                                            <span class="badge bg-gradient-quepal text-white rounded-pill px-3 shadow-sm">{{ $product->stock_quantity }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        <form action="{{ route('featured.toggle', $product->id) }}" method="POST" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-inverse-danger">
                                                <i class="bx bx-x me-0"></i> Remove Featured
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">No featured products found.</td>
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

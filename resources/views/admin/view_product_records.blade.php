@extends('layouts.admin')
@section('title', 'Products')

@section('content')
    <div class="page-wrapper">
        <div class="page-content">
            <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
                <div class="breadcrumb-title pe-3">Catalog</div>
                <div class="ps-3">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0 p-0">
                            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}"><i class="bx bx-home-alt"></i></a></li>
                            <li class="breadcrumb-item active" aria-current="page">Products</li>
                        </ol>
                    </nav>
                </div>
                <div class="ms-auto">
                    <span class="badge bg-gradient-orange text-white rounded-pill px-3 py-2 shadow-sm">{{ $products->count() }} Products</span>
                </div>
            </div>

            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            <div class="card radius-10 overflow-hidden">
                <div class="card-header bg-gradient-orange p-3">
                    <div class="d-flex align-items-center">
                        <div>
                            <h6 class="mb-0 text-white"><i class="bx bx-box me-2"></i>Product Records</h6>
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
                                    <th>Price</th>
                                    <th>Stock</th>
                                    <th>Vendor</th>
                                    <th>Featured</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($products as $index => $product)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td><span class="fw-bold">{{ $product->name ?? 'N/A' }}</span></td>
                                    <td><span class="fw-bold text-primary">${{ number_format($product->price ?? 0, 2) }}</span></td>
                                    <td>
                                        @if(($product->stock_quantity ?? 0) <= 0)
                                            <span class="badge bg-gradient-bloody text-white rounded-pill px-3 shadow-sm">Out of Stock</span>
                                        @elseif(($product->stock_quantity ?? 0) < 10)
                                            <span class="badge bg-gradient-blooker text-white rounded-pill px-3 shadow-sm">{{ $product->stock_quantity }} Left</span>
                                        @else
                                            <span class="badge bg-gradient-lush text-white rounded-pill px-3 shadow-sm">{{ $product->stock_quantity }} In Stock</span>
                                        @endif
                                    </td>
                                    <td>{{ $product->user ? $product->user->first_name . ' ' . $product->user->last_name : 'N/A' }}</td>
                                    <td>
                                        <form action="{{ route('featured.toggle', $product->id) }}" method="POST" class="d-inline">
                                            @csrf
                                            <button type="submit" class="badge border-0 text-white rounded-pill px-3 shadow-sm {{ $product->is_top ? 'bg-gradient-blooker' : 'bg-secondary' }}">
                                                <i class="bx {{ $product->is_top ? 'bxs-star' : 'bx-star' }}"></i>
                                                {{ $product->is_top ? 'Featured' : 'Feature' }}
                                            </button>
                                        </form>
                                    </td>
                                    <td>
                                        <form action="{{ route('product.toggle.status', $product->id) }}" method="POST" class="d-inline">
                                            @csrf
                                            <button type="submit" class="badge border-0 text-white rounded-pill px-3 shadow-sm {{ $product->status === 'active' ? 'bg-gradient-quepal' : 'bg-gradient-bloody' }}">
                                                {{ ucfirst($product->status ?? 'inactive') }}
                                            </button>
                                        </form>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-inverse-primary" data-bs-toggle="modal" data-bs-target="#viewProductModal{{ $product->id }}">
                                            <i class="bx bx-show me-0"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-inverse-danger" data-bs-toggle="modal" data-bs-target="#deleteProductModal{{ $product->id }}">
                                            <i class="bx bx-trash me-0"></i>
                                        </button>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">No product records found.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @foreach($products as $product)
    <!-- View Product Modal -->
    <div class="modal fade" id="viewProductModal{{ $product->id }}" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Product Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Name:</strong> {{ $product->name ?? 'N/A' }}</p>
                            <p><strong>SKU:</strong> {{ $product->sku ?? 'N/A' }}</p>
                            <p><strong>Modal Number:</strong> {{ $product->modal_number ?? 'N/A' }}</p>
                            <p><strong>Price:</strong> ${{ number_format($product->price ?? 0, 2) }}</p>
                            <p><strong>Stock:</strong> {{ $product->stock_quantity ?? 0 }}</p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Brand:</strong> {{ $product->brand ?? 'N/A' }}</p>
                            <p><strong>Vendor:</strong> {{ $product->user ? $product->user->first_name . ' ' . $product->user->last_name : 'N/A' }}</p>
                            <p><strong>Status:</strong> <span class="badge {{ $product->status === 'active' ? 'bg-success' : 'bg-secondary' }}">{{ ucfirst($product->status ?? 'inactive') }}</span></p>
                            <p><strong>Featured:</strong> {{ $product->is_top ? 'Yes' : 'No' }}</p>
                            <p><strong>Created:</strong> {{ $product->created_at ? $product->created_at->format('d M Y') : 'N/A' }}</p>
                        </div>
                    </div>
                    @if($product->description)
                        <hr>
                        <p><strong>Description:</strong><br>{{ $product->description }}</p>
                    @endif
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Modal -->
    <div class="modal fade" id="deleteProductModal{{ $product->id }}" tabindex="-1">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <form action="{{ route('product.destroy', $product->id) }}" method="POST">
                    @csrf
                    @method('DELETE')
                    <div class="modal-header">
                        <h5 class="modal-title">Delete Product</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>Are you sure you want to delete <strong>{{ $product->name }}</strong>?</p>
                        <div class="alert alert-warning py-2">This action cannot be undone.</div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Delete</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endforeach
@endsection

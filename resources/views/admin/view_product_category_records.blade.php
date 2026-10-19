@extends('layouts.admin')
@section('title', 'Product Categories')

@section('content')
    <div class="page-wrapper">
        <div class="page-content">
            <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
                <div class="breadcrumb-title pe-3">Catalog</div>
                <div class="ps-3">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0 p-0">
                            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}"><i class="bx bx-home-alt"></i></a></li>
                            <li class="breadcrumb-item active" aria-current="page">Product Categories</li>
                        </ol>
                    </nav>
                </div>
                <div class="ms-auto">
                    <span class="badge bg-gradient-ibiza text-white rounded-pill px-3 py-2 shadow-sm me-2">{{ $productCategories->count() }} Product Categories</span>
                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addProductCategoryModal">
                        <i class="bx bx-plus"></i> Add Product Category
                    </button>
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

            @if($errors->any())
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            <div class="card radius-10 overflow-hidden">
                <div class="card-header bg-gradient-ibiza p-3">
                    <div class="d-flex align-items-center">
                        <div>
                            <h6 class="mb-0 text-white"><i class="bx bx-purchase-tag me-2"></i>Product Category Records</h6>
                        </div>
                        <div class="ms-auto">
                            @include('admin.partials.export_buttons', ['list' => 'product-categories', 'onDark' => true])
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="example2" class="table table-striped table-bordered">
                            <thead>
                                <tr>
                                    <th>S.no</th>
                                    <th>Image</th>
                                    <th>Name</th>
                                    <th>Company</th>
                                    <th>Category / Sub category</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($productCategories as $index => $productCategory)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td>
                                        @if($productCategory->product_categories_image)
                                            <img src="{{ asset('storage/' . $productCategory->product_categories_image) }}" alt="" width="40" height="40" class="rounded" style="object-fit:cover;">
                                        @else
                                            <div class="widgets-icons rounded-circle bg-gradient-cosmic text-white" style="width:40px;height:40px;font-size:14px;">
                                                {{ strtoupper(substr($productCategory->product_categories_name ?? 'P', 0, 1)) }}
                                            </div>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="fw-bold">{{ $productCategory->product_categories_name ?? 'N/A' }}</span>
                                        @if($productCategory->description)
                                            <div class="small text-muted">{{ Str::limit($productCategory->description, 50) }}</div>
                                        @endif
                                    </td>
                                    <td>{{ $productCategory->company->company_name ?? 'N/A' }}</td>
                                    <td>
                                        {{ $categoryNames[$productCategory->category_id] ?? 'N/A' }}
                                        <span class="text-muted">/</span>
                                        {{ $subCategoryNames[$productCategory->sub_category_id] ?? 'N/A' }}
                                    </td>
                                    <td>
                                        <form action="{{ route('product.category.toggle.status', $productCategory->id) }}" method="POST" class="d-inline">
                                            @csrf
                                            <button type="submit" class="badge border-0 text-white rounded-pill px-3 shadow-sm {{ $productCategory->status === 'active' ? 'bg-gradient-quepal' : 'bg-gradient-bloody' }}">
                                                {{ ucfirst($productCategory->status ?? 'inactive') }}
                                            </button>
                                        </form>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-inverse-primary" data-bs-toggle="modal" data-bs-target="#editProductCategoryModal{{ $productCategory->id }}">
                                            <i class="bx bx-edit me-0"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-inverse-danger" data-bs-toggle="modal" data-bs-target="#deleteProductCategoryModal{{ $productCategory->id }}">
                                            <i class="bx bx-trash me-0"></i>
                                        </button>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">No product category records found.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Product Category Modal -->
    <div class="modal fade" id="addProductCategoryModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="{{ route('product.category.store') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">Add Product Category</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Company <span class="text-danger">*</span></label>
                            <select class="form-select" name="company_id" required>
                                <option value="">Select Company</option>
                                @foreach($companies as $company)
                                    <option value="{{ $company->id }}" {{ old('company_id') == $company->id ? 'selected' : '' }}>{{ $company->company_name }}</option>
                                @endforeach
                            </select>
                            <small class="text-muted">Category and sub category are taken from the company.</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="product_categories_name" maxlength="200" value="{{ old('product_categories_name') }}" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="3" maxlength="500">{{ old('description') }}</textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Image</label>
                            <input type="file" class="form-control" name="product_categories_image" accept="image/*">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @foreach($productCategories as $productCategory)
    <!-- Edit Modal -->
    <div class="modal fade" id="editProductCategoryModal{{ $productCategory->id }}" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="{{ route('product.category.update', $productCategory->id) }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    @method('PUT')
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Product Category</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Company <span class="text-danger">*</span></label>
                            <select class="form-select" name="company_id" required>
                                @foreach($companies as $company)
                                    <option value="{{ $company->id }}" {{ $productCategory->company_id == $company->id ? 'selected' : '' }}>{{ $company->company_name }}</option>
                                @endforeach
                            </select>
                            <small class="text-muted">Category and sub category are taken from the company.</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="product_categories_name" maxlength="200" value="{{ $productCategory->product_categories_name }}" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="3" maxlength="500">{{ $productCategory->description }}</textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Image</label>
                            @if($productCategory->product_categories_image)
                                <div class="mb-2"><img src="{{ asset('storage/' . $productCategory->product_categories_image) }}" alt="" width="60" class="rounded"></div>
                            @endif
                            <input type="file" class="form-control" name="product_categories_image" accept="image/*">
                            <small class="text-muted">Leave empty to keep current image</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status">
                                <option value="active" {{ $productCategory->status === 'active' ? 'selected' : '' }}>Active</option>
                                <option value="inactive" {{ $productCategory->status === 'inactive' ? 'selected' : '' }}>Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Modal -->
    <div class="modal fade" id="deleteProductCategoryModal{{ $productCategory->id }}" tabindex="-1">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <form action="{{ route('product.category.destroy', $productCategory->id) }}" method="POST">
                    @csrf
                    @method('DELETE')
                    <div class="modal-header">
                        <h5 class="modal-title">Delete Product Category</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>Are you sure you want to delete <strong>{{ $productCategory->product_categories_name }}</strong>?</p>
                        <p class="small text-muted mb-0">Categories used by products cannot be deleted; set them to inactive instead.</p>
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

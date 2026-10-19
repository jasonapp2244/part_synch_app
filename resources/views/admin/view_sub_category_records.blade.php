@extends('layouts.admin')
@section('title', 'Sub Categories')

@section('content')
    <div class="page-wrapper">
        <div class="page-content">
            <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
                <div class="breadcrumb-title pe-3">Catalog</div>
                <div class="ps-3">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0 p-0">
                            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}"><i class="bx bx-home-alt"></i></a></li>
                            <li class="breadcrumb-item active" aria-current="page">Sub Categories</li>
                        </ol>
                    </nav>
                </div>
                <div class="ms-auto">
                    <span class="badge bg-gradient-cosmic text-white rounded-pill px-3 py-2 shadow-sm me-2">{{ $subCategories->count() }} Sub Categories</span>
                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addSubCategoryModal">
                        <i class="bx bx-plus"></i> Add Sub Category
                    </button>
                </div>
            </div>

            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @if($errors->any())
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <ul class="mb-0">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            <div class="card radius-10 overflow-hidden">
                <div class="card-header bg-gradient-cosmic p-3">
                    <div class="d-flex align-items-center">
                        <div>
                            <h6 class="mb-0 text-white"><i class="bx bx-subdirectory-right me-2"></i>Sub Category Records</h6>
                        </div>
                        <div class="ms-auto">
                            @include('admin.partials.export_buttons', ['list' => 'sub-categories', 'onDark' => true])
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="example2" class="table table-striped table-bordered">
                            <thead>
                                <tr>
                                    <th>S.no</th>
                                    <th>Sub Category Name</th>
                                    <th>Parent Category</th>
                                    <th>Status</th>
                                    <th>Created At</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($subCategories as $index => $subCategory)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td><span class="fw-bold">{{ $subCategory->sub_category_name ?? 'N/A' }}</span></td>
                                    <td><span class="badge bg-gradient-scooter text-white rounded-pill px-3 shadow-sm">{{ $subCategory->category->name ?? 'N/A' }}</span></td>
                                    <td>
                                        <form action="{{ route('sub.category.toggle.status', $subCategory->id) }}" method="POST" class="d-inline">
                                            @csrf
                                            <button type="submit" class="badge border-0 text-white rounded-pill px-3 shadow-sm {{ $subCategory->status === 'active' ? 'bg-gradient-quepal' : 'bg-gradient-bloody' }}">
                                                {{ ucfirst($subCategory->status ?? 'inactive') }}
                                            </button>
                                        </form>
                                    </td>
                                    <td>{{ $subCategory->created_at ? $subCategory->created_at->format('d M Y') : 'N/A' }}</td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-inverse-primary" data-bs-toggle="modal" data-bs-target="#editSubCategoryModal{{ $subCategory->id }}">
                                            <i class="bx bx-edit me-0"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-inverse-danger" data-bs-toggle="modal" data-bs-target="#deleteSubCategoryModal{{ $subCategory->id }}">
                                            <i class="bx bx-trash me-0"></i>
                                        </button>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">No sub category records found.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Sub Category Modal -->
    <div class="modal fade" id="addSubCategoryModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="{{ route('sub.category.store') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">Add Sub Category</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Parent Category <span class="text-danger">*</span></label>
                            <select class="form-select" name="category_id" required>
                                <option value="">Select Category</option>
                                @foreach($categories as $category)
                                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Sub Category Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="sub_category_name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Image</label>
                            <input type="file" class="form-control" name="image" accept="image/*">
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

    <!-- Edit & Delete Modals -->
    @foreach($subCategories as $subCategory)
    <!-- Edit Modal -->
    <div class="modal fade" id="editSubCategoryModal{{ $subCategory->id }}" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="{{ route('sub.category.update', $subCategory->id) }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    @method('PUT')
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Sub Category</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Parent Category <span class="text-danger">*</span></label>
                            <select class="form-select" name="category_id" required>
                                @foreach($categories as $category)
                                    <option value="{{ $category->id }}" {{ $subCategory->category_id == $category->id ? 'selected' : '' }}>{{ $category->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Sub Category Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="sub_category_name" value="{{ $subCategory->sub_category_name }}" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Image</label>
                            @if($subCategory->image)
                                <div class="mb-2">
                                    <img src="{{ asset('storage/' . $subCategory->image) }}" alt="" width="60" class="rounded">
                                </div>
                            @endif
                            <input type="file" class="form-control" name="image" accept="image/*">
                            <small class="text-muted">Leave empty to keep current image</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status">
                                <option value="active" {{ $subCategory->status === 'active' ? 'selected' : '' }}>Active</option>
                                <option value="inactive" {{ $subCategory->status === 'inactive' ? 'selected' : '' }}>Inactive</option>
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
    <div class="modal fade" id="deleteSubCategoryModal{{ $subCategory->id }}" tabindex="-1">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <form action="{{ route('sub.category.destroy', $subCategory->id) }}" method="POST">
                    @csrf
                    @method('DELETE')
                    <div class="modal-header">
                        <h5 class="modal-title">Delete Sub Category</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>Are you sure you want to delete <strong>{{ $subCategory->sub_category_name }}</strong>?</p>
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

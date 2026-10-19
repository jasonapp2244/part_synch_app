@extends('layouts.admin')
@section('title', 'Companies')

@section('content')
    <div class="page-wrapper">
        <div class="page-content">
            <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
                <div class="breadcrumb-title pe-3">Catalog</div>
                <div class="ps-3">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0 p-0">
                            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}"><i class="bx bx-home-alt"></i></a></li>
                            <li class="breadcrumb-item active" aria-current="page">Companies</li>
                        </ol>
                    </nav>
                </div>
                <div class="ms-auto">
                    <span class="badge bg-gradient-ibiza text-white rounded-pill px-3 py-2 shadow-sm me-2">{{ $companies->count() }} Companies</span>
                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addCompanyModal">
                        <i class="bx bx-plus"></i> Add Company
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
                    <ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            <div class="card radius-10 overflow-hidden">
                <div class="card-header bg-gradient-ibiza p-3">
                    <div class="d-flex align-items-center">
                        <div>
                            <h6 class="mb-0 text-white"><i class="bx bx-buildings me-2"></i>Company Records</h6>
                        </div>
                        <div class="ms-auto">
                            @include('admin.partials.export_buttons', ['list' => 'companies', 'onDark' => true])
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="example2" class="table table-striped table-bordered">
                            <thead>
                                <tr>
                                    <th>S.no</th>
                                    <th>Company Name</th>
                                    <th>Vendor</th>
                                    <th>Description</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($companies as $index => $company)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td><span class="fw-bold">{{ $company->company_name ?? 'N/A' }}</span></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="widgets-icons rounded-circle bg-gradient-cosmic text-white me-2" style="width:32px;height:32px;font-size:13px;">
                                                {{ strtoupper(substr($company->user->first_name ?? 'V', 0, 1)) }}
                                            </div>
                                            {{ $company->user ? $company->user->first_name . ' ' . $company->user->last_name : 'N/A' }}
                                        </div>
                                    </td>
                                    <td>{{ Str::limit($company->description ?? 'N/A', 50) }}</td>
                                    <td>
                                        <form action="{{ route('company.toggle.status', $company->id) }}" method="POST" class="d-inline">
                                            @csrf
                                            <button type="submit" class="badge border-0 text-white rounded-pill px-3 shadow-sm {{ $company->status === 'active' ? 'bg-gradient-quepal' : 'bg-gradient-bloody' }}">
                                                {{ ucfirst($company->status ?? 'inactive') }}
                                            </button>
                                        </form>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-inverse-primary" data-bs-toggle="modal" data-bs-target="#editCompanyModal{{ $company->id }}">
                                            <i class="bx bx-edit me-0"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-inverse-danger" data-bs-toggle="modal" data-bs-target="#deleteCompanyModal{{ $company->id }}">
                                            <i class="bx bx-trash me-0"></i>
                                        </button>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">No company records found.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Company Modal -->
    <div class="modal fade" id="addCompanyModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="{{ route('company.store') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">Add Company</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Company Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="company_name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Category <span class="text-danger">*</span></label>
                            <select class="form-select" name="category_id" required>
                                <option value="">Select Category</option>
                                @foreach($categories as $cat)
                                    <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Sub Category <span class="text-danger">*</span></label>
                            <select class="form-select" name="sub_category_id" required>
                                <option value="">Select Sub Category</option>
                                @foreach($subCategories as $sub)
                                    <option value="{{ $sub->id }}">{{ $sub->sub_category_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Image</label>
                            <input type="file" class="form-control" name="company_image" accept="image/*">
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

    @foreach($companies as $company)
    <!-- Edit Modal -->
    <div class="modal fade" id="editCompanyModal{{ $company->id }}" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="{{ route('company.update', $company->id) }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    @method('PUT')
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Company</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Company Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="company_name" value="{{ $company->company_name }}" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Category <span class="text-danger">*</span></label>
                            <select class="form-select" name="category_id" required>
                                @foreach($categories as $cat)
                                    <option value="{{ $cat->id }}" {{ $company->category_id == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Sub Category <span class="text-danger">*</span></label>
                            <select class="form-select" name="sub_category_id" required>
                                @foreach($subCategories as $sub)
                                    <option value="{{ $sub->id }}" {{ $company->sub_category_id == $sub->id ? 'selected' : '' }}>{{ $sub->sub_category_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="3">{{ $company->description }}</textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Image</label>
                            @if($company->company_image)
                                <div class="mb-2"><img src="{{ asset('storage/' . $company->company_image) }}" alt="" width="60" class="rounded"></div>
                            @endif
                            <input type="file" class="form-control" name="company_image" accept="image/*">
                            <small class="text-muted">Leave empty to keep current image</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status">
                                <option value="active" {{ $company->status === 'active' ? 'selected' : '' }}>Active</option>
                                <option value="inactive" {{ $company->status === 'inactive' ? 'selected' : '' }}>Inactive</option>
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
    <div class="modal fade" id="deleteCompanyModal{{ $company->id }}" tabindex="-1">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <form action="{{ route('company.destroy', $company->id) }}" method="POST">
                    @csrf
                    @method('DELETE')
                    <div class="modal-header">
                        <h5 class="modal-title">Delete Company</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>Are you sure you want to delete <strong>{{ $company->company_name }}</strong>?</p>
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

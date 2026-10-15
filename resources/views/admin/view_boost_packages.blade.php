@extends('layouts.admin')

@section('content')
    <div class="page-wrapper">
        <div class="page-content">
            <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
                <div class="breadcrumb-title pe-3">Tables</div>
                <div class="ps-3">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0 p-0">
                            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}"><i class="bx bx-home-alt"></i></a></li>
                            <li class="breadcrumb-item active" aria-current="page">Boost Packages</li>
                        </ol>
                    </nav>
                </div>
                <div class="ms-auto">
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPackageModal">
                        <i class="bx bx-plus"></i> Add Package
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

            <!-- Boost Packages -->
            <h6 class="mb-0 text-uppercase">Boost Packages</h6>
            <hr />
            <div class="card mb-4">
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="example2" class="table table-striped table-bordered">
                            <thead>
                                <tr>
                                    <th>S.no</th>
                                    <th>Name</th>
                                    <th>Price</th>
                                    <th>Product Limit</th>
                                    <th>Duration</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($packages as $index => $package)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td>{{ $package->name }}</td>
                                    <td>${{ number_format($package->price, 2) }} <small class="text-muted">{{ strtoupper($package->currency) }}</small></td>
                                    <td>{{ $package->product_limit }}</td>
                                    <td>{{ $package->duration_days }} days</td>
                                    <td>
                                        <form action="{{ route('boost.package.toggle.status', $package->id) }}" method="POST" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-sm {{ $package->status ? 'btn-success' : 'btn-secondary' }}">
                                                {{ $package->status ? 'Active' : 'Inactive' }}
                                            </button>
                                        </form>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editPackageModal{{ $package->id }}">
                                            <i class="bx bx-edit"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deletePackageModal{{ $package->id }}">
                                            <i class="bx bx-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="7" class="text-center">No boost packages found.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Boost Positions: capacity and pricing -->
            <h6 class="mb-0 text-uppercase">Boost Positions</h6>
            <hr />
            <div class="card">
                <div class="card-body">
                    <p class="text-muted small">
                        <strong>Slots</strong> = how many boosted products the section shows at once. When a section is full,
                        new approvals are scheduled for the next free date. <strong>Price ×</strong> multiplies the package price
                        (e.g. 1.5 = 50% more for the Top section). The app shows the Top, Middle and Bottom sections only.
                    </p>
                    <div class="table-responsive">
                        <table class="table table-striped table-bordered align-middle">
                            <thead>
                                <tr>
                                    <th>Position</th>
                                    <th>Slots in use now</th>
                                    <th style="width: 130px;">Slots</th>
                                    <th style="width: 130px;">Price ×</th>
                                    <th>Offered to vendors</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($positions as $position)
                                <tr>
                                    <td>{{ $position->name }}</td>
                                    <td>{{ $slotsInUse[$position->id] ?? 0 }} / {{ $position->display_limit }}</td>
                                    <td colspan="4" class="p-0">
                                        <form action="{{ route('boost.position.update', $position->id) }}" method="POST" class="d-flex align-items-center gap-2 p-2 flex-wrap">
                                            @csrf
                                            @method('PUT')
                                            <input type="number" name="display_limit" value="{{ $position->display_limit }}" min="1" max="50" required class="form-control form-control-sm" style="width: 110px;" aria-label="Slots">
                                            <input type="number" name="price_multiplier" value="{{ $position->price_multiplier }}" min="0.1" max="9.99" step="0.05" required class="form-control form-control-sm" style="width: 110px;" aria-label="Price multiplier">
                                            <div class="form-check form-switch mb-0 mx-2">
                                                <input class="form-check-input" type="checkbox" name="status" value="1" id="posStatus{{ $position->id }}" {{ $position->status ? 'checked' : '' }}>
                                                <label class="form-check-label" for="posStatus{{ $position->id }}">{{ $position->status ? 'Yes' : 'No' }}</label>
                                            </div>
                                            <button type="submit" class="btn btn-sm btn-outline-primary ms-auto"><i class="bx bx-save"></i> Save</button>
                                        </form>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="6" class="text-center">No boost positions found.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Package Modal -->
    <div class="modal fade" id="addPackageModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="{{ route('boost.package.store') }}" method="POST">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">Add Boost Package</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Package Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="name" required>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Price <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" name="price" step="0.01" min="0" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Currency</label>
                                <input type="text" class="form-control" name="currency" value="usd" placeholder="usd">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Product Limit <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" name="product_limit" value="1" min="1" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Duration (days) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" name="duration_days" value="30" min="1" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="3"></textarea>
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

    @foreach($packages as $package)
    <!-- Edit Modal -->
    <div class="modal fade" id="editPackageModal{{ $package->id }}" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="{{ route('boost.package.update', $package->id) }}" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Boost Package</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Package Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="name" value="{{ $package->name }}" required>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Price <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" name="price" value="{{ $package->price }}" step="0.01" min="0" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Currency</label>
                                <input type="text" class="form-control" name="currency" value="{{ $package->currency }}">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Product Limit <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" name="product_limit" value="{{ $package->product_limit }}" min="1" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Duration (days) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" name="duration_days" value="{{ $package->duration_days }}" min="1" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="3">{{ $package->description }}</textarea>
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
    <div class="modal fade" id="deletePackageModal{{ $package->id }}" tabindex="-1">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <form action="{{ route('boost.package.destroy', $package->id) }}" method="POST">
                    @csrf
                    @method('DELETE')
                    <div class="modal-header">
                        <h5 class="modal-title">Delete Package</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>Are you sure you want to delete <strong>{{ $package->name }}</strong>?</p>
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

@extends('layouts.admin')
@section('title', 'Vendors')

@section('content')
    <div class="page-wrapper">
        <div class="page-content">
            <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
                <div class="breadcrumb-title pe-3">Vendors</div>
                <div class="ps-3">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0 p-0">
                            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}"><i class="bx bx-home-alt"></i></a></li>
                            <li class="breadcrumb-item active" aria-current="page">Vendor Records</li>
                        </ol>
                    </nav>
                </div>
                <div class="ms-auto d-flex gap-2">
                    @if($pendingCount > 0)
                        <span class="badge bg-warning text-dark rounded-pill px-3 py-2 shadow-sm">{{ $pendingCount }} Awaiting Approval</span>
                    @endif
                    <span class="badge bg-gradient-quepal text-white rounded-pill px-3 py-2 shadow-sm">{{ $vendors->count() }} Total Vendors</span>
                </div>
            </div>

            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            <div class="card radius-10 overflow-hidden">
                <div class="card-header bg-gradient-deepblue p-3">
                    <div class="d-flex align-items-center">
                        <div>
                            <h6 class="mb-0 text-white"><i class="bx bx-store-alt me-2"></i>Vendor Records</h6>
                        </div>
                        <div class="ms-auto">
                            @include('admin.partials.export_buttons', ['list' => 'vendors', 'onDark' => true])
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="example2" class="table table-striped table-bordered">
                            <thead>
                                <tr>
                                    <th>S.no</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Business Type</th>
                                    <th>Phone Number</th>
                                    <th>Approval</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($vendors as $index => $vendor)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="widgets-icons rounded-circle bg-gradient-scooter text-white me-2" style="width:32px;height:32px;font-size:13px;">
                                                {{ strtoupper(substr($vendor->first_name ?? 'V', 0, 1)) }}
                                            </div>
                                            {{ $vendor->first_name }} {{ $vendor->last_name }}
                                        </div>
                                    </td>
                                    <td>{{ $vendor->email ?? 'N/A' }}</td>
                                    <td>{{ $vendor->business_type ?? 'N/A' }}</td>
                                    <td>{{ $vendor->phone_number ?? 'N/A' }}</td>
                                    <td>
                                        @if($vendor->business_status === 'active')
                                            <span class="badge bg-success rounded-pill px-3">Approved</span>
                                            <button type="button" class="btn btn-sm btn-link text-danger p-0 ms-1" data-bs-toggle="modal" data-bs-target="#revokeVendorModal{{ $vendor->id }}">Revoke</button>
                                        @else
                                            <form action="{{ route('vendor.toggle.approval', $vendor->id) }}" method="POST" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-success px-3"><i class="bx bx-check me-1"></i>Approve</button>
                                            </form>
                                        @endif
                                    </td>
                                    <td>
                                        <form action="{{ route('vendor.toggle.status', $vendor->id) }}" method="POST" class="d-inline">
                                            @csrf
                                            <button type="submit" class="badge border-0 text-white rounded-pill px-3 shadow-sm {{ $vendor->status === 'active' ? 'bg-gradient-quepal' : 'bg-gradient-bloody' }}">
                                                {{ ucfirst($vendor->status ?? 'inactive') }}
                                            </button>
                                        </form>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-inverse-primary" data-bs-toggle="modal" data-bs-target="#viewVendorModal{{ $vendor->id }}">
                                            <i class="bx bx-show me-0"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-inverse-danger" data-bs-toggle="modal" data-bs-target="#deleteVendorModal{{ $vendor->id }}">
                                            <i class="bx bx-trash me-0"></i>
                                        </button>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">No vendor records found.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @foreach($vendors as $vendor)
    <!-- View Vendor Modal -->
    <div class="modal fade" id="viewVendorModal{{ $vendor->id }}" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Vendor Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Name:</strong> {{ $vendor->first_name }} {{ $vendor->last_name }}</p>
                            <p><strong>Email:</strong> {{ $vendor->email ?? 'N/A' }}</p>
                            <p><strong>Phone:</strong> {{ $vendor->phone_number ?? 'N/A' }}</p>
                            <p><strong>Approval:</strong> <span class="badge {{ $vendor->business_status === 'active' ? 'bg-success' : 'bg-warning text-dark' }}">{{ $vendor->business_status === 'active' ? 'Approved' : 'Awaiting approval' }}</span></p>
                            <p><strong>Status:</strong> <span class="badge {{ $vendor->status === 'active' ? 'bg-success' : 'bg-secondary' }}">{{ ucfirst($vendor->status ?? 'inactive') }}</span></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Business Type:</strong> {{ $vendor->business_type ?? 'N/A' }}</p>
                            <p><strong>Business Description:</strong> {{ $vendor->business_description ?? 'N/A' }}</p>
                            <p><strong>Business License:</strong> {{ $vendor->business_license ?? 'N/A' }}</p>
                            <p><strong>Joined:</strong> {{ $vendor->created_at ? $vendor->created_at->format('d M Y') : 'N/A' }}</p>
                        </div>
                    </div>
                    @if($vendor->business_logo)
                        <div class="mt-2">
                            <strong>Business Logo:</strong><br>
                            <img src="{{ asset('storage/' . $vendor->business_logo) }}" alt="Logo" width="100" class="rounded mt-1">
                        </div>
                    @endif
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    @if($vendor->business_status === 'active')
    <!-- Revoke Approval Modal -->
    <div class="modal fade" id="revokeVendorModal{{ $vendor->id }}" tabindex="-1">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <form action="{{ route('vendor.toggle.approval', $vendor->id) }}" method="POST">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">Revoke Approval</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>Withdraw approval for <strong>{{ $vendor->first_name }} {{ $vendor->last_name }}</strong>?</p>
                        <div class="alert alert-warning py-2">They will be signed out of the app and cannot sign in as a vendor until approved again.</div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Revoke</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif

    <!-- Delete Modal -->
    <div class="modal fade" id="deleteVendorModal{{ $vendor->id }}" tabindex="-1">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <form action="{{ route('vendor.destroy', $vendor->id) }}" method="POST">
                    @csrf
                    @method('DELETE')
                    <div class="modal-header">
                        <h5 class="modal-title">Delete Vendor</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>Are you sure you want to delete <strong>{{ $vendor->first_name }} {{ $vendor->last_name }}</strong>?</p>
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

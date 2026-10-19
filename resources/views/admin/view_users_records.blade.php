@extends('layouts.admin')
@section('title', 'Users')

@section('content')
    <div class="page-wrapper">
        <div class="page-content">
            <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
                <div class="breadcrumb-title pe-3">Users</div>
                <div class="ps-3">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0 p-0">
                            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}"><i class="bx bx-home-alt"></i></a></li>
                            <li class="breadcrumb-item active" aria-current="page">User Records</li>
                        </ol>
                    </nav>
                </div>
                <div class="ms-auto">
                    <span class="badge bg-gradient-blues text-white rounded-pill px-3 py-2 shadow-sm">{{ $users->count() }} Total Users</span>
                </div>
            </div>

            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            <div class="card radius-10 overflow-hidden">
                <div class="card-header bg-gradient-cosmic p-3">
                    <div class="d-flex align-items-center">
                        <div>
                            <h6 class="mb-0 text-white"><i class="bx bx-group me-2"></i>User Records</h6>
                        </div>
                        <div class="ms-auto">
                            @include('admin.partials.export_buttons', ['list' => 'users', 'onDark' => true])
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
                                    <th>Phone Number</th>
                                    <th>Joined</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($users as $index => $user)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="widgets-icons rounded-circle bg-gradient-ibiza text-white me-2" style="width:32px;height:32px;font-size:13px;">
                                                {{ strtoupper(substr($user->first_name ?? 'U', 0, 1)) }}
                                            </div>
                                            {{ $user->first_name }} {{ $user->last_name }}
                                        </div>
                                    </td>
                                    <td>{{ $user->email ?? 'N/A' }}</td>
                                    <td>{{ $user->phone_number ?? 'N/A' }}</td>
                                    <td>{{ $user->created_at ? $user->created_at->format('d M Y') : 'N/A' }}</td>
                                    <td>
                                        <form action="{{ route('user.toggle.status', $user->id) }}" method="POST" class="d-inline">
                                            @csrf
                                            <button type="submit" class="badge border-0 text-white rounded-pill px-3 shadow-sm {{ $user->status === 'active' ? 'bg-gradient-quepal' : 'bg-gradient-bloody' }}">
                                                {{ ucfirst($user->status ?? 'inactive') }}
                                            </button>
                                        </form>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-inverse-primary" data-bs-toggle="modal" data-bs-target="#viewUserModal{{ $user->id }}">
                                            <i class="bx bx-show me-0"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-inverse-danger" data-bs-toggle="modal" data-bs-target="#deleteUserModal{{ $user->id }}">
                                            <i class="bx bx-trash me-0"></i>
                                        </button>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">No user records found.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @foreach($users as $user)
    <!-- View User Modal -->
    <div class="modal fade" id="viewUserModal{{ $user->id }}" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">User Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p><strong>Name:</strong> {{ $user->first_name }} {{ $user->last_name }}</p>
                    <p><strong>Email:</strong> {{ $user->email ?? 'N/A' }}</p>
                    <p><strong>Phone:</strong> {{ $user->phone_number ?? 'N/A' }}</p>
                    <p><strong>Status:</strong> <span class="badge {{ $user->status === 'active' ? 'bg-success' : 'bg-secondary' }}">{{ ucfirst($user->status ?? 'inactive') }}</span></p>
                    <p><strong>Joined:</strong> {{ $user->created_at ? $user->created_at->format('d M Y H:i') : 'N/A' }}</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Modal -->
    <div class="modal fade" id="deleteUserModal{{ $user->id }}" tabindex="-1">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <form action="{{ route('user.destroy', $user->id) }}" method="POST">
                    @csrf
                    @method('DELETE')
                    <div class="modal-header">
                        <h5 class="modal-title">Delete User</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p>Are you sure you want to delete <strong>{{ $user->first_name }} {{ $user->last_name }}</strong>?</p>
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

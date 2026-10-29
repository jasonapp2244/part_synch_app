@extends('layouts.admin')
@section('title', 'Services')

@section('content')
    <div class="page-wrapper">
        <div class="page-content">
            <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
                <div class="breadcrumb-title pe-3">Services</div>
                <div class="ps-3">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0 p-0">
                            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}"><i class="bx bx-home-alt"></i></a></li>
                            <li class="breadcrumb-item active" aria-current="page">All services</li>
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

            <div class="d-flex align-items-center mb-2">
                <h6 class="mb-0 text-uppercase">Services vendors offer</h6>
                @include('admin.partials.export_buttons', ['list' => 'services', 'class' => 'ms-auto'])
            </div>
            <p class="text-secondary small mb-2">Active services show on the seller's page and, when linked, on the product page. Switching one off hides it from customers; open requests stay as they are.</p>
            <hr />

            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="example2" class="table table-striped table-bordered">
                            <thead>
                                <tr>
                                    <th>S.no</th>
                                    <th>Service</th>
                                    <th>Vendor</th>
                                    <th>Linked product</th>
                                    <th>Price</th>
                                    <th>Type</th>
                                    <th>Requests</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($services as $index => $service)
                                    <tr>
                                        <td>{{ $index + 1 }}</td>
                                        <td>
                                            {{ $service->title }}
                                            @if($service->description)
                                                <small class="d-block text-secondary">{{ Str::limit($service->description, 60) }}</small>
                                            @endif
                                        </td>
                                        <td>{{ $service->user?->sellerName() ?? 'Deleted vendor' }}</td>
                                        <td>{{ $service->product?->name ?? '—' }}</td>
                                        <td>{{ $service->price !== null ? number_format((float) $service->price, 2) : '—' }}</td>
                                        <td>
                                            {{ collect([$service->service_types, $service->duration_type, $service->service_mode])->filter()->map(fn ($v) => ucfirst(str_replace('_', ' ', $v)))->join(' · ') ?: '—' }}
                                        </td>
                                        <td>{{ $service->requests_count }}</td>
                                        <td>
                                            <span class="badge bg-{{ $service->status === 'active' ? 'success' : 'secondary' }} text-capitalize">{{ $service->status }}</span>
                                        </td>
                                        <td>
                                            <form action="{{ route('admin.services.toggle', $service->id) }}" method="POST">
                                                @csrf
                                                <button type="submit" class="btn btn-sm {{ $service->status === 'active' ? 'btn-outline-danger' : 'btn-outline-success' }}">
                                                    {{ $service->status === 'active' ? 'Switch off' : 'Switch on' }}
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="text-center">No services yet.</td>
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

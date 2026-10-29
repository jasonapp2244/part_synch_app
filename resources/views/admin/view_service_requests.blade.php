@extends('layouts.admin')
@section('title', 'Service Requests')

@section('content')
    @php
        $colours = ['requested' => 'warning', 'accepted' => 'info', 'in_progress' => 'primary', 'completed' => 'success', 'declined' => 'secondary', 'cancelled' => 'danger'];
        $labels = \App\Models\ServiceRequest::STATUS_LABELS;
    @endphp
    <div class="page-wrapper">
        <div class="page-content">
            <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
                <div class="breadcrumb-title pe-3">Services</div>
                <div class="ps-3">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0 p-0">
                            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}"><i class="bx bx-home-alt"></i></a></li>
                            <li class="breadcrumb-item active" aria-current="page">Service requests</li>
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

            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @if($errors->any())
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    {{ $errors->first() }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            <div class="row row-cols-2 row-cols-md-3 row-cols-xl-6 mb-3">
                @foreach($colours as $key => $colour)
                    <div class="col">
                        <a href="{{ route('admin.service.requests', ['status' => $key]) }}" class="text-decoration-none">
                            <div class="card radius-10 border-start border-0 border-4 border-{{ $colour }}">
                                <div class="card-body">
                                    <p class="mb-1 text-secondary">{{ $labels[$key] }}</p>
                                    <h4 class="my-1 text-{{ $colour }}">{{ $counts[$key] }}</h4>
                                </div>
                            </div>
                        </a>
                    </div>
                @endforeach
            </div>

            <div class="d-flex align-items-center mb-2">
                <h6 class="mb-0 text-uppercase">Service requests</h6>
                @if($status)
                    <a href="{{ route('admin.service.requests') }}" class="btn btn-sm btn-outline-secondary ms-3">Clear filter ({{ $labels[$status] }})</a>
                @endif
                @include('admin.partials.export_buttons', ['list' => 'service-requests', 'class' => 'ms-auto'])
            </div>
            <p class="text-secondary small mb-2">Customers request a vendor's service in the app; the vendor accepts with a date, does the work and marks it completed. Payment is arranged outside the app.</p>
            <hr />

            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="example2" class="table table-striped table-bordered">
                            <thead>
                                <tr>
                                    <th>Request</th>
                                    <th>Service</th>
                                    <th>Customer</th>
                                    <th>Vendor</th>
                                    <th>Date</th>
                                    <th>Price</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($requests as $item)
                                    <tr>
                                        <td>
                                            {{ $item->request_number }}
                                            <small class="d-block text-secondary">{{ $item->created_at?->format('d M Y H:i') }}</small>
                                        </td>
                                        <td>{{ $item->title }}</td>
                                        <td>{{ $item->customer?->name ?? 'Deleted user' }}</td>
                                        <td>{{ $item->vendor?->sellerName() ?? 'Deleted vendor' }}</td>
                                        <td>
                                            @if($item->scheduled_at)
                                                {{ $item->scheduled_at->format('d M Y H:i') }}
                                            @elseif($item->preferred_date)
                                                <span class="text-secondary">Wants {{ $item->preferred_date->format('d M Y') }}</span>
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td>
                                            @php $amount = $item->quoted_price ?? $item->price; @endphp
                                            {{ $amount !== null ? number_format((float) $amount, 2) : '—' }}
                                            @if($item->paid_outside)
                                                <small class="d-block text-success">Paid outside the app</small>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="badge bg-{{ $colours[$item->status] ?? 'secondary' }}">{{ $item->statusLabel() }}</span>
                                            @if($item->cancelled_by)
                                                <small class="d-block text-secondary">by {{ $item->cancelled_by }}</small>
                                            @endif
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-outline-primary"
                                                data-bs-toggle="modal" data-bs-target="#serviceRequest{{ $item->id }}">
                                                <i class="bx bx-show"></i> View
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center">No service requests found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Modals live outside the table so DataTables does not reorder them. --}}
    @foreach($requests as $item)
        <div class="modal fade" id="serviceRequest{{ $item->id }}" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ $item->request_number }} · {{ $item->statusLabel() }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <dl class="row mb-3">
                            <dt class="col-sm-3">Service</dt>
                            <dd class="col-sm-9">{{ $item->title }}</dd>
                            <dt class="col-sm-3">Customer</dt>
                            <dd class="col-sm-9">{{ $item->customer?->name ?? 'Deleted user' }} · {{ $item->customer?->email }} · {{ $item->phone }}</dd>
                            <dt class="col-sm-3">Vendor</dt>
                            <dd class="col-sm-9">{{ $item->vendor?->sellerName() ?? 'Deleted vendor' }} · {{ $item->vendor?->email }}</dd>
                            <dt class="col-sm-3">Address</dt>
                            <dd class="col-sm-9">{{ $item->address }}</dd>
                            <dt class="col-sm-3">Preferred date</dt>
                            <dd class="col-sm-9">{{ $item->preferred_date?->format('d M Y') ?? '—' }}</dd>
                            <dt class="col-sm-3">Booked for</dt>
                            <dd class="col-sm-9">{{ $item->scheduled_at?->format('d M Y H:i') ?? '—' }}</dd>
                            <dt class="col-sm-3">Price</dt>
                            <dd class="col-sm-9">
                                {{ $item->price !== null ? number_format((float) $item->price, 2) : '—' }}
                                @if($item->quoted_price !== null)
                                    (quoted {{ number_format((float) $item->quoted_price, 2) }})
                                @endif
                                · {{ $item->paid_outside ? 'paid outside the app' : 'not paid yet' }}
                            </dd>
                            <dt class="col-sm-3">Customer note</dt>
                            <dd class="col-sm-9">{{ $item->note ?: '—' }}</dd>
                            @if($item->decline_reason)
                                <dt class="col-sm-3">Declined because</dt>
                                <dd class="col-sm-9">{{ $item->decline_reason }}</dd>
                            @endif
                            @if($item->cancelled_by)
                                <dt class="col-sm-3">Cancelled by</dt>
                                <dd class="col-sm-9 text-capitalize">{{ $item->cancelled_by }}{{ $item->cancel_reason ? ': ' . $item->cancel_reason : '' }}</dd>
                            @endif
                        </dl>

                        @if($item->isOpen())
                            <form action="{{ route('admin.service.requests.cancel', $item->id) }}" method="POST"
                                  onsubmit="return confirm('Cancel {{ $item->request_number }}? The customer and the vendor will be told.');">
                                @csrf
                                <div class="mb-3">
                                    <label class="form-label">Reason (sent to both sides)</label>
                                    <textarea name="cancel_reason" class="form-control" rows="2" maxlength="500" required></textarea>
                                </div>
                                <button type="submit" class="btn btn-danger"><i class="bx bx-x-circle"></i> Cancel request</button>
                            </form>
                        @endif
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
    @endforeach
@endsection

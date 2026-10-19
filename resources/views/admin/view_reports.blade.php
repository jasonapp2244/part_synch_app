@extends('layouts.admin')
@section('title', 'Reported Content')

@section('content')
    <div class="page-wrapper">
        <div class="page-content">
            <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
                <div class="breadcrumb-title pe-3">Moderation</div>
                <div class="ps-3">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0 p-0">
                            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}"><i class="bx bx-home-alt"></i></a></li>
                            <li class="breadcrumb-item active" aria-current="page">Reported Content</li>
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

            <div class="row row-cols-1 row-cols-md-2 row-cols-xl-4 mb-3">
                @foreach(['pending' => 'warning', 'reviewing' => 'info', 'actioned' => 'success', 'dismissed' => 'secondary'] as $key => $colour)
                    <div class="col">
                        <a href="{{ route('admin.reports', ['status' => $key]) }}" class="text-decoration-none">
                            <div class="card radius-10 border-start border-0 border-4 border-{{ $colour }}">
                                <div class="card-body">
                                    <p class="mb-1 text-secondary text-capitalize">{{ $key }}</p>
                                    <h4 class="my-1 text-{{ $colour }}">{{ $counts[$key] }}</h4>
                                </div>
                            </div>
                        </a>
                    </div>
                @endforeach
            </div>

            <div class="d-flex align-items-center mb-2">
                <h6 class="mb-0 text-uppercase">Reported Content</h6>
                @if($status)
                    <a href="{{ route('admin.reports') }}" class="btn btn-sm btn-outline-secondary ms-3">Clear filter ({{ $status }})</a>
                @endif
                @include('admin.partials.export_buttons', ['list' => 'reports', 'class' => 'ms-auto'])
            </div>
            <hr />

            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="example2" class="table table-striped table-bordered">
                            <thead>
                                <tr>
                                    <th>S.no</th>
                                    <th>Type</th>
                                    <th>Reported item</th>
                                    <th>Reason</th>
                                    <th>Reported by</th>
                                    <th>When</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($reports as $index => $report)
                                    <tr>
                                        <td>{{ $index + 1 }}</td>
                                        <td><span class="badge bg-light text-dark text-capitalize">{{ $report->reportable_type }}</span></td>
                                        <td>
                                            {{ $report->subject_label }}
                                            <small class="d-block text-secondary">#{{ $report->reportable_id }}</small>
                                        </td>
                                        <td>
                                            {{ ucwords(str_replace('_', ' ', $report->reason)) }}
                                            @if($report->details)
                                                <small class="d-block text-secondary">{{ Str::limit($report->details, 60) }}</small>
                                            @endif
                                        </td>
                                        <td>
                                            {{ $report->reporter ? trim($report->reporter->first_name . ' ' . $report->reporter->last_name) : 'Deleted user' }}
                                        </td>
                                        <td>{{ $report->created_at?->format('d M Y H:i') }}</td>
                                        <td>
                                            @php
                                                $badge = ['pending' => 'warning', 'reviewing' => 'info', 'actioned' => 'success', 'dismissed' => 'secondary'][$report->status] ?? 'secondary';
                                            @endphp
                                            <span class="badge bg-{{ $badge }} text-capitalize">{{ $report->status }}</span>
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-outline-primary"
                                                data-bs-toggle="modal" data-bs-target="#reviewReport{{ $report->id }}">
                                                <i class="bx bx-edit"></i> Review
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center">No reports found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Review modals live outside the table so DataTables does not reorder them. --}}
    @foreach($reports as $report)
        <div class="modal fade" id="reviewReport{{ $report->id }}" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Review report #{{ $report->id }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <dl class="row mb-3">
                            <dt class="col-sm-3">Type</dt>
                            <dd class="col-sm-9 text-capitalize">{{ $report->reportable_type }} #{{ $report->reportable_id }}</dd>
                            <dt class="col-sm-3">Content</dt>
                            <dd class="col-sm-9">{{ $report->subject_label }}</dd>
                            <dt class="col-sm-3">Reason</dt>
                            <dd class="col-sm-9">{{ ucwords(str_replace('_', ' ', $report->reason)) }}</dd>
                            <dt class="col-sm-3">Details</dt>
                            <dd class="col-sm-9">{{ $report->details ?: '—' }}</dd>
                            @if($report->reviewed_at)
                                <dt class="col-sm-3">Last reviewed</dt>
                                <dd class="col-sm-9">{{ $report->reviewed_at->format('d M Y H:i') }}</dd>
                            @endif
                        </dl>

                        <form action="{{ route('admin.reports.update', $report->id) }}" method="POST">
                            @csrf
                            @method('PUT')
                            <div class="mb-3">
                                <label class="form-label">Decision</label>
                                <select name="status" class="form-select" required>
                                    @foreach(['pending', 'reviewing', 'actioned', 'dismissed'] as $option)
                                        <option value="{{ $option }}" @selected($report->status === $option)>{{ ucfirst($option) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Internal note</label>
                                <textarea name="admin_note" class="form-control" rows="3">{{ $report->admin_note }}</textarea>
                            </div>
                            <button type="submit" class="btn btn-primary">Save decision</button>
                        </form>
                    </div>
                    <div class="modal-footer justify-content-between">
                        <form action="{{ route('admin.reports.takedown', $report->id) }}" method="POST"
                              onsubmit="return confirm('Remove this content from the marketplace and mark the report actioned?');">
                            @csrf
                            <button type="submit" class="btn btn-danger">
                                <i class="bx bx-trash"></i> Take content down
                            </button>
                        </form>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
    @endforeach
@endsection

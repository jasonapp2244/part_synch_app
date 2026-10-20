@extends('layouts.admin')

@section('title', 'Boost Requests')

@php
    $tz = config('app.timezone');
    $statusBadge = [
        'pending' => 'bg-warning text-dark',
        'scheduled' => 'bg-info text-dark',
        'active' => 'bg-success',
        'expired' => 'bg-secondary',
        'rejected' => 'bg-danger',
        'cancelled' => 'bg-dark',
    ];
    $money = fn ($boost) => strtoupper($boost->currency ?? 'usd') . ' ' . number_format((float) $boost->amount, 2);
    $vendorName = fn ($boost) => trim(($boost->vendor?->first_name ?? '') . ' ' . ($boost->vendor?->last_name ?? '')) ?: ($boost->vendor?->email ?? 'Deleted vendor');
@endphp

@section('content')
    <div class="page-wrapper">
        <div class="page-content">
            <div class="page-breadcrumb d-flex flex-wrap align-items-center gap-2 mb-3">
                <div class="breadcrumb-title pe-3 d-none d-sm-block">Boost Requests</div>
                <div class="ps-3 d-none d-sm-block">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0 p-0">
                            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}"><i class="bx bx-home-alt"></i></a></li>
                            <li class="breadcrumb-item active" aria-current="page">Boost Requests</li>
                        </ol>
                    </nav>
                </div>
                <div class="ms-auto d-flex gap-2">
                    <a href="{{ route('boost.packages') }}" class="btn btn-outline-secondary"><i class="bx bx-cog"></i> Packages &amp; slots</a>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createBoost">
                        <i class="bx bx-plus"></i> New boost for a vendor
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

            {{-- Summary --}}
            <div class="row row-cols-2 row-cols-lg-4 g-3 mb-3">
                <div class="col"><div class="card radius-10 mb-0 h-100"><div class="card-body">
                    <p class="mb-1 text-secondary">Waiting for approval</p>
                    <h4 class="mb-0 {{ $stats['pending'] ? 'text-danger' : '' }}">{{ $stats['pending'] }}</h4>
                </div></div></div>
                <div class="col"><div class="card radius-10 mb-0 h-100"><div class="card-body">
                    <p class="mb-1 text-secondary">Live now</p>
                    <h4 class="mb-0 text-success">{{ $stats['live'] }}</h4>
                </div></div></div>
                <div class="col"><div class="card radius-10 mb-0 h-100"><div class="card-body">
                    <p class="mb-1 text-secondary">Scheduled</p>
                    <h4 class="mb-0">{{ $stats['scheduled'] }}</h4>
                </div></div></div>
                <div class="col"><div class="card radius-10 mb-0 h-100"><div class="card-body">
                    <p class="mb-1 text-secondary">Received this month</p>
                    <h4 class="mb-0">{{ number_format($stats['revenue_month'], 2) }}</h4>
                </div></div></div>
            </div>

            {{-- Slot usage per position --}}
            <div class="card mb-3">
                <div class="card-body py-3">
                    <div class="d-flex flex-wrap gap-4 align-items-center">
                        <strong class="me-2">Slots in use now</strong>
                        @foreach($positions as $position)
                            @php
                                $used = $slotsInUse[$position->id] ?? 0;
                                $pct = $position->display_limit ? min(100, round($used / $position->display_limit * 100)) : 0;
                            @endphp
                            <div style="min-width: 170px;" class="{{ $position->status ? '' : 'opacity-50' }}">
                                <div class="d-flex justify-content-between small">
                                    <span>{{ $position->name }}@unless($position->status) (off)@endunless</span>
                                    <span>{{ $used }} / {{ $position->display_limit }}</span>
                                </div>
                                <div class="progress" style="height: 6px;">
                                    <div class="progress-bar {{ $pct >= 100 ? 'bg-danger' : 'bg-success' }}" style="width: {{ $pct }}%"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <p class="small text-muted mb-0 mt-2">
                        How it works: a vendor requests a boost in the app (no payment in the app). Send them an invoice or payment link by email,
                        then <strong>Approve</strong> and record the payment. If the section is full, the boost is scheduled for the next free date
                        automatically, and the vendor is told when it starts and three days before it ends. Times are {{ $tz }}.
                    </p>
                </div>
            </div>

            <ul class="nav nav-tabs" role="tablist">
                <li class="nav-item">
                    <button class="nav-link {{ $tab === 'pending' ? 'active' : '' }}" data-bs-toggle="tab" data-bs-target="#tab-pending" type="button">
                        Pending <span class="badge {{ $pending->count() ? 'bg-danger' : 'bg-secondary' }}">{{ $pending->count() }}</span>
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link {{ $tab === 'current' ? 'active' : '' }}" data-bs-toggle="tab" data-bs-target="#tab-current" type="button">
                        Live &amp; scheduled <span class="badge bg-secondary">{{ $current->count() }}</span>
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link {{ $tab === 'history' ? 'active' : '' }}" data-bs-toggle="tab" data-bs-target="#tab-history" type="button">
                        History
                    </button>
                </li>
            </ul>

            <div class="card border-top-0" style="border-top-left-radius: 0; border-top-right-radius: 0;">
                <div class="card-body tab-content">

                    {{-- Pending --}}
                    <div class="tab-pane fade {{ $tab === 'pending' ? 'show active' : '' }}" id="tab-pending">
                        <div class="d-flex justify-content-end mb-2">
                            @include('admin.partials.export_buttons', ['list' => 'boost-requests', 'query' => ['tab' => 'pending']])
                        </div>
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered align-middle boost-table">
                                <thead>
                                    <tr>
                                        <th>Vendor</th>
                                        <th>Products</th>
                                        <th>Package / position</th>
                                        <th>Quoted</th>
                                        <th>Requested</th>
                                        <th>Earliest start</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($pending as $boost)
                                        @php $next = $nextStart[$boost->id] ?? null; @endphp
                                        <tr>
                                            <td>
                                                <div class="fw-semibold">{{ $vendorName($boost) }}</div>
                                                <div class="small"><a href="mailto:{{ $boost->vendor?->email }}">{{ $boost->vendor?->email }}</a></div>
                                                @if($boost->vendor?->phone_number)<div class="small text-muted">{{ $boost->vendor->phone_number }}</div>@endif
                                            </td>
                                            <td>@include('admin.partials.boost_products', ['boost' => $boost])</td>
                                            <td>
                                                {{ $boost->package?->name }} <span class="text-muted small">({{ $boost->package?->duration_days }} days)</span><br>
                                                <span class="text-muted">{{ $boost->position?->name }}</span>
                                                @if($boost->vendor_note)
                                                    <div class="small fst-italic mt-1">“{{ $boost->vendor_note }}”</div>
                                                @endif
                                            </td>
                                            <td data-order="{{ $boost->amount }}">{{ $money($boost) }}</td>
                                            <td data-order="{{ $boost->created_at?->timestamp }}">
                                                {{ $boost->created_at?->format('M j, Y') }}
                                                <div class="small text-muted">{{ $boost->created_at?->diffForHumans() }}</div>
                                            </td>
                                            <td>
                                                @if(! $next)
                                                    <span class="badge bg-danger">Too many products for this position</span>
                                                @elseif($next->lte(now()->addMinute()))
                                                    <span class="badge bg-success">Now</span>
                                                @else
                                                    <span class="badge bg-info text-dark">{{ $next->format('M j, Y H:i') }}</span>
                                                    <div class="small text-muted">section full until then</div>
                                                @endif
                                            </td>
                                            <td class="text-nowrap">
                                                <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#approve{{ $boost->id }}" @disabled(! $next)>
                                                    <i class="bx bx-check"></i> Approve
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#reject{{ $boost->id }}">
                                                    <i class="bx bx-x"></i> Reject
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @if($pending->isEmpty())
                            <p class="text-center text-muted mb-0">No requests waiting. New requests from the app appear here and are emailed to the admin address.</p>
                        @endif
                    </div>

                    {{-- Live & scheduled --}}
                    <div class="tab-pane fade {{ $tab === 'current' ? 'show active' : '' }}" id="tab-current">
                        <div class="d-flex justify-content-end mb-2">
                            @include('admin.partials.export_buttons', ['list' => 'boost-requests', 'query' => ['tab' => 'current']])
                        </div>
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered align-middle boost-table">
                                <thead>
                                    <tr>
                                        <th>Vendor</th>
                                        <th>Products</th>
                                        <th>Position</th>
                                        <th>Status</th>
                                        <th>Runs</th>
                                        <th>Payment</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($current as $boost)
                                        <tr>
                                            <td>
                                                <div class="fw-semibold">{{ $vendorName($boost) }}</div>
                                                <div class="small text-muted">{{ $boost->vendor?->email }}</div>
                                            </td>
                                            <td>@include('admin.partials.boost_products', ['boost' => $boost])</td>
                                            <td>{{ $boost->position?->name }}<div class="small text-muted">{{ $boost->package?->name }}</div></td>
                                            <td><span class="badge {{ $statusBadge[$boost->status] ?? 'bg-secondary' }}">{{ ucfirst($boost->status) }}</span></td>
                                            <td data-order="{{ $boost->start_date?->timestamp }}">
                                                {{ $boost->start_date?->format('M j, Y H:i') }}<br>
                                                <span class="text-muted">to {{ $boost->end_date?->format('M j, Y H:i') }}</span>
                                            </td>
                                            <td>
                                                {{ $money($boost) }}
                                                <div class="small text-muted">
                                                    {{ \App\Models\VendorBoost::methodLabel($boost->payment_method) }}
                                                    @if($boost->payment_reference) · {{ $boost->payment_reference }}@endif
                                                </div>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#cancel{{ $boost->id }}">
                                                    Cancel
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @if($current->isEmpty())
                            <p class="text-center text-muted mb-0">No boosts are live or scheduled.</p>
                        @endif
                    </div>

                    {{-- History --}}
                    <div class="tab-pane fade {{ $tab === 'history' ? 'show active' : '' }}" id="tab-history">
                        <div class="d-flex justify-content-end mb-2">
                            @include('admin.partials.export_buttons', ['list' => 'boost-requests', 'query' => ['tab' => 'history']])
                        </div>
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered align-middle boost-table">
                                <thead>
                                    <tr>
                                        <th>Vendor</th>
                                        <th>Products</th>
                                        <th>Package / position</th>
                                        <th>Status</th>
                                        <th>Amount</th>
                                        <th>Updated</th>
                                        <th>Note</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($history as $boost)
                                        <tr>
                                            <td>{{ $vendorName($boost) }}</td>
                                            <td>@include('admin.partials.boost_products', ['boost' => $boost])</td>
                                            <td>{{ $boost->package?->name }}<div class="small text-muted">{{ $boost->position?->name }}</div></td>
                                            <td>
                                                <span class="badge {{ $statusBadge[$boost->status] ?? 'bg-secondary' }}">{{ ucfirst($boost->status) }}</span>
                                                @if($boost->payment_status === 'refunded')<span class="badge bg-light text-dark">Refunded</span>@endif
                                            </td>
                                            <td data-order="{{ $boost->amount }}">{{ $money($boost) }}</td>
                                            <td data-order="{{ $boost->updated_at?->timestamp }}">{{ $boost->updated_at?->format('M j, Y') }}</td>
                                            <td class="small">{{ $boost->rejection_reason }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @if($history->isEmpty())
                            <p class="text-center text-muted mb-0">Nothing here yet.</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Approve / reject modals for pending requests --}}
    @foreach($pending as $boost)
        @php $next = $nextStart[$boost->id] ?? null; @endphp
        <div class="modal fade" id="approve{{ $boost->id }}" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form action="{{ route('admin.boosts.approve', $boost->id) }}" method="POST">
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title">Approve boost — {{ $vendorName($boost) }}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <p class="small text-muted">
                                {{ $boost->package?->name }} in {{ $boost->position?->name }} for {{ $boost->boostedProducts->count() }} product(s).
                                Approve once the payment has been received.
                            </p>
                            @include('admin.partials.boost_payment_fields', ['amount' => $boost->amount, 'start' => $next, 'modalId' => 'approve' . $boost->id])
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-success"><i class="bx bx-check"></i> Approve</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="modal fade" id="reject{{ $boost->id }}" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form action="{{ route('admin.boosts.reject', $boost->id) }}" method="POST">
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title">Reject request</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <label class="form-label" for="reason{{ $boost->id }}">Reason (sent to the vendor)</label>
                            <textarea class="form-control" id="reason{{ $boost->id }}" name="reason" rows="3" maxlength="500" required
                                placeholder="e.g. Payment not received within 7 days"></textarea>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-danger">Reject</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endforeach

    {{-- Cancel modals for live / scheduled boosts --}}
    @foreach($current as $boost)
        <div class="modal fade" id="cancel{{ $boost->id }}" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form action="{{ route('admin.boosts.cancel', $boost->id) }}" method="POST">
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title">Cancel boost — {{ $vendorName($boost) }}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <p class="small text-muted">The products stop showing as boosted straight away and the slots are freed.</p>
                            <div class="mb-3">
                                <label class="form-label" for="cancelReason{{ $boost->id }}">Reason (sent to the vendor)</label>
                                <textarea class="form-control" id="cancelReason{{ $boost->id }}" name="reason" rows="2" maxlength="500"></textarea>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="refunded" value="1" id="refunded{{ $boost->id }}">
                                <label class="form-check-label" for="refunded{{ $boost->id }}">{{ $boost->payment_method === 'stripe' ? 'Refund the card payment through Stripe now' : 'The payment was refunded' }}</label>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-danger">Cancel boost</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endforeach

    {{-- Create a boost for a vendor (sold directly) --}}
    <div class="modal fade" id="createBoost" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form action="{{ route('admin.boosts.store') }}" method="POST">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title">New boost for a vendor</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p class="small text-muted">For boosts sold directly (phone, email, in person). It is approved straight away, or scheduled if the section is full.</p>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label" for="cbVendor">Vendor</label>
                                <select class="form-select" id="cbVendor" name="vendor_id" required>
                                    <option value="">Choose a vendor…</option>
                                    @foreach($vendors as $vendor)
                                        <option value="{{ $vendor->id }}" @selected(old('vendor_id') == $vendor->id)>
                                            {{ trim($vendor->first_name . ' ' . $vendor->last_name) ?: $vendor->email }} — {{ $vendor->email }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label" for="cbPackage">Package</label>
                                <select class="form-select" id="cbPackage" name="package_id" required>
                                    @foreach($packages as $package)
                                        <option value="{{ $package->id }}" data-price="{{ $package->price }}" data-limit="{{ $package->product_limit }}" @selected(old('package_id') == $package->id)>
                                            {{ $package->name }} ({{ $package->duration_days }}d, up to {{ $package->product_limit }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label" for="cbPosition">Position</label>
                                <select class="form-select" id="cbPosition" name="position_id" required>
                                    @foreach($activePositions as $position)
                                        <option value="{{ $position->id }}" data-multiplier="{{ $position->price_multiplier }}" @selected(old('position_id') == $position->id)>
                                            {{ $position->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Products</label>
                            <div id="cbProducts" class="border rounded p-2" style="max-height: 180px; overflow-y: auto;">
                                <span class="text-muted small">Choose a vendor first.</span>
                            </div>
                        </div>
                        @include('admin.partials.boost_payment_fields', ['amount' => null, 'start' => null, 'prefix' => 'cb', 'modalId' => 'createBoost'])
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Create &amp; approve</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
<script>
    $(function () {
        $('.boost-table').each(function () {
            if ($(this).find('tbody tr').length) {
                $(this).DataTable({ lengthChange: false, pageLength: 25, order: [], autoWidth: false });
            }
        });

        // Re-open the form that failed validation.
        var reopen = @json(session('open_modal'));
        if (reopen && document.getElementById(reopen)) {
            bootstrap.Modal.getOrCreateInstance(document.getElementById(reopen)).show();
        }

        // Create form: load the chosen vendor's products and suggest the price.
        var productsUrl = @json(route('admin.boosts.vendor.products', ['vendorId' => '__ID__']));
        var oldProducts = @json(array_map('intval', (array) old('product_ids', [])));
        var $vendor = $('#cbVendor'), $box = $('#cbProducts');

        function loadProducts() {
            var id = $vendor.val();
            if (!id) { $box.html('<span class="text-muted small">Choose a vendor first.</span>'); return; }
            $box.html('<span class="text-muted small">Loading…</span>');
            $.getJSON(productsUrl.replace('__ID__', id)).done(function (rows) {
                if (!rows.length) { $box.html('<span class="text-danger small">This vendor has no active products.</span>'); return; }
                $box.empty();
                rows.forEach(function (p) {
                    var cid = 'cbp' + p.id;
                    var $row = $('<div class="form-check"></div>');
                    $('<input class="form-check-input" type="checkbox" name="product_ids[]">')
                        .attr({ id: cid, value: p.id }).prop('checked', oldProducts.indexOf(p.id) !== -1).appendTo($row);
                    $('<label class="form-check-label"></label>').attr('for', cid).text(p.name).appendTo($row);
                    $box.append($row);
                });
            }).fail(function () {
                $box.html('<span class="text-danger small">Could not load products. Try again.</span>');
            });
        }

        function suggestAmount() {
            var price = parseFloat($('#cbPackage option:selected').data('price')) || 0;
            var mult = parseFloat($('#cbPosition option:selected').data('multiplier')) || 1;
            var $amount = $('#cbAmount');
            if ($amount.data('touched')) { return; }
            $amount.val((Math.round(price * mult * 100) / 100).toFixed(2));
        }

        $vendor.on('change', loadProducts);
        $('#cbPackage, #cbPosition').on('change', suggestAmount);
        $('#cbAmount').on('input', function () { $(this).data('touched', true); });
        if ($vendor.val()) { loadProducts(); }
        if (!$('#cbAmount').val()) { suggestAmount(); }
    });
</script>
@endsection

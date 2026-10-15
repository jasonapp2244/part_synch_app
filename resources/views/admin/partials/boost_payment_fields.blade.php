{{-- Payment received outside the app + start date, shared by approve and create. --}}
@php
    $prefix = $prefix ?? 'ap' . ($boost->id ?? '');
    // Old input belongs to the one form that failed validation, not to every modal.
    $useOld = session('open_modal') === $modalId;
    $old = fn ($key, $default = null) => $useOld ? old($key, $default) : $default;
    $startValue = $old('start_date', $start ? $start->copy()->max(now())->format('Y-m-d\TH:i') : now()->format('Y-m-d\TH:i'));
@endphp
<div class="row">
    <div class="col-md-6 mb-3">
        <label class="form-label" for="{{ $prefix }}Method">Payment received by</label>
        <select class="form-select" id="{{ $prefix }}Method" name="payment_method" required>
            @foreach(\App\Models\VendorBoost::PAYMENT_METHODS as $value => $label)
                <option value="{{ $value }}" @selected($old('payment_method', 'bank_transfer') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-6 mb-3">
        <label class="form-label" for="{{ $prefix }}Amount">Amount received</label>
        <input type="number" class="form-control" id="{{ $prefix }}Amount" name="amount" step="0.01" min="0" required
            value="{{ $old('amount', $amount !== null ? number_format((float) $amount, 2, '.', '') : '') }}">
    </div>
    <div class="col-md-6 mb-3">
        <label class="form-label" for="{{ $prefix }}Ref">Payment reference</label>
        <input type="text" class="form-control" id="{{ $prefix }}Ref" name="payment_reference" maxlength="255"
            value="{{ $old('payment_reference') }}" placeholder="Invoice no., transaction ID…">
    </div>
    <div class="col-md-6 mb-3">
        <label class="form-label" for="{{ $prefix }}Start">Start <span class="text-muted small">({{ config('app.timezone') }})</span></label>
        <input type="datetime-local" class="form-control" id="{{ $prefix }}Start" name="start_date" value="{{ $startValue }}">
        <div class="form-text">Defaults to the earliest free slot. A full section is never overbooked.</div>
    </div>
    <div class="col-12 mb-0">
        <label class="form-label" for="{{ $prefix }}Note">Internal note</label>
        <textarea class="form-control" id="{{ $prefix }}Note" name="admin_note" rows="2" maxlength="1000">{{ $old('admin_note') }}</textarea>
    </div>
</div>

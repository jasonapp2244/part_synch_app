{{--
    PDF / Excel download buttons for one admin list.
    $list   key from App\Support\AdminExports
    $query  optional filters to send; defaults to the page's own ?status= / ?tab=
    $onDark true on the coloured card headers
--}}
@php
    $exportQuery = $query ?? \Illuminate\Support\Arr::only(request()->query(), ['status', 'tab']);
    $exportBtn = ($onDark ?? false) ? 'btn-light' : 'btn-outline-secondary';
@endphp
<div class="btn-group btn-group-sm {{ $class ?? '' }}" role="group" aria-label="Download this list">
    <a href="{{ route('admin.export', ['list' => $list, 'format' => 'pdf'] + $exportQuery) }}" class="btn {{ $exportBtn }}" title="Download as PDF">
        <i class="bx bxs-file-pdf"></i> PDF
    </a>
    <a href="{{ route('admin.export', ['list' => $list, 'format' => 'xlsx'] + $exportQuery) }}" class="btn {{ $exportBtn }}" title="Download as Excel">
        <i class="bx bx-spreadsheet"></i> Excel
    </a>
</div>

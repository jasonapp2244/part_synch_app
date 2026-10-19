{{-- Product names (with thumbnail) of one boost. --}}
@foreach($boost->boostedProducts as $item)
    @php $image = $item->product?->productImages?->first()?->url(); @endphp
    <div class="d-flex align-items-center gap-2 {{ $loop->last ? '' : 'mb-1' }}">
        @if($image)
            <img src="{{ $image }}" alt="" width="28" height="28" class="rounded border" style="object-fit: cover;" loading="lazy" onerror="this.remove()">
        @endif
        <span class="small">{{ $item->product?->name ?? 'Deleted product #' . $item->product_id }}</span>
    </div>
@endforeach

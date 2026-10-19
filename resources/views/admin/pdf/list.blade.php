@php
    $logoPath = public_path('admin/images/favicon-32x32.png');
    $logo = is_file($logoPath) ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath)) : null;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @page { margin: 24px 26px 34px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #222; }
        .header { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        .header td { vertical-align: middle; }
        .header h1 { margin: 0; font-size: 16px; color: #111827; }
        .muted { color: #6b7280; }
        .data { width: 100%; border-collapse: collapse; }
        .data th { background: #111827; color: #fff; text-align: left; padding: 5px 6px; font-size: 8.5px; }
        .data td { padding: 4px 6px; border-bottom: 1px solid #e5e7eb; vertical-align: top; word-wrap: break-word; }
        .data tr:nth-child(even) td { background: #f9fafb; }
        .num { text-align: right; white-space: nowrap; }
        .footer { position: fixed; bottom: -20px; left: 0; right: 0; font-size: 8px; color: #9ca3af; text-align: center; }
    </style>
</head>
<body>
    <div class="footer">{{ $siteName }} &middot; {{ $title }} &middot; generated {{ now()->format('d M Y H:i') }}</div>

    <table class="header">
        <tr>
            <td>
                <h1>{{ $title }}</h1>
                <span class="muted">
                    {{ count($rows) }} {{ \Illuminate\Support\Str::plural('record', count($rows)) }}
                    @if($filter) &middot; {{ $filter }}@endif
                    &middot; {{ now()->format('d M Y H:i') }}
                </span>
            </td>
            <td style="text-align: right;">
                @if($logo)<img src="{{ $logo }}" alt="" style="height: 36px; vertical-align: middle; margin-right: 6px;">@endif
                <strong style="font-size: 13px;">{{ $siteName }}</strong>
            </td>
        </tr>
    </table>

    <table class="data">
        <thead>
            <tr>
                @foreach($headings as $heading)
                    <th>{{ $heading }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $row)
                <tr>
                    @foreach($row as $cell)
                        @if(is_float($cell))
                            <td class="num">{{ number_format($cell, 2) }}</td>
                        @elseif(is_int($cell))
                            <td class="num">{{ $cell }}</td>
                        @else
                            <td>{{ \Illuminate\Support\Str::limit((string) $cell, 160) }}</td>
                        @endif
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="{{ count($headings) }}" class="muted" style="text-align: center;">No records.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subject }}</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; background: #f4f4f4; margin: 0; padding: 20px;">
    <div style="max-width: 600px; margin: 0 auto; background: #fff; border-radius: 10px; padding: 30px;">
        <h2 style="margin-top: 0; color: #e8262d;">{{ $subject }}</h2>
        <p>{{ $body }}</p>

        <table style="width: 100%; border-collapse: collapse; margin: 20px 0; font-size: 14px;">
            <tr><td style="padding: 6px 0; color: #777;">Package</td><td style="padding: 6px 0;">{{ $boost->package?->name }} ({{ $boost->package?->duration_days }} days)</td></tr>
            <tr><td style="padding: 6px 0; color: #777;">Position</td><td style="padding: 6px 0;">{{ $boost->position?->name }}</td></tr>
            @if($boost->start_date)
                <tr><td style="padding: 6px 0; color: #777;">Runs</td><td style="padding: 6px 0;">{{ $boost->start_date->format('M j, Y') }} – {{ $boost->end_date?->format('M j, Y') }}</td></tr>
            @endif
            @if($forAdmin || $boost->payment_status === 'success')
                <tr><td style="padding: 6px 0; color: #777;">Amount</td><td style="padding: 6px 0;">{{ strtoupper($boost->currency) }} {{ number_format((float) $boost->amount, 2) }}</td></tr>
            @endif
            <tr>
                <td style="padding: 6px 0; color: #777; vertical-align: top;">Products</td>
                <td style="padding: 6px 0;">
                    @foreach($boost->boostedProducts as $item)
                        {{ $item->product?->name ?? 'Product #' . $item->product_id }}@if(! $loop->last)<br>@endif
                    @endforeach
                </td>
            </tr>
        </table>

        @if($forAdmin)
            <p><a href="{{ route('admin.boosts') }}" style="background: #e8262d; color: #fff; padding: 10px 18px; border-radius: 6px; text-decoration: none;">Open Boost Requests</a></p>
        @endif

        <p style="color: #999; font-size: 12px; margin-bottom: 0;">{{ $appName }}</p>
    </div>
</body>
</html>

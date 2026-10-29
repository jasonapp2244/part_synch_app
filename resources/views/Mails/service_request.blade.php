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
            <tr><td style="padding: 6px 0; color: #777;">Request</td><td style="padding: 6px 0;">{{ $serviceRequest->request_number }}</td></tr>
            <tr><td style="padding: 6px 0; color: #777;">Service</td><td style="padding: 6px 0;">{{ $serviceRequest->title }}</td></tr>
            <tr><td style="padding: 6px 0; color: #777;">Status</td><td style="padding: 6px 0;">{{ $serviceRequest->statusLabel() }}</td></tr>
            @if($serviceRequest->scheduled_at)
                <tr><td style="padding: 6px 0; color: #777;">Booked for</td><td style="padding: 6px 0;">{{ $serviceRequest->scheduled_at->format('M j, Y H:i') }}</td></tr>
            @elseif($serviceRequest->preferred_date)
                <tr><td style="padding: 6px 0; color: #777;">Preferred date</td><td style="padding: 6px 0;">{{ $serviceRequest->preferred_date->format('M j, Y') }}</td></tr>
            @endif
            @if($serviceRequest->quoted_price !== null || $serviceRequest->price !== null)
                <tr><td style="padding: 6px 0; color: #777;">Price</td><td style="padding: 6px 0;">{{ number_format((float) ($serviceRequest->quoted_price ?? $serviceRequest->price), 2) }} (paid outside the app)</td></tr>
            @endif
        </table>

        <p style="color: #999; font-size: 12px; margin-bottom: 0;">{{ $appName }}</p>
    </div>
</body>
</html>

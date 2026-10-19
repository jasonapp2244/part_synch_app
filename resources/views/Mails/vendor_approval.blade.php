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
        <p>Hi {{ $name }},</p>
        <p>{{ $body }}</p>
        <p style="color: #999; font-size: 12px; margin-bottom: 0;">{{ $appName }}</p>
    </div>
</body>
</html>

<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Reset Request</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
        }

        .email-container {
            max-width: 600px;
            margin: 20px auto;
            background: #ffffff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        h2 {
            color: #333;
            text-align: center;
        }

        p {
            font-size: 16px;
            color: #666;
            text-align: center;
        }

        .otp-code {
            font-size: 24px;
            font-weight: bold;
            /* color: #57db46; */
            text-align: center;
            background: #4ce61d;
            padding: 10px;
            border-radius: 5px;
            /* display: inline-block; */
            margin: 10px auto;
        }

        .footer {
            text-align: center;
            font-size: 14px;
            color: #693c3c;
            margin-top: 20px;
        }
    </style>
</head>

<body>

    <div class="email-container">
        <h2>New Password</h2>
        {{-- <p>Dear <strong>{{name}}</strong>,</p> --}}
        <p>You have requested to reset your password. Use the OTP code below to proceed:</p>

        <div class="otp-code" style="text-align: center">{{ $new_password }}</div>

        <p>If you did not request this, please ignore this email.</p>

        <div class="footer">
            <p>Best Regards,<br>Part Synch</p>
        </div>
    </div>

</body>

</html>

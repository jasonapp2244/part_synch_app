<!DOCTYPE html>
<html>
<head>
    <title>OTP Code</title>
    <style>
           h2 {
            color: #5aef2d;
        }
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            text-align: center;
            padding: 20px;
        }
        .container {
            max-width: 400px;
            background: #ffffff;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            margin: 0 auto;
        }
        .otp {
            font-size: 24px;
            font-weight: bold;
            color: #db110a;
            padding: 10px;
            border: 2px dashed #db110a;
            display: inline-block;
            letter-spacing: 4px;
            margin: 20px 0;
        }
        .footer {
            font-size: 12px;
            color: #777;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Verify Your Account</h2>
        <p>Use the OTP below to complete your verification process:</p>
        <div class="otp">{{ $data['otp'] }}</div>
        <p>Please do not share this code with anyone.</p>
        <div class="footer">
            <p>Thank you,</p>
            <p><strong>Part Synch</strong></p>
        </div>
    </div>
</body>
</html>

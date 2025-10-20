<!DOCTYPE html>
<html>

<head>
    <title>::</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
        }

        .container {
            width: 100%;
            max-width: 600px;
            margin: 20px auto;
            background-color: #ffffff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            border: 1px solid #ddd;
        }

        h2 {
            color: #333;
            text-align: center;
        }

        p {
            font-size: 16px;
            color: #555;
            line-height: 1.5;
        }

        .details {
            background-color: #f9f9f9;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
            border-left: 5px solid #db110a;
        }

        .details li {
            list-style: none;
            font-size: 16px;
            margin-bottom: 8px;
        }

        .footer {
            text-align: center;
            font-size: 14px;
            color: #888;
            margin-top: 20px;
            border-top: 1px solid #ddd;
            padding-top: 10px;
        }
    </style>
</head>

<body>
    <div class="container">
        <p>Hello Admin,</p>
        <p>A new user has registered on the platform. Below are the details:</p>

        <div class="details">
            <ul>
                @if(isset($data['first_name']))
                    <li><strong>Name:</strong> {{ $data['first_name'] }}</li>
                    <li><strong>Email:</strong> {{ $data['email'] }}</li>
                    <li><strong>Phone:</strong> {{ $data['phone_number'] }}</li>
                    {{-- <li><strong>Business Address:</strong> {{ $data['address'] }}</li> --}}
                @elseif(isset($data['first_name']))
                    <li><strong>Business Name:</strong> {{ $data['first_name'] }}</li>
                    <li><strong>Email:</strong> {{ $data['email'] }}</li>
                    <li><strong>Phone:</strong> {{ $data['phone_number'] }}</li>
                    <li><strong>Business Type:</strong> {{ $data['business_type'] }}</li>
                    {{-- <li><strong>Business Address:</strong> {{ $data['address'] }}</li> --}}
                @endif
            </ul>
        </div>

        <p>Please review the details and take any necessary actions.</p>

        <div class="footer">
            <p>&copy; {{ date('Y') }} Part Synch. All rights reserved.</p>
        </div>
    </div>

</body>

</html>

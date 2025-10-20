<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>Order Confirmation</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 30px 15px;
        }

        .email-wrapper {
            max-width: 700px;
            background-color: #ffffff;
            margin: 0 auto;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.08);
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
        }

        .header img {
            max-width: 150px;
            height: auto;
        }

        h2 {
            color: #333333;
        }

        p {
            font-size: 15px;
            color: #333;
            line-height: 1.6;
        }

        .order-info,
        .address-info {
            margin-top: 20px;
            margin-bottom: 25px;
        }

        .order-info p,
        .address-info p {
            margin: 4px 0;
            font-size: 14px;
            color: #555;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        th,
        td {
            padding: 10px;
            text-align: left;
            border: 1px solid #ddd;
        }

        th {
            background-color: #f8f8f8;
            font-weight: bold;
        }

        .total-row td {
            font-weight: bold;
            background-color: #eefaf0;
        }

        .footer {
            text-align: center;
            margin-top: 30px;
            font-size: 13px;
            color: #888;
        }

        .cta-button {
            display: inline-block;
            background-color: #28a745;
            color: #fff;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 20px;
        }

        @media screen and (max-width: 600px) {
            .email-wrapper {
                padding: 15px;
            }

            table th,
            table td {
                font-size: 12px;
            }

            .header img {
                max-width: 100px;
            }
        }
    </style>
</head>

<body>
    <div class="email-wrapper">
        <div class="header">
            {{-- <img src="{{ asset('path_to_your_logo') }}" alt="Your Store Logo"> --}}
            <h2>Order Confirmation</h2>
        </div>

        @if ($recipientType === 'vendor')
            <p>Hi {{ $vendorRecord->first_name ?? 'Vendor' }},</p>

            <p>You’ve received a new order that requires your attention. Please review the details below and proceed with processing as soon as possible.</p>

            <div class="order-info">
                <p><strong>Customer Name:</strong> {{ $userRecord->first_name ?? 'N/A' }}</p>
                <p><strong>Customer Phone:</strong> {{ $userRecord->phone_number ?? 'N/A' }}</p>
                <p><strong>Order Date:</strong> {{ $order->created_at->format('d M Y, h:i A') }}</p>
            </div>

        @elseif ($recipientType === 'user')
            <p>Hi {{ $userRecord->first_name ?? 'Valued Customer' }},</p>

            <p>Thank you for your purchase! We’ve received your order and it’s currently being processed. We’ll notify you once it ships.</p>

            <div class="order-info">
                <p><strong>Vendor Name:</strong> {{ $vendorRecord->first_name ?? 'N/A' }}</p>
                <p><strong>Vendor Phone:</strong> {{ $vendorRecord->phone_number ?? 'N/A' }}</p>
                <p><strong>Order Date:</strong> {{ $order->created_at->format('d M Y, h:i A') }}</p>
            </div>
        @endif

        <table>
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Qty</th>
                    <th>Unit Price</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                @php $grandTotal = 0; @endphp
                @foreach ($orderItem as $item)
                    @php $grandTotal += $item->total_price; @endphp
                    <tr>
                        <td>{{ $item->name ?? 'N/A' }}</td>
                        <td>{{ $item->quantity }}</td>
                        <td>${{ number_format($item->price, 2) }}</td>
                        <td>${{ number_format($item->total_price, 2) }}</td>
                    </tr>
                @endforeach
                <tr class="total-row">
                    <td colspan="3" style="text-align: right;">Grand Total:</td>
                    <td>${{ number_format($grandTotal, 2) }}</td>
                </tr>
            </tbody>
        </table>

        <div class="address-info">
            <p><strong>Shipping Address:</strong> {{ $completeAddress }}</p>
        </div>

        @if ($recipientType === 'vendor')
            <div class="footer">
                <p>We appreciate your timely fulfillment of this order. For any assistance, please contact our support team.</p>
            </div>
        @else
            <div class="footer">
                <p>If you have any questions, feel free to contact us at <a href="mailto:support@yourstore.com">support@yourstore.com</a>.</p>
                <p>Thank you for choosing <strong>Part Synch</strong>. We appreciate your business.</p>
            </div>
        @endif
    </div>
</body>

</html>

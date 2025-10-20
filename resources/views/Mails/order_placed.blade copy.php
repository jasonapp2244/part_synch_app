<!-- resources/views/emails/order_placed.blade.php -->
<!DOCTYPE html>
<html>
<head>
    <title>Order Confirmation</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            background-color: #f4f4f4;
            padding: 40px 20px;
            margin: 0;
        }
        .email-container {
            max-width: 600px;
            background: #ffffff;
            padding: 30px;
            margin: 0 auto;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        h2 {
            color: #5aef2d;
        }
        .order-info p {
            margin: 8px 0;
            font-size: 15px;
        }
        .footer {
            font-size: 13px;
            color: #666;
            margin-top: 30px;
            text-align: center;
        }
        .footer strong {
            color: #000;
        }
        .btn {
            display: inline-block;
            background: #db110a;
            color: #fff;
            padding: 12px 20px;
            margin-top: 20px;
            text-decoration: none;
            border-radius: 5px;
        }
        .btn:hover {
            background: #f71109;
        }
    </style>
</head>
<body>
    <div class="order-info">
        <p><strong>Order ID:</strong> {{ "#10001" . $order->id }}</p>
        <p><strong>Custmer Name</strong> {{  $orderItems->user->first_name }}</p>
        <table width="100%" cellpadding="10" border="1" cellspacing="0" style="border-collapse: collapse;">
            <thead>
                <tr style="background-color: #f4f4f4;">
                    <th>Product</th>
                    <th>Qty</th>
                    <th>Unit Price</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $grandTotal = 0;
                @endphp

                @foreach ($orderItems as $item)
                    @php
                        $grandTotal += $item->total_price;
                    @endphp
                    <tr>
                        <td>{{ $item->product->name ?? 'N/A' }}</td>
                        <td>{{ $item->quantity }}</td>
                        <td>${{ number_format($item->price, 2) }}</td>
                        <td>${{ number_format($item->total_price, 2) }}</td>
                    </tr>
                @endforeach

                <!-- Grand Total Row -->
                <tr style="background-color: #e9f5e9; font-weight: bold;">
                    <td colspan="3" style="text-align: right;">Grand Total:</td>
                    <td>${{ number_format($grandTotal, 2) }}</td>
                </tr>
            </tbody>
        </table>


        <p><strong>Shipping Address:</strong> {{ $completeAddress }}</p>
    </div>

</body>
</html>

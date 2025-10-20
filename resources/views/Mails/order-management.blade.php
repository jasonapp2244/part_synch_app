<!DOCTYPE html>
<html>
<head>
    <title>Order Notification</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6;">
    <h2>Hello {{ $recipientType == 'user' ? $user->name : $vendor->name }},</h2>

    <p>Your order status has been updated.</p>

    <h3>Order Details:</h3>
    <ul>
        <li><strong>Order ID:</strong> {{ $order->id }}</li>
        <li><strong>Status:</strong> {{ ucfirst($order->order_status) }}</li>
        <li><strong>Date:</strong> {{ $order->updated_at->format('F j, Y, g:i a') }}</li>
    </ul>

    @if($recipientType == 'user')
        <p>Thank you for shopping with us. We’ll keep you updated on the next steps.</p>
    @else
        <p>Please prepare for the next step in fulfilling this order.</p>
    @endif

    <br>
    <p>Regards,</p>
    <p><strong>Part Synch</strong></p>
</body>
</html>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Product Out of Stock</title>
</head>
<body>
    <h2>
        @if($type === 'customer')
            Dear {{ $user->first_name }},
        @else
            Hello {{ $user->first_name ?? 'Vendor' }},
        @endif
    </h2>

    <p>
        @if($type === 'customer')
            We're sorry! The product <strong>{{ $product->name }}</strong> you tried to order is currently out of stock.
        @else
            Your product <strong>{{ $product->name }}</strong> is out of stock. Please update stock levels to continue receiving orders.
        @endif
    </p>

    <p><strong>Product:</strong> {{ $product->name }}</p>

    @if($type === 'vendor')
        <p><strong>Current Stock:</strong> {{ $product->stock->current_stock ?? 0 }}</p>
    @endif

    <p>Regards,<br>Part Synch</p>
</body>
</html>

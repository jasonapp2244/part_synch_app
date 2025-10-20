<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Wishlist Notification</title>
</head>

<body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f4f4f4;">
    <table width="100%" style="background-color: #f4f4f4; padding: 20px 0;">
        <tr>
            <td align="center">
                <table width="600"
                    style="background: #ffffff; border-radius: 8px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.05);">

                    <!-- Header -->
                    <tr>
                        <td align="center" style="padding-bottom: 20px;">
                            <h2 style="margin: 0; color: #333;">Wishlist Activity</h2>
                        </td>
                    </tr>

                    <!-- Message -->
                    <tr>
                        <td>
                            <p style="font-size: 16px; color: #333;">
                                <strong>{{ $data['by_user_name'] ?? 'Someone' }}</strong> just added
                                @if ($data['type'] === 'product')
                                    the product <strong>"{{ $data['product_name'] }}"</strong> to their wishlist.
                                @elseif($data['type'] === 'vendor')
                                    the store <strong>{{ $data['vendor_name'] }}"</strong> to their wishlist.
                                @endif
                            </p>
                        </td>
                    </tr>

                    <!-- Product View -->
                    @if ($data['type'] === 'product')
                        <tr>
                            <td align="center" style="padding: 20px 0;">
                                <img src="{{ $data['product_image'] ?? 'https://via.placeholder.com/200' }}"
                                    alt="Product Image" width="200" style="border-radius: 8px;"><br><br>
                                <p><strong>Product:</strong> {{ $data['product_name'] }}</p>
                                <p><strong>Store:</strong> {{ $data['vendor_name'] }}</p>
                                <a href="{{ $data['product_url'] }}"
                                    style="background-color: #28a745; color: white; padding: 10px 20px; border-radius: 5px; text-decoration: none;">View
                                    Product</a>
                            </td>
                        </tr>
                    @endif

                    <!-- Vendor View -->
                    @if ($data['type'] === 'vendor')
                        <tr>
                            <td align="center" style="padding: 20px 0;">
                                <img src="{{ $data['vendor_logo'] ?? 'https://via.placeholder.com/200' }}"
                                    alt="Vendor Logo" width="100" style="border-radius: 50%;"><br><br>
                                <p><strong>Store:</strong> {{ $data['vendor_name'] }}</p>
                                <a href="{{ $data['vendor_url'] }}"
                                    style="background-color: #007bff; color: white; padding: 10px 20px; border-radius: 5px; text-decoration: none;">Visit
                                    Store</a>
                            </td>
                        </tr>
                    @endif

                    <!-- Footer -->
                    <tr>
                        <td style="padding-top: 30px; font-size: 14px; color: #888; text-align: center;">
                            <p>This is an automated email from <strong>Part Synch</strong>.</p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>

</html>

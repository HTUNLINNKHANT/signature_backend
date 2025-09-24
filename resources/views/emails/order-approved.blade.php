<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Payment Approved</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #d4edda; padding: 20px; text-align: center; border-radius: 8px; border: 1px solid #c3e6cb; }
        .order-info { background: #fff; border: 1px solid #ddd; padding: 20px; margin: 20px 0; border-radius: 8px; }
        .items-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        .items-table th, .items-table td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        .items-table th { background: #f8f9fa; }
        .total-row { font-weight: bold; background: #f8f9fa; }
        .footer { text-align: center; margin-top: 30px; color: #666; font-size: 14px; }
        .status-badge { background: #28a745; color: #fff; padding: 4px 12px; border-radius: 20px; font-size: 12px; }
        .success-icon { color: #28a745; font-size: 48px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="success-icon">✓</div>
            <h1>Payment Approved!</h1>
            <p>Great news, {{ $customer->name }}! Your payment has been verified.</p>
        </div>

        <div class="order-info">
            <h2>Order Update</h2>
            <p><strong>Order Number:</strong> {{ $order->order_number }}</p>
            <p><strong>Order Date:</strong> {{ $order->created_at->format('F j, Y g:i A') }}</p>
            <p><strong>Approved Date:</strong> {{ $order->approved_at ? \Carbon\Carbon::parse($order->approved_at)->format('F j, Y g:i A') : 'Just now' }}</p>
            <p><strong>Status:</strong> <span class="status-badge">Processing</span></p>
            <p><strong>Payment Status:</strong> <span class="status-badge">Approved</span></p>
        </div>

        <div class="order-info">
            <h2>What Happens Next?</h2>
            <p>✓ Your payment has been successfully verified</p>
            <p>✓ Your order is now being processed</p>
            <p>📦 We'll prepare your items for shipping</p>
            <p>🚚 You'll receive tracking information once shipped</p>
        </div>

        <div class="order-info">
            <h2>Order Summary</h2>
            <table class="items-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Size</th>
                        <th>Quantity</th>
                        <th>Price</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($items as $item)
                    <tr>
                        <td>{{ $item->product_name }}</td>
                        <td>{{ $item->variant_size ?? 'N/A' }}</td>
                        <td>{{ $item->quantity }}</td>
                        <td>{{ number_format($item->price, 0) }} MMK</td>
                        <td>{{ number_format($item->total, 0) }} MMK</td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="4"><strong>Subtotal:</strong></td>
                        <td><strong>{{ number_format($order->subtotal, 0) }} MMK</strong></td>
                    </tr>
                    <tr>
                        <td colspan="4"><strong>Delivery Fee:</strong></td>
                        <td><strong>{{ number_format($order->delivery_fee, 0) }} MMK</strong></td>
                    </tr>
                    <tr class="total-row">
                        <td colspan="4"><strong>Total Paid:</strong></td>
                        <td><strong>{{ number_format($order->total_amount, 0) }} MMK</strong></td>
                    </tr>
                </tfoot>
            </table>
        </div>

        @if($address)
        <div class="order-info">
            <h2>Shipping Address</h2>
            <p>{{ $address->name }}<br>
            {{ $address->address_line_1 }}<br>
            @if($address->address_line_2){{ $address->address_line_2 }}<br>@endif
            {{ $address->township }}, {{ $address->state_region }}<br>
            @if($address->postal_code){{ $address->postal_code }}<br>@endif
            {{ $address->country }}</p>
            @if($address->phone)
            <p><strong>Phone:</strong> {{ $address->phone }}</p>
            @endif
        </div>
        @endif

        <div class="footer">
            <p>Thank you for your business!</p>
            <p>We'll keep you updated on your order progress.</p>
        </div>
    </div>
</body>
</html>

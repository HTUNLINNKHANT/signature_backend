<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Payment Issue</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #f8d7da; padding: 20px; text-align: center; border-radius: 8px; border: 1px solid #f5c6cb; }
        .order-info { background: #fff; border: 1px solid #ddd; padding: 20px; margin: 20px 0; border-radius: 8px; }
        .items-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        .items-table th, .items-table td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        .items-table th { background: #f8f9fa; }
        .total-row { font-weight: bold; background: #f8f9fa; }
        .footer { text-align: center; margin-top: 30px; color: #666; font-size: 14px; }
        .status-badge { background: #dc3545; color: #fff; padding: 4px 12px; border-radius: 20px; font-size: 12px; }
        .warning-icon { color: #dc3545; font-size: 48px; }
        .action-steps { background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 8px; margin: 20px 0; }
        .action-steps h3 { color: #856404; margin-top: 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="warning-icon">⚠</div>
            <h1>Payment Issue</h1>
            <p>Hello {{ $customer->name }}, we need your attention regarding your order.</p>
        </div>

        <div class="order-info">
            <h2>Order Information</h2>
            <p><strong>Order Number:</strong> {{ $order->order_number }}</p>
            <p><strong>Order Date:</strong> {{ $order->created_at->format('F j, Y g:i A') }}</p>
            <p><strong>Status:</strong> <span class="status-badge">Payment Failed</span></p>
        </div>

        @if($rejection_reason)
        <div class="order-info">
            <h2>Issue Details</h2>
            <p><strong>Reason:</strong> {{ $rejection_reason }}</p>
        </div>
        @endif

        <div class="action-steps">
            <h3>What You Need to Do</h3>
            <p><strong>To complete your order, please:</strong></p>
            <ol>
                <li>Review the payment issue mentioned above</li>
                <li>Make a new payment using the correct method</li>
                <li>Upload a clear screenshot of your new transaction</li>
                <li>Contact our support team if you need assistance</li>
            </ol>
            <p><strong>Note:</strong> Your order items are still reserved for you, but please complete the payment process soon to avoid cancellation.</p>
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
                        <td colspan="4"><strong>Amount Due:</strong></td>
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
            <p>We're here to help! Contact our support team if you have any questions.</p>
            <p>Thank you for your patience and understanding.</p>
        </div>
    </div>
</body>
</html>

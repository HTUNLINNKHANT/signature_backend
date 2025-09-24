<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Order Confirmation</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #f8f9fa; padding: 20px; text-align: center; border-radius: 8px; }
        .order-info { background: #fff; border: 1px solid #ddd; padding: 20px; margin: 20px 0; border-radius: 8px; }
        .items-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        .items-table th, .items-table td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        .items-table th { background: #f8f9fa; }
        .total-row { font-weight: bold; background: #f8f9fa; }
        .footer { text-align: center; margin-top: 30px; color: #666; font-size: 14px; }
        .status-badge { background: #ffc107; color: #000; padding: 4px 12px; border-radius: 20px; font-size: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Order Confirmation</h1>
            <p>Thank you for your order, {{ $customer->name }}!</p>
        </div>

        <div class="order-info">
            <h2>Order Details</h2>
            <p><strong>Order Number:</strong> {{ $order->order_number }}</p>
            <p><strong>Order Date:</strong> {{ $order->created_at->format('F j, Y g:i A') }}</p>
            <p><strong>Status:</strong> <span class="status-badge">{{ ucfirst($order->status) }}</span></p>
            <p><strong>Payment Status:</strong> <span class="status-badge">Pending Approval</span></p>
        </div>

        <div class="order-info">
            <h2>Items Ordered</h2>
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
                        <td colspan="4"><strong>Total Amount:</strong></td>
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

        <div class="order-info">
            <h2>Payment Information</h2>
            <p>Your payment screenshot has been received and is currently being reviewed by our team.</p>
            <p>You will receive another email once your payment has been verified and approved.</p>
            @if($order->payment_notes)
            <p><strong>Payment Notes:</strong> {{ $order->payment_notes }}</p>
            @endif
        </div>

        <div class="footer">
            <p>Thank you for shopping with us!</p>
            <p>If you have any questions, please contact our support team.</p>
        </div>
    </div>
</body>
</html>

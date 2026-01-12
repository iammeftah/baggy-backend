{{-- resources/views/emails/orders/new-order.blade.php --}}
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Order</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #2563eb;
            color: white;
            padding: 20px;
            text-align: center;
            border-radius: 8px 8px 0 0;
        }
        .content {
            background-color: #f9fafb;
            padding: 30px;
            border: 1px solid #e5e7eb;
        }
        .order-info {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #e5e7eb;
        }
        .info-label {
            font-weight: bold;
            color: #6b7280;
        }
        .items-table {
            width: 100%;
            background-color: white;
            border-radius: 8px;
            overflow: hidden;
            margin-top: 20px;
        }
        .items-table th {
            background-color: #f3f4f6;
            padding: 12px;
            text-align: left;
            font-weight: bold;
            color: #374151;
        }
        .items-table td {
            padding: 12px;
            border-bottom: 1px solid #e5e7eb;
        }
        .total {
            text-align: right;
            font-size: 20px;
            font-weight: bold;
            color: #2563eb;
            margin-top: 20px;
        }
        .button {
            display: inline-block;
            background-color: #2563eb;
            color: white;
            padding: 12px 30px;
            text-decoration: none;
            border-radius: 6px;
            margin-top: 20px;
        }
        .footer {
            text-align: center;
            color: #6b7280;
            margin-top: 30px;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🎉 New Order Received!</h1>
    </div>

    <div class="content">
        <p>Hello Admin,</p>
        <p>A new order has been placed on your store. Here are the details:</p>

        <div class="order-info">
            <div class="info-row">
                <span class="info-label">Order Number:</span>
                <span>{{ $order->order_number }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Customer:</span>
                <span>{{ $order->user->first_name }} {{ $order->user->last_name }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Email:</span>
                <span>{{ $order->user->email }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Phone:</span>
                <span>{{ $order->shipping_phone }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Status:</span>
                <span style="color: #f59e0b; font-weight: bold;">{{ ucfirst($order->status) }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Date:</span>
                <span>{{ $order->created_at->format('F j, Y g:i A') }}</span>
            </div>
        </div>

        <h3>Shipping Information</h3>
        <div class="order-info">
            <div class="info-row">
                <span class="info-label">Address:</span>
                <span>{{ $order->shipping_address }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">City:</span>
                <span>{{ $order->shipping_city }}</span>
            </div>
            @if($order->notes)
            <div class="info-row">
                <span class="info-label">Notes:</span>
                <span>{{ $order->notes }}</span>
            </div>
            @endif
        </div>

        <h3>Order Items</h3>
        <table class="items-table">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Quantity</th>
                    <th>Price</th>
                    <th>Subtotal</th>
                </tr>
            </thead>
            <tbody>
                @foreach($order->items as $item)
                <tr>
                    <td>{{ $item->product_name }}</td>
                    <td>{{ $item->quantity }}</td>
                    <td>{{ number_format($item->product_price, 2) }} MAD</td>
                    <td>{{ number_format($item->subtotal, 2) }} MAD</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <div class="total">
            Total: {{ number_format($order->total_amount, 2) }} MAD
        </div>

        <center>
            <a href="{{ config('app.frontend_url') }}/admin/orders/{{ $order->order_number }}" class="button">
                View Order Details
            </a>
        </center>

        <div class="footer">
            <p>This is an automated notification from your store.</p>
            <p>Please do not reply to this email.</p>
        </div>
    </div>
</body>
</html>

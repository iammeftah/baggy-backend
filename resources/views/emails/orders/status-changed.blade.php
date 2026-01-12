{{-- resources/views/emails/orders/status-changed.blade.php --}}
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Status Update</title>
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
        .status-box {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            margin: 20px 0;
        }
        .status-change {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 20px;
            margin: 20px 0;
        }
        .status-badge {
            padding: 10px 20px;
            border-radius: 6px;
            font-weight: bold;
        }
        .status-pending {
            background-color: #fef3c7;
            color: #92400e;
        }
        .status-shipping {
            background-color: #dbeafe;
            color: #1e40af;
        }
        .status-delivered {
            background-color: #d1fae5;
            color: #065f46;
        }
        .arrow {
            font-size: 24px;
            color: #6b7280;
        }
        .order-info {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
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
        <h1>📦 Order Status Updated</h1>
    </div>

    <div class="content">
        <p>Hello {{ $order->user->first_name }},</p>
        <p>Your order status has been updated:</p>

        <div class="status-box">
            <div class="status-change">
                <span class="status-badge status-{{ $oldStatus }}">
                    {{ ucfirst($oldStatus) }}
                </span>
                <span class="arrow">→</span>
                <span class="status-badge status-{{ $order->status }}">
                    {{ ucfirst($order->status) }}
                </span>
            </div>
        </div>

        @if($order->status === 'shipping')
        <p style="text-align: center; font-weight: bold; color: #2563eb;">
            🚚 Your order is on its way!
        </p>
        @elseif($order->status === 'delivered')
        <p style="text-align: center; font-weight: bold; color: #059669;">
            ✅ Your order has been delivered! We hope you enjoy your purchase.
        </p>
        @endif

        <div class="order-info">
            <div class="info-row">
                <span class="info-label">Order Number:</span>
                <span>{{ $order->order_number }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Total Amount:</span>
                <span>{{ number_format($order->total_amount, 2) }} MAD</span>
            </div>
            <div class="info-row">
                <span class="info-label">Shipping Address:</span>
                <span>{{ $order->shipping_address }}, {{ $order->shipping_city }}</span>
            </div>
        </div>

        <center>
            <a href="{{ config('app.frontend_url') }}/customer/orders/{{ $order->order_number }}" class="button">
                View Order Details
            </a>
        </center>

        <div class="footer">
            <p>Thank you for shopping with us!</p>
            <p>If you have any questions, please contact our support team.</p>
        </div>
    </div>
</body>
</html>

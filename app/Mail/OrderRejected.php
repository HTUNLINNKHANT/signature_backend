<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OrderRejected extends Mailable
{
    use Queueable, SerializesModels;

    public $order;

    public function __construct(Order $order)
    {
        $this->order = $order;
    }

    public function build()
    {
        return $this->subject('Payment Issue - Order ' . $this->order->order_number)
                    ->view('emails.order-rejected')
                    ->with([
                        'order' => $this->order,
                        'customer' => $this->order->user,
                        'items' => $this->order->orderItems,
                        'address' => $this->order->shippingAddress,
                        'rejection_reason' => $this->order->rejection_reason
                    ]);
    }
}

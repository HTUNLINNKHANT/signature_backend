<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OrderApproved extends Mailable
{
    use Queueable, SerializesModels;

    public $order;

    public function __construct(Order $order)
    {
        $this->order = $order;
    }

    public function build()
    {
        return $this->subject('Payment Approved - Order ' . $this->order->order_number)
                    ->view('emails.order-approved')
                    ->with([
                        'order' => $this->order,
                        'customer' => $this->order->user,
                        'items' => $this->order->orderItems,
                        'address' => $this->order->shippingAddress
                    ]);
    }
}

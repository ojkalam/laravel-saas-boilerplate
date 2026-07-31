<?php

namespace App\Mail;

use App\Models\License;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderReceiptMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Your order :number', ['number' => $this->order->number]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.order-receipt',
            with: [
                'licenses' => License::acrossTeams()
                    ->whereIn('order_item_id', $this->order->items()->select('id'))
                    ->with('product')
                    ->get(),
            ],
        );
    }
}

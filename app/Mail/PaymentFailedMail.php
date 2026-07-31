<?php

namespace App\Mail;

use App\Models\Team;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaymentFailedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Team $team) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Payment failed for :team', ['team' => $this->team->name]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.payment-failed',
            with: [
                'graceDays' => (int) config('plans.grace_period_days'),
            ],
        );
    }
}

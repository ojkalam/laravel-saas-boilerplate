<?php

namespace App\Mail;

use App\Models\TeamInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class TeamInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public TeamInvitation $invitation) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __(':team invited you to join them', ['team' => $this->invitation->team->name]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.team-invitation',
            with: [
                'acceptUrl' => URL::temporarySignedRoute(
                    'team-invitations.accept',
                    $this->invitation->expires_at,
                    ['invitation' => $this->invitation],
                ),
            ],
        );
    }
}

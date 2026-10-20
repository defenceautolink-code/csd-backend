<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ResetPasswordMail extends Mailable
{
    use Queueable, SerializesModels;

    public User $user;
    public string $resetUrl;
    public int $validityMinutes;

    /**
     * Create a new message instance.
     */
    public function __construct(User $user, string $resetUrl, int $validityMinutes = 4)
    {
        $this->user = $user;
        $this->resetUrl = $resetUrl;
        $this->validityMinutes = $validityMinutes;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Password Reset Request – Defence Autolink CRM',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.reset-password',
            with: [
                'user' => $this->user,
                'resetUrl' => $this->resetUrl,
                'validityMinutes' => $this->validityMinutes,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     */
    public function attachments(): array
    {
        return [];
    }
}

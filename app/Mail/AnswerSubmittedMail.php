<?php

namespace App\Mail;

use App\Models\userAnswer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AnswerSubmittedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $userAnswers;

    public function __construct($user,$userAnswers)
    {
        $this->user = $user;
        $this->userAnswers = $userAnswers; // Accepting array of userAnswers
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Answer Submitted Mail',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'mail.submitAnswer', // This view will need to handle an array of answers
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}

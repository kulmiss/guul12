<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * "Send test email" in Nova: proves that the server can send email.
 */
class StaffTestEmail extends Mailable
{
    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Guul 12 test email');
    }

    public function content(): Content
    {
        return new Content(htmlString: '<p>This is a test email from the Guul 12 admin.</p><p>If you can read this, email works.</p>');
    }
}

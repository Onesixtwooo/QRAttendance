<?php

namespace App\Mail;

use App\Models\Student;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class StudentAccountCredentials extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Student $student, public string $temporaryPassword)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your student attendance account');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.student-account-credentials');
    }
}

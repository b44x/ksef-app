<?php

namespace App\Mail;

use App\Models\Company;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** The daily summary. A test message (no sections) is the same mail with a line saying it is a test. */
class DigestMail extends Mailable
{
    /**
     * @param  array{offline: list<array<string, mixed>>, purchases: list<array<string, mixed>>, rejected: list<array<string, mixed>>}|null  $digest  null for the test message
     */
    public function __construct(public readonly Company $company, public readonly ?array $digest) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->digest === null
            ? __('KSeF: test message')
            : __('KSeF: :company: what needs your attention', ['company' => $this->company->name]));
    }

    public function content(): Content
    {
        return new Content(view: 'mail.digest');
    }
}

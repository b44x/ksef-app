<?php

namespace App\Mail;

use App\Models\Company;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** An invoice for its buyer: a short message and the PDF. The seller's address is the one replies go to. */
class InvoiceMail extends Mailable
{
    public function __construct(
        public readonly Company $company,
        public readonly string $mailSubject,
        public readonly string $body,
        private readonly string $pdf,
        private readonly string $filename,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->mailSubject,
            replyTo: $this->company->email !== null && $this->company->email !== '' ? [new Address($this->company->email, $this->company->name)] : [],
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.invoice');
    }

    /**
     * @return list<Attachment>
     */
    public function attachments(): array
    {
        return [Attachment::fromData(fn (): string => $this->pdf, $this->filename)->withMime('application/pdf')];
    }
}

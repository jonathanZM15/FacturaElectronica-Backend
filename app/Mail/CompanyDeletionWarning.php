<?php

namespace App\Mail;

use App\Models\Company;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CompanyDeletionWarning extends Mailable
{
    use Queueable, SerializesModels;

    public Company $company;
    public string $backupDownloadUrl;

    /**
     * Create a new message instance.
     */
    public function __construct(Company $company, string $backupDownloadUrl)
    {
        $this->company = $company;
        $this->backupDownloadUrl = $backupDownloadUrl;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '⚠️ ADVERTENCIA: Tu cuenta de emisor será eliminada en 3 días',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.company-deletion-warning',
            with: [
                'company' => $this->company,
                'backupDownloadUrl' => $this->backupDownloadUrl,
                'deletionDate' => now()->addDays(3)->format('d/m/Y'),
            ],
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

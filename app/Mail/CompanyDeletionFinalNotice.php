<?php

namespace App\Mail;

use App\Models\Company;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CompanyDeletionFinalNotice extends Mailable
{
    use Queueable, SerializesModels;

    public Company $company;
    public string $backupDownloadUrl;
    public int $hoursUntilDeletion;

    /**
     * Create a new message instance.
     */
    public function __construct(Company $company, string $backupDownloadUrl, int $hoursUntilDeletion = 72)
    {
        $this->company = $company;
        $this->backupDownloadUrl = $backupDownloadUrl;
        $this->hoursUntilDeletion = $hoursUntilDeletion;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '🔴 NOTIFICACIÓN FINAL: Tu cuenta será eliminada permanentemente',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.company-deletion-final-notice',
            with: [
                'company' => $this->company,
                'backupDownloadUrl' => $this->backupDownloadUrl,
                'hoursUntilDeletion' => $this->hoursUntilDeletion,
                'daysRemaining' => ceil($this->hoursUntilDeletion / 24),
                'reactivationUrl' => config('app.frontend_url') . '/company/' . $this->company->id . '/reactivate',
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

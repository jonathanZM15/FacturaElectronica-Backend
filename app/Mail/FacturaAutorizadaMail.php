<?php

namespace App\Mail;

use App\Models\Comprobante;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class FacturaAutorizadaMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Comprobante $comprobante,
        public string $pdfContent
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->comprobante->company->razon_social . ' - Factura Electrónica Nº ' . $this->comprobante->secuencial_formateado,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.factura_autorizada',
        );
    }

    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => $this->pdfContent, $this->comprobante->clave_acceso . '.pdf')
                ->withMime('application/pdf'),
            Attachment::fromData(fn () => $this->comprobante->xml_autorizado, $this->comprobante->clave_acceso . '.xml')
                ->withMime('application/xml'),
        ];
    }
}

<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class EmailChangeConfirmMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public $user,
        public $url
    ) {}

    public function build()
    {
        return $this->view('emails.email_change_confirm')
                    ->subject('📧 Confirmación de cambio de correo en Máximo Facturas')
                    ->with([
                        'user' => $this->user,
                        'url' => $this->url,
                    ]);
    }
}

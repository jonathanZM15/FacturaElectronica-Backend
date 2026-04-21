<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class EmailChangeNoticeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public $user,
        public $new_email
    ) {}

    public function build()
    {
        return $this->view('emails.email_change_notice')
                    ->subject('⚠️ Solicitud de cambio de correo en Máximo Facturas')
                    ->with([
                        'user' => $this->user,
                        'new_email' => $this->new_email,
                    ]);
    }
}

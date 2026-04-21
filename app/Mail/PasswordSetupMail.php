<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PasswordSetupMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public $user,
        public $url
    ) {}

    public function build()
    {
        return $this->view('emails.password_setup')
                    ->subject('🔐 Establecimiento de contraseña para acceder a Máximo Facturas')
                    ->with([
                        'user' => $this->user,
                        'url' => $this->url,
                    ]);
    }
}

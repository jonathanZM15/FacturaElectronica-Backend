<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PasswordResetMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public $user,
        public $url
    ) {}

    public function build()
    {
        return $this->view('emails.password_reset')
                    ->subject('🔐 Restablecimiento de contraseña en Máximo Facturas')
                    ->with([
                        'user' => $this->user,
                        'url' => $this->url,
                    ]);
    }
}

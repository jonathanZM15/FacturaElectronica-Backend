<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AccountReactivationVerifyMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public $user,
        public $url
    ) {}

    public function build()
    {
        return $this->view('emails.account_reactivation_verify')
                    ->subject('🔐 Verificación para reactivar su cuenta en Máximo Facturas')
                    ->with([
                        'user' => $this->user,
                        'url' => $this->url,
                    ]);
    }
}

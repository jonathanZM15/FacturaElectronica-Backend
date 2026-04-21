<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AccountConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public $user,
        public $url
    ) {}

    public function build()
    {
        return $this->view('emails.account_confirmation')
                    ->subject('📩 Confirmación de cuenta en Máximo Facturas')
                    ->with([
                        'user' => $this->user,
                        'url' => $this->url,
                    ]);
    }
}

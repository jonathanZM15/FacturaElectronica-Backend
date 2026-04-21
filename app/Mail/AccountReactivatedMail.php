<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AccountReactivatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public $user) {}

    public function build()
    {
        return $this->view('emails.account_reactivated')
                    ->subject('✅ Reactivación de su cuenta en Máximo Facturas')
                    ->with([
                        'user' => $this->user,
                    ]);
    }
}

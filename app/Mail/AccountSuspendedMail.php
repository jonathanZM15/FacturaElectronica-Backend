<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AccountSuspendedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public $user) {}

    public function build()
    {
        return $this->view('emails.account_suspended')
                    ->subject('⚠️ Suspensión de su cuenta en Máximo Facturas')
                    ->with([
                        'user' => $this->user,
                    ]);
    }
}

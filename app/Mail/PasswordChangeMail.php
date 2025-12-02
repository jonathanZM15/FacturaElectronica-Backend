<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PasswordChangeMail extends Mailable
{
    use Queueable, SerializesModels;

    public $url;
    public $user;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(string $url, $user)
    {
        $this->url = $url;
        $this->user = $user;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject('Cambia tu contraseña - Máximo Facturas')
                    ->view('emails.password_change')
                    ->with(['url' => $this->url, 'user' => $this->user]);
    }
}

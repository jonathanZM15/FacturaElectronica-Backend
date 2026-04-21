<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class LoginAttemptsAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public $user,
        public $attempts = 5,
        public $date_time = null,
        public $ip_address = null,
        public $device = null
    ) {
        $this->date_time = $date_time ?? now()->format('d/m/Y H:i');
    }

    public function build()
    {
        return $this->view('emails.login_attempts_alert')
                    ->subject('⚠️ Intentos fallidos de acceso a su cuenta en Máximo Facturas')
                    ->with([
                        'user' => $this->user,
                        'attempts' => $this->attempts,
                        'date_time' => $this->date_time,
                        'ip_address' => $this->ip_address,
                        'device' => $this->device,
                    ]);
    }
}

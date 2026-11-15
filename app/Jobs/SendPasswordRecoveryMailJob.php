<?php

namespace App\Jobs;

use App\Mail\PasswordRecoveryMail;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;

class SendPasswordRecoveryMailJob implements ShouldQueue, ShouldBeEncrypted
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 60;

    public function __construct(public int $userId, public string $email, public string $token)
    {
        $this->afterCommit();
    }

    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function handle(): void
    {
        $user = User::find($this->userId);
        // No reenviar enlaces invalidados o destinados a un correo anterior.
        if (!$user || $user->email !== $this->email || !Password::broker()->tokenExists($user, $this->token)) return;
        $url = rtrim(config('app.frontend_url'), '/').'/cambiarPassword?token='.urlencode($this->token).'&email='.urlencode($this->email);
        Mail::to($this->email)->send(new PasswordRecoveryMail($url, $user));
    }
}

<?php

namespace App\Jobs;

use App\Mail\LoginAttemptsAlertMail;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Mail;

class SendSuspiciousLoginEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public User $user,
        public string $deviceType,
        public string $browser,
        public string $platform,
        public string $ipAddress,
    ) {
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Construir deviceInfo en el mismo formato que en AuthController
        $deviceInfo = "{$this->browser} en {$this->platform} ({$this->deviceType})";
        $timestamp = now()->timezone('America/Guayaquil')->format('d/m/Y H:i');

        Mail::to($this->user->email)->send(
            new LoginAttemptsAlertMail(
                $this->user,
                $this->ipAddress,
                5, // attemptCount siempre es 5 cuando se dispara este job
                $deviceInfo,
                $timestamp
            )
        );
    }

    /**
     * Handle a job failure.
     */
    public function failed(Throwable $exception): void
    {
        \Log::error('Failed to send suspicious login email', [
            'user_id' => $this->user->id,
            'error' => $exception->getMessage(),
        ]);
    }
}

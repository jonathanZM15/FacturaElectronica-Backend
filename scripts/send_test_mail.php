<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';

/** @var \Illuminate\Contracts\Console\Kernel $kernel */
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$to = (string) (config('mail.from.address') ?? '');

if ($to === '') {
    fwrite(STDERR, "MAIL_FROM_ADDRESS no está configurado.\n");
    exit(1);
}

\Illuminate\Support\Facades\Mail::raw('SMTP test desde Laravel', function ($message) use ($to): void {
    $message->to($to)->subject('SMTP test');
});

echo "OK\n";

<?php

require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Company;

$companies = Company::whereIn('ruc', ['1234567890001', '1234567890002', '1234567890003'])->get();

foreach($companies as $c) {
    $c->correo_remitente = 'yendermejia0@gmail.com';
    $c->save();
    echo "Actualizado: {$c->razon_social}\n";
}

echo "✅ Todos los correos fueron actualizados\n";

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transportistas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('emisor_id')->constrained('emisores');
            $table->string('tipo_identificacion', 10);
            $table->string('identificacion', 20);
            $table->string('razon_social', 300);
            $table->string('email')->nullable();
            $table->string('telefono', 50)->nullable();
            $table->string('placa_vehiculo', 20)->nullable();
            
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->unique(['emisor_id', 'identificacion']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transportistas');
    }
};

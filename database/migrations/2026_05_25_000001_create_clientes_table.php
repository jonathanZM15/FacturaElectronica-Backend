<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clientes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('emisor_id')->constrained('emisores')->cascadeOnDelete();
            $table->string('tipo_identificacion', 20);
            $table->string('identificacion', 13);
            $table->string('razon_social', 255);
            $table->string('nombre_comercial', 255)->nullable();
            $table->string('direccion', 500);
            $table->string('email', 255);
            $table->string('telefono', 50)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['emisor_id', 'tipo_identificacion', 'identificacion']);
            $table->index(['emisor_id', 'identificacion']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clientes');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('proveedores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('emisor_id')->constrained('emisores');
            $table->string('tipo_identificacion', 10);
            $table->string('identificacion', 20);
            $table->string('razon_social', 300);
            $table->string('nombre_comercial', 300)->nullable();
            $table->string('direccion', 300)->nullable();
            $table->string('email')->nullable();
            $table->string('telefono', 50)->nullable();
            
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();

            // Un proveedor es único por emisor e identificación
            $table->unique(['emisor_id', 'identificacion']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('proveedores');
    }
};

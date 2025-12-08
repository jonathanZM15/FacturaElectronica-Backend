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
        Schema::create('establecimientos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('company_id')->index();
            $table->string('codigo', 100);
            $table->enum('estado', ['ABIERTO','CERRADO'])->default('ABIERTO');
            $table->string('nombre', 255);
            $table->string('nombre_comercial', 255)->nullable();
            $table->string('direccion', 500);
            $table->string('correo', 255)->nullable();
            $table->string('telefono', 50)->nullable();
            $table->string('logo_path')->nullable();
            $table->text('actividades_economicas')->nullable();
            $table->date('fecha_inicio_actividades')->nullable();
            $table->date('fecha_reinicio_actividades')->nullable();
            $table->date('fecha_cierre_establecimiento')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'codigo']);

            // If emisores table exists, add foreign key constraint if desired
            try {
                if (Schema::hasTable('emisores')) {
                    $table->foreign('company_id')->references('id')->on('emisores')->onDelete('cascade');
                }
            } catch (\Exception $_) {
                // ignore if running on environments where FK cannot be created
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('establecimientos');
    }
};

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
        Schema::create('suscripciones', function (Blueprint $table) {
            $table->id();
            
            // Relación con el emisor (company)
            $table->unsignedBigInteger('emisor_id')->comment('ID del emisor (company)');
            
            // Relación con el plan
            $table->unsignedBigInteger('plan_id')->comment('ID del plan de facturación');
            
            // Fechas de vigencia
            $table->date('fecha_inicio')->comment('Fecha de inicio de la suscripción');
            $table->date('fecha_fin')->comment('Fecha de fin de la suscripción');
            
            // Monto y comprobantes
            $table->decimal('monto', 10, 2)->comment('Monto de la suscripción');
            $table->unsignedInteger('cantidad_comprobantes')->comment('Cantidad de comprobantes asignados');
            $table->unsignedInteger('comprobantes_usados')->default(0)->comment('Comprobantes ya utilizados');
            
            // Estados
            $table->enum('estado_suscripcion', [
                'Vigente',
                'Suspendido',
                'Pendiente',
                'Programado',
                'Proximo a caducar',
                'Pocos comprobantes',
                'Proximo a caducar y con pocos comprobantes',
                'Caducado',
                'Sin comprobantes'
            ])->default('Pendiente')->comment('Estado de la suscripción');
            
            $table->enum('estado_transaccion', ['Pendiente', 'Confirmada'])
                  ->default('Pendiente')
                  ->comment('Estado de la transacción');
            
            // Forma de pago
            $table->enum('forma_pago', ['Efectivo', 'Transferencia', 'Otro'])
                  ->comment('Forma de pago');
            
            // Archivos adjuntos
            $table->string('comprobante_pago')->nullable()->comment('Ruta del comprobante de pago (imagen)');
            $table->string('factura')->nullable()->comment('Ruta de la factura (PDF)');
            
            // Campos de comisión
            $table->enum('estado_comision', ['Sin comision', 'Pendiente', 'Pagada'])
                  ->default('Sin comision')
                  ->comment('Estado de la comisión');
            $table->decimal('monto_comision', 10, 2)->default(0)->comment('Monto de la comisión');
            $table->string('comprobante_comision')->nullable()->comment('Comprobante de pago de comisión');
            
            // Auditoría
            $table->unsignedBigInteger('created_by_id')->nullable()->comment('Usuario que creó la suscripción');
            $table->unsignedBigInteger('updated_by_id')->nullable()->comment('Usuario que actualizó la suscripción');
            $table->string('ip_address', 45)->nullable()->comment('IP del usuario que creó el registro');
            $table->text('user_agent')->nullable()->comment('User agent del navegador');
            
            $table->timestamps();
            $table->softDeletes();
            
            // Índices
            $table->index('emisor_id');
            $table->index('plan_id');
            $table->index('estado_suscripcion');
            $table->index('estado_transaccion');
            $table->index('fecha_inicio');
            $table->index('fecha_fin');
            $table->index('created_by_id');
            
            // Foreign keys
            $table->foreign('emisor_id')->references('id')->on('emisores')->onDelete('cascade');
            $table->foreign('plan_id')->references('id')->on('planes')->onDelete('restrict');
            $table->foreign('created_by_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('suscripciones');
    }
};

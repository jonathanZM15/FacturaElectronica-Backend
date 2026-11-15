<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comprobantes', fn (Blueprint $table) => $table->unsignedInteger('revision_fiscal')->default(1));
        Schema::create('sri_tareas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comprobante_id')->constrained('comprobantes')->cascadeOnDelete();
            $table->unsignedInteger('revision');
            $table->string('etapa', 20);
            $table->string('estado', 16)->default('pending');
            $table->uuid('token');
            $table->string('queue_connection', 40)->default('database');
            $table->string('queue', 100)->default('default');
            $table->string('queue_job_id', 100)->nullable();
            $table->string('certificate_path')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('lease_until')->nullable();
            $table->timestamp('enqueued_at')->nullable();
            $table->timestamp('last_started_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
            $table->unique(['comprobante_id', 'revision', 'etapa'], 'sri_tarea_documento_revision_etapa');
            $table->index(['estado', 'lease_until']);
        });
    }

    public function down(): void
    {
        if (\Illuminate\Support\Facades\DB::table('sri_tareas')->exists()) {
            throw new \RuntimeException('El registro SRI contiene evidencia de tareas. Restaura un respaldo verificado para revertir sin perder trazabilidad.');
        }
        Schema::dropIfExists('sri_tareas');
        Schema::table('comprobantes', fn (Blueprint $table) => $table->dropColumn('revision_fiscal'));
    }
};

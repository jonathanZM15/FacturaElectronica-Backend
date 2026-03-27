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
        Schema::table('emisores', function (Blueprint $table) {
            // Rastrear última actividad para detectar inactividad
            $table->timestamp('last_activity_at')->nullable()->after('updated_at');
            
            // Controlar envío de notificaciones escalonadas
            $table->timestamp('deletion_warning_sent_at')->nullable()->after('last_activity_at');
            $table->timestamp('deletion_final_notice_sent_at')->nullable()->after('deletion_warning_sent_at');
            
            // Fecha programada para eliminación automática
            $table->timestamp('scheduled_deletion_at')->nullable()->after('deletion_final_notice_sent_at');
            
            // Flag para marcar que está en proceso de eliminación
            $table->boolean('is_marked_for_deletion')->default(false)->after('scheduled_deletion_at');
            
            // Información del usuario admin que solicitó la eliminación
            $table->unsignedBigInteger('deletion_requested_by')->nullable()->after('is_marked_for_deletion');
            
            // Path al archivo de backup generado
            $table->string('backup_file_path')->nullable()->after('deletion_requested_by');
            
            $table->foreign('deletion_requested_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('emisores', function (Blueprint $table) {
            $table->dropForeign(['deletion_requested_by']);
            $table->dropColumn([
                'last_activity_at',
                'deletion_warning_sent_at',
                'deletion_final_notice_sent_at',
                'scheduled_deletion_at',
                'is_marked_for_deletion',
                'deletion_requested_by',
                'backup_file_path'
            ]);
        });
    }
};

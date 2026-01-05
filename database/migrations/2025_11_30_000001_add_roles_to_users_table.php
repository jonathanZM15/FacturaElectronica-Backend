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
        Schema::table('users', function (Blueprint $table) {
            // Agregar campo role si no existe
            if (!Schema::hasColumn('users', 'role')) {
                $table->enum('role', ['administrador', 'distribuidor', 'emisor', 'gerente', 'cajero'])->default('cajero')->after('password');
            }

            // Relación con user creador (para auditoría)
            if (!Schema::hasColumn('users', 'created_by_id')) {
                $table->foreignId('created_by_id')->nullable()->constrained('users')->onDelete('set null')->after('role');
            }

            // Relación con distribuidor (si el usuario es distribuidor, quién es su distribuidor)
            if (!Schema::hasColumn('users', 'distribuidor_id')) {
                $table->foreignId('distribuidor_id')->nullable()->constrained('users')->onDelete('set null')->after('created_by_id');
            }

            // Relación con emisor (para gerentes y cajeros)
            if (!Schema::hasColumn('users', 'emisor_id')) {
                $table->foreignId('emisor_id')->nullable()->constrained('emisores')->onDelete('set null')->after('distribuidor_id');
            }

            // Establecimientos asignados (para gerentes)
            if (!Schema::hasColumn('users', 'establecimientos_ids')) {
                $table->json('establecimientos_ids')->nullable()->after('emisor_id')->comment('IDs de establecimientos asignados al gerente');
            }

            // Estado del usuario
            if (!Schema::hasColumn('users', 'estado')) {
                $table->enum('estado', ['activo', 'inactivo', 'suspendido'])->default('activo')->after('establecimientos_ids');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'role')) {
                $table->dropColumn('role');
            }
            if (Schema::hasColumn('users', 'created_by_id')) {
                $table->dropForeign(['created_by_id']);
                $table->dropColumn('created_by_id');
            }
            if (Schema::hasColumn('users', 'distribuidor_id')) {
                $table->dropForeign(['distribuidor_id']);
                $table->dropColumn('distribuidor_id');
            }
            if (Schema::hasColumn('users', 'emisor_id')) {
                $table->dropForeign(['emisor_id']);
                $table->dropColumn('emisor_id');
            }
            if (Schema::hasColumn('users', 'establecimientos_ids')) {
                $table->dropColumn('establecimientos_ids');
            }
            if (Schema::hasColumn('users', 'estado')) {
                $table->dropColumn('estado');
            }
        });
    }
};

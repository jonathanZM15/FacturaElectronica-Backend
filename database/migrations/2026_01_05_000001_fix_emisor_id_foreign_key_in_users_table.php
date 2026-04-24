<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Solo proceder en PostgreSQL
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        // Solo proceder si la tabla users existe
        if (!Schema::hasTable('users') || !Schema::hasColumn('users', 'emisor_id')) {
            return;
        }

        // Paso 1: Limpiar los valores de emisor_id que no existen en la tabla emisores
        try {
            DB::statement(
                "UPDATE users SET emisor_id = NULL 
                 WHERE emisor_id IS NOT NULL 
                 AND emisor_id NOT IN (SELECT id FROM emisores)"
            );
        } catch (\Exception $e) {
            // Ignorar si hay error
        }

        Schema::table('users', function (Blueprint $table) {
            // Paso 2: Obtener todas las foreign keys en la tabla
            try {
                $foreignKeys = DB::select(
                    "SELECT constraint_name FROM information_schema.table_constraints 
                     WHERE table_name = 'users' AND constraint_type = 'FOREIGN KEY' 
                     AND constraint_name LIKE '%emisor_id%'"
                );
                
                // Paso 3: Eliminar todas las foreign keys relacionadas con emisor_id
                foreach ($foreignKeys as $fk) {
                    try {
                        DB::statement("ALTER TABLE users DROP CONSTRAINT " . $fk->constraint_name);
                    } catch (\Exception $e) {
                        // Ignorar si la constraint no existe
                    }
                }
            } catch (\Exception $e) {
                // Ignorar si hay error consultando
            }
        });

        // Paso 4: Ahora agregar la foreign key correcta
        try {
            Schema::table('users', function (Blueprint $table) {
                $table->foreign('emisor_id')
                    ->references('id')
                    ->on('emisores')
                    ->onDelete('set null');
            });
        } catch (\Exception $e) {
            // Ignorar si la foreign key ya existe
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Solo proceder en PostgreSQL
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        // Opcional: revertir los cambios
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                try {
                    // Obtener el nombre de la foreign key
                    $foreignKeys = DB::select(
                        "SELECT constraint_name FROM information_schema.table_constraints 
                         WHERE table_name = 'users' AND constraint_type = 'FOREIGN KEY' 
                         AND constraint_name LIKE '%emisor_id%'"
                    );
                    
                    foreach ($foreignKeys as $fk) {
                        try {
                            DB::statement("ALTER TABLE users DROP CONSTRAINT " . $fk->constraint_name);
                        } catch (\Exception $e) {
                            // Ignorar si la constraint no existe
                        }
                    }
                } catch (\Exception $e) {
                    // Ignorar si hay error
                }
            });
        }
    }
};



<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Agregar establecimiento_id y permitir nulos temporalmente para el backfill
        Schema::table('bodegas', function (Blueprint $table) {
            $table->unsignedBigInteger('establecimiento_id')->nullable()->after('emisor_id');
            // Agregar codigo y permite_venta según requerimientos de Fase 0 (si no existen)
            if (!Schema::hasColumn('bodegas', 'codigo')) {
                $table->string('codigo')->nullable()->after('id');
            }
            if (!Schema::hasColumn('bodegas', 'permite_venta')) {
                $table->boolean('permite_venta')->default(true)->after('tipo');
            }
        });

        // 2. Backfill: Crear un establecimiento por defecto para cada emisor si no tiene y asignar bodegas
        $emisores = DB::table('emisores')->get();
        foreach ($emisores as $emisor) {
            $establecimientoDefault = DB::table('establecimientos')->where('emisor_id', $emisor->id)->first();
            
            if (!$establecimientoDefault) {
                $estId = DB::table('establecimientos')->insertGetId([
                    'emisor_id' => $emisor->id,
                    'codigo' => '001',
                    'estado' => 'ABIERTO',
                    'nombre' => 'Establecimiento Principal',
                    'direccion' => 'S/N',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $estId = $establecimientoDefault->id;
            }

            DB::table('bodegas')
                ->where('emisor_id', $emisor->id)
                ->update(['establecimiento_id' => $estId, 'codigo' => DB::raw("CONCAT('BOD-', id)")]);
        }

        // 3. Modificar bodegas para agregar Foreign Key, quitar emisor_id
        Schema::table('bodegas', function (Blueprint $table) {
            $table->unsignedBigInteger('establecimiento_id')->nullable(false)->change();
            $table->foreign('establecimiento_id')->references('id')->on('establecimientos')->onDelete('cascade');
            
            if (DB::getDriverName() !== 'sqlite') {
                $table->dropForeign(['emisor_id']);
            }
            $table->dropColumn('emisor_id');
        });
    }

    public function down(): void
    {
        Schema::table('bodegas', function (Blueprint $table) {
            $table->unsignedBigInteger('emisor_id')->nullable()->after('id');
        });

        // Restaurar emisor_id basándose en establecimiento
        $bodegas = DB::table('bodegas')->get();
        foreach ($bodegas as $bodega) {
            $est = DB::table('establecimientos')->where('id', $bodega->establecimiento_id)->first();
            if ($est) {
                DB::table('bodegas')->where('id', $bodega->id)->update(['emisor_id' => $est->company_id]);
            }
        }

        Schema::table('bodegas', function (Blueprint $table) {
            if (DB::getDriverName() !== 'sqlite') {
                $table->dropForeign(['establecimiento_id']);
            }
            $table->dropColumn('establecimiento_id');
            $table->dropColumn('codigo');
            $table->dropColumn('permite_venta');
            
            $table->unsignedBigInteger('emisor_id')->nullable(false)->change();
            $table->foreign('emisor_id')->references('id')->on('emisores')->onDelete('cascade');
        });
    }
};

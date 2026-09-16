<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // El spec a menudo requiere que las bodegas se codifiquen secuencialmente por establecimiento
        // ej. si el establecimiento 1 tiene 2 bodegas: BOD-01, BOD-02
        $establecimientos = DB::table('establecimientos')->pluck('id');
        
        foreach ($establecimientos as $estId) {
            $bodegas = DB::table('bodegas')->where('establecimiento_id', $estId)->orderBy('id')->get();
            $contador = 1;
            foreach ($bodegas as $bodega) {
                $nuevoCodigo = 'BOD-' . str_pad($contador, 2, '0', STR_PAD_LEFT);
                DB::table('bodegas')
                    ->where('id', $bodega->id)
                    ->update(['codigo' => $nuevoCodigo]);
                $contador++;
            }
        }
    }

    public function down(): void
    {
        // Revertir a global (BOD-{id})
        $bodegas = DB::table('bodegas')->get();
        foreach ($bodegas as $bodega) {
            DB::table('bodegas')
                ->where('id', $bodega->id)
                ->update(['codigo' => 'BOD-' . $bodega->id]);
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('emisores', function (Blueprint $table) {
            if (!Schema::hasColumn('emisores', 'numero_resolucion_contribuyente_especial')) {
                $table->string('numero_resolucion_contribuyente_especial', 50)->nullable()->after('contribuyente_especial');
            }
            if (!Schema::hasColumn('emisores', 'numero_resolucion_agente_retencion')) {
                $table->string('numero_resolucion_agente_retencion', 50)->nullable()->after('agente_retencion');
            }
        });
    }

    public function down(): void
    {
        Schema::table('emisores', function (Blueprint $table) {
            if (Schema::hasColumn('emisores', 'numero_resolucion_contribuyente_especial')) {
                $table->dropColumn('numero_resolucion_contribuyente_especial');
            }
            if (Schema::hasColumn('emisores', 'numero_resolucion_agente_retencion')) {
                $table->dropColumn('numero_resolucion_agente_retencion');
            }
        });
    }
};

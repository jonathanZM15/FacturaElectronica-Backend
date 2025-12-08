<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('emisores', function (Blueprint $table) {
            if (!Schema::hasColumn('emisores', 'ruc')) $table->string('ruc', 13)->unique()->nullable();
            if (!Schema::hasColumn('emisores', 'razon_social')) $table->string('razon_social')->nullable();
            if (!Schema::hasColumn('emisores', 'nombre_comercial')) $table->string('nombre_comercial')->nullable();
            if (!Schema::hasColumn('emisores', 'direccion_matriz')) $table->string('direccion_matriz', 500)->nullable();

            if (!Schema::hasColumn('emisores', 'regimen_tributario')) $table->string('regimen_tributario', 30)->nullable();
            if (!Schema::hasColumn('emisores', 'obligado_contabilidad')) $table->string('obligado_contabilidad', 2)->nullable(); // SI/NO
            if (!Schema::hasColumn('emisores', 'contribuyente_especial')) $table->string('contribuyente_especial', 2)->nullable(); // SI/NO
            if (!Schema::hasColumn('emisores', 'agente_retencion')) $table->string('agente_retencion', 2)->nullable(); // SI/NO
            if (!Schema::hasColumn('emisores', 'tipo_persona')) $table->string('tipo_persona', 10)->nullable(); // NATURAL/JURIDICA
            if (!Schema::hasColumn('emisores', 'codigo_artesano')) $table->string('codigo_artesano', 50)->nullable();

            if (!Schema::hasColumn('emisores', 'correo_remitente')) $table->string('correo_remitente')->nullable();
            if (!Schema::hasColumn('emisores', 'estado')) $table->string('estado', 10)->default('ACTIVO');
            if (!Schema::hasColumn('emisores', 'ambiente')) $table->string('ambiente', 15)->default('PRODUCCION');
            if (!Schema::hasColumn('emisores', 'tipo_emision')) $table->string('tipo_emision', 20)->default('NORMAL');

            if (!Schema::hasColumn('emisores', 'logo_path')) $table->string('logo_path')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('emisores', function (Blueprint $table) {
            $cols = [
                'ruc','razon_social','nombre_comercial','direccion_matriz',
                'regimen_tributario','obligado_contabilidad','contribuyente_especial','agente_retencion',
                'tipo_persona','codigo_artesano','correo_remitente','estado','ambiente','tipo_emision','logo_path'
            ];
            foreach ($cols as $c) {
                if (Schema::hasColumn('companies', $c)) $table->dropColumn($c);
            }
        });
    }
};
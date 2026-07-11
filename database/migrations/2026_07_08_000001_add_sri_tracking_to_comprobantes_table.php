<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comprobantes', function (Blueprint $table) {
            if (!Schema::hasColumn('comprobantes', 'sri_estado_recepcion')) {
                $table->string('sri_estado_recepcion', 20)->nullable()->after('estado_sri');
            }

            if (!Schema::hasColumn('comprobantes', 'sri_estado_autorizacion')) {
                $table->string('sri_estado_autorizacion', 20)->nullable()->after('sri_estado_recepcion');
            }

            if (!Schema::hasColumn('comprobantes', 'xml_generado')) {
                $table->longText('xml_generado')->nullable()->after('tipo_emision');
            }

            if (!Schema::hasColumn('comprobantes', 'xml_firmado')) {
                $table->longText('xml_firmado')->nullable()->after('xml_generado');
            }

            if (!Schema::hasColumn('comprobantes', 'ultima_peticion_recepcion')) {
                $table->longText('ultima_peticion_recepcion')->nullable()->after('xml_firmado');
            }

            if (!Schema::hasColumn('comprobantes', 'ultima_respuesta_recepcion')) {
                $table->longText('ultima_respuesta_recepcion')->nullable()->after('ultima_peticion_recepcion');
            }

            if (!Schema::hasColumn('comprobantes', 'ultima_peticion_autorizacion')) {
                $table->longText('ultima_peticion_autorizacion')->nullable()->after('ultima_respuesta_recepcion');
            }

            if (!Schema::hasColumn('comprobantes', 'ultima_respuesta_autorizacion')) {
                $table->longText('ultima_respuesta_autorizacion')->nullable()->after('ultima_peticion_autorizacion');
            }

            if (!Schema::hasColumn('comprobantes', 'ultimo_error_sri')) {
                $table->text('ultimo_error_sri')->nullable()->after('ultima_respuesta_autorizacion');
            }

            if (!Schema::hasColumn('comprobantes', 'sri_intentos_envio')) {
                $table->unsignedSmallInteger('sri_intentos_envio')->default(0)->after('ultimo_error_sri');
            }

            if (!Schema::hasColumn('comprobantes', 'sri_intentos_autorizacion')) {
                $table->unsignedSmallInteger('sri_intentos_autorizacion')->default(0)->after('sri_intentos_envio');
            }

            if (!Schema::hasColumn('comprobantes', 'firmado_en')) {
                $table->timestamp('firmado_en')->nullable()->after('sri_intentos_autorizacion');
            }

            if (!Schema::hasColumn('comprobantes', 'enviado_en')) {
                $table->timestamp('enviado_en')->nullable()->after('firmado_en');
            }

            if (!Schema::hasColumn('comprobantes', 'recibido_en')) {
                $table->timestamp('recibido_en')->nullable()->after('enviado_en');
            }

            if (!Schema::hasColumn('comprobantes', 'autorizado_en')) {
                $table->timestamp('autorizado_en')->nullable()->after('recibido_en');
            }
        });
    }

    public function down(): void
    {
        Schema::table('comprobantes', function (Blueprint $table) {
            foreach ([
                'autorizado_en',
                'recibido_en',
                'enviado_en',
                'firmado_en',
                'sri_intentos_autorizacion',
                'sri_intentos_envio',
                'ultimo_error_sri',
                'ultima_respuesta_autorizacion',
                'ultima_peticion_autorizacion',
                'ultima_respuesta_recepcion',
                'ultima_peticion_recepcion',
                'xml_firmado',
                'xml_generado',
                'sri_estado_autorizacion',
                'sri_estado_recepcion',
            ] as $column) {
                if (Schema::hasColumn('comprobantes', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
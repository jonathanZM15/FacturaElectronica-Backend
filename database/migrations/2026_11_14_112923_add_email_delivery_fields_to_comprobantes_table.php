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
        Schema::table('comprobantes', function (Blueprint $table) {
            if (!Schema::hasColumn('comprobantes', 'email_estado')) {
                $table->string('email_estado', 20)->default('PENDIENTE')->nullable()->after('autorizado_en');
            }
            if (!Schema::hasColumn('comprobantes', 'email_intentos')) {
                $table->unsignedSmallInteger('email_intentos')->default(0)->after('email_estado');
            }
            if (!Schema::hasColumn('comprobantes', 'email_enviado_en')) {
                $table->timestamp('email_enviado_en')->nullable()->after('email_intentos');
            }
            if (!Schema::hasColumn('comprobantes', 'email_error')) {
                $table->text('email_error')->nullable()->after('email_enviado_en');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('comprobantes', function (Blueprint $table) {
            foreach (['email_error', 'email_enviado_en', 'email_intentos', 'email_estado'] as $column) {
                if (Schema::hasColumn('comprobantes', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

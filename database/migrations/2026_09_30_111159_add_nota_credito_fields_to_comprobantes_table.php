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
            $table->unsignedBigInteger('comprobante_modificado_id')->nullable()->after('cliente_id');
            $table->string('motivo_modificacion', 300)->nullable()->after('comprobante_modificado_id');

            $table->foreign('comprobante_modificado_id')->references('id')->on('comprobantes')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('comprobantes', function (Blueprint $table) {
            $table->dropForeign(['comprobante_modificado_id']);
            $table->dropColumn('comprobante_modificado_id');
            $table->dropColumn('motivo_modificacion');
        });
    }
};

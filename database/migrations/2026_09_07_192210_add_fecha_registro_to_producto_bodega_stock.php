<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('producto_bodega_stock', function (Blueprint $table) {
            $table->timestamp('fecha_registro')->nullable()->after('observacion');
        });

        // Set fecha_registro to created_at for existing records
        DB::statement('UPDATE producto_bodega_stock SET fecha_registro = created_at WHERE fecha_registro IS NULL');
    }

    public function down(): void
    {
        Schema::table('producto_bodega_stock', function (Blueprint $table) {
            $table->dropColumn('fecha_registro');
        });
    }
};

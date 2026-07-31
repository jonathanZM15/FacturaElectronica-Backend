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
        Schema::table('producto_bodega_stock', function (Blueprint $table) {
            $table->decimal('stock_fisico', 14, 6)->default(0)->after('observacion');
            $table->decimal('stock_disponible', 14, 6)->default(0)->after('stock_fisico');
            $table->decimal('stock_reservado', 14, 6)->default(0)->after('stock_disponible');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('producto_bodega_stock', function (Blueprint $table) {
            $table->dropColumn(['stock_fisico', 'stock_disponible', 'stock_reservado']);
        });
    }
};
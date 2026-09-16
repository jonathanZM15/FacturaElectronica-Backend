<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('registro_operativo_detalles', function (Blueprint $table) {
            if (!Schema::hasColumn('registro_operativo_detalles', 'cantidad_faltante')) {
                $table->decimal('cantidad_faltante', 14, 6)->default(0)->after('cantidad_sobrante');
            }
            if (!Schema::hasColumn('registro_operativo_detalles', 'cantidad_danada')) {
                $table->decimal('cantidad_danada', 14, 6)->default(0)->after('cantidad_faltante');
            }
        });
    }

    public function down()
    {
        Schema::table('registro_operativo_detalles', function (Blueprint $table) {
            $table->dropColumn(['cantidad_faltante', 'cantidad_danada']);
        });
    }
};

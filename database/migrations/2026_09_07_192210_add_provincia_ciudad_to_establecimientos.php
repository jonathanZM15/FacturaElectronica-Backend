<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('establecimientos', function (Blueprint $table) {
            $table->string('provincia')->nullable()->after('direccion');
            $table->string('ciudad')->nullable()->after('provincia');
        });
    }

    public function down(): void
    {
        Schema::table('establecimientos', function (Blueprint $table) {
            $table->dropColumn(['provincia', 'ciudad']);
        });
    }
};

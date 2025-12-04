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
        Schema::table('user_verification_tokens', function (Blueprint $table) {
            $table->json('metadata')->nullable()->after('type')->comment('Información adicional como estado_anterior del usuario');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_verification_tokens', function (Blueprint $table) {
            $table->dropColumn('metadata');
        });
    }
};

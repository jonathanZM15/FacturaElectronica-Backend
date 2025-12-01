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
        Schema::table('users', function (Blueprint $table) {
            // Agregar nuevos campos después del email
            $table->string('cedula', 10)->unique()->nullable()->after('email');
            $table->string('nombres')->nullable()->after('cedula');
            $table->string('apellidos')->nullable()->after('nombres');
            $table->string('username')->unique()->nullable()->after('apellidos');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['cedula']);
            $table->dropUnique(['username']);
            $table->dropColumn(['cedula', 'nombres', 'apellidos', 'username']);
        });
    }
};

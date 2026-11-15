<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Las cuentas ya existentes conservan su acceso.
            $table->boolean('password_setup_required')->default(false);
        });
        DB::table('users')->where('estado', 'nuevo')->update(['password_setup_required' => true]);
        DB::table('user_verification_tokens')->orderBy('id')->chunkById(200, function ($rows) {
            foreach ($rows as $row) {
                if (!str_starts_with($row->token, 'sha256:')) {
                    DB::table('user_verification_tokens')->where('id', $row->id)
                        ->where('token', $row->token)
                        ->update(['token' => 'sha256:'.hash('sha256', $row->token)]);
                }
            }
        });
    }

    public function down(): void
    {
        // Los resúmenes no se pueden convertir a texto; se conservan protegidos.
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('password_setup_required'));
    }
};

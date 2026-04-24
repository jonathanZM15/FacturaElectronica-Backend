<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Solo aplicar cambios en PostgreSQL
        if (DB::getDriverName() === 'pgsql') {
            // Cambiar la columna 'type' de enum a VARCHAR para soportar nuevos tipos
            DB::statement("ALTER TABLE user_verification_tokens DROP CONSTRAINT IF EXISTS user_verification_tokens_type_check");
            DB::statement("ALTER TABLE user_verification_tokens ALTER COLUMN type TYPE VARCHAR(40)");
            DB::statement("ALTER TABLE user_verification_tokens ADD CONSTRAINT user_verification_tokens_type_check CHECK (type IN ('email_verification', 'password_change', 'password_setup', 'email_change_confirmation'))");
        }
        // SQLite maneja strings, no requiere cambios
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE user_verification_tokens DROP CONSTRAINT IF EXISTS user_verification_tokens_type_check");
            DB::statement("ALTER TABLE user_verification_tokens ALTER COLUMN type TYPE VARCHAR(40)");
            DB::statement("ALTER TABLE user_verification_tokens ADD CONSTRAINT user_verification_tokens_type_check CHECK (type IN ('email_verification', 'password_change'))");
        }
    }
};



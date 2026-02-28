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
        Schema::create('user_audit', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('target_user_id');
            $table->string('action', 60);
            $table->text('description');
            $table->json('changes')->nullable();

            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->string('actor_role', 50)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();

            // Fecha + hora del registro de auditoría
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('target_user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');

            $table->foreign('actor_user_id')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            $table->index(['target_user_id', 'created_at']);
            $table->index(['actor_user_id', 'created_at']);
            $table->index('action');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_audit');
    }
};

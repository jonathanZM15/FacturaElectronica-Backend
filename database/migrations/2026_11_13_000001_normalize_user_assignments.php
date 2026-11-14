<?php

use App\Casts\IdList;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_scope_normalization_backups', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->primary();
            $table->text('establecimientos_ids')->nullable();
            $table->text('puntos_emision_ids')->nullable();
        });
        DB::table('users')->select('id', 'establecimientos_ids', 'puntos_emision_ids')->orderBy('id')->chunkById(200, function ($users) {
            foreach ($users as $user) {
                DB::table('user_scope_normalization_backups')->insert([
                    'user_id' => $user->id, 'establecimientos_ids' => $user->establecimientos_ids, 'puntos_emision_ids' => $user->puntos_emision_ids,
                ]);
                $changes = [];
                foreach (['establecimientos_ids', 'puntos_emision_ids'] as $field) {
                    $changes[$field] = json_encode(IdList::normalize($user->$field), JSON_THROW_ON_ERROR);
                }
                DB::table('users')->where('id', $user->id)->update($changes);
            }
        });
    }

    public function down(): void
    {
        DB::table('user_scope_normalization_backups')->orderBy('user_id')->chunkById(200, function ($backups) {
            foreach ($backups as $backup) {
                $user = DB::table('users')->where('id', $backup->user_id)->first();
                if (!$user) continue;
                $restore = [];
                foreach (['establecimientos_ids', 'puntos_emision_ids'] as $field) {
                    // Restore only unchanged values; preserve assignments edited after migration.
                    if (json_decode($user->$field ?? 'null', true) === IdList::normalize($backup->$field)) $restore[$field] = $backup->$field;
                }
                if ($restore) DB::table('users')->where('id', $user->id)->update($restore);
            }
        }, 'user_id');
        Schema::dropIfExists('user_scope_normalization_backups');
    }
};

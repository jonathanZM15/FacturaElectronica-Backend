<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // establecimientos.company_id -> establecimientos.emisor_id
        if (Schema::hasTable('establecimientos')) {
            if (Schema::hasColumn('establecimientos', 'company_id') && !Schema::hasColumn('establecimientos', 'emisor_id')) {
                Schema::table('establecimientos', function (Blueprint $table) {
                    $table->renameColumn('company_id', 'emisor_id');
                });
            }
        }

        // puntos_emision.company_id -> puntos_emision.emisor_id
        if (Schema::hasTable('puntos_emision')) {
            if (Schema::hasColumn('puntos_emision', 'company_id') && !Schema::hasColumn('puntos_emision', 'emisor_id')) {
                Schema::table('puntos_emision', function (Blueprint $table) {
                    $table->renameColumn('company_id', 'emisor_id');
                });
            }
        }

        // company_deletion_logs -> emisor_deletion_logs
        if (Schema::hasTable('company_deletion_logs') && !Schema::hasTable('emisor_deletion_logs')) {
            Schema::rename('company_deletion_logs', 'emisor_deletion_logs');
        }

        // emisor_deletion_logs.company_id -> emisor_deletion_logs.emisor_id
        if (Schema::hasTable('emisor_deletion_logs')) {
            if (Schema::hasColumn('emisor_deletion_logs', 'company_id') && !Schema::hasColumn('emisor_deletion_logs', 'emisor_id')) {
                Schema::table('emisor_deletion_logs', function (Blueprint $table) {
                    $table->renameColumn('company_id', 'emisor_id');
                });
            }
        }
    }

    public function down(): void
    {
        // Reverse columnas en establecimientos
        if (Schema::hasTable('establecimientos')) {
            if (Schema::hasColumn('establecimientos', 'emisor_id') && !Schema::hasColumn('establecimientos', 'company_id')) {
                Schema::table('establecimientos', function (Blueprint $table) {
                    $table->renameColumn('emisor_id', 'company_id');
                });
            }
        }

        // Reverse columnas en puntos_emision
        if (Schema::hasTable('puntos_emision')) {
            if (Schema::hasColumn('puntos_emision', 'emisor_id') && !Schema::hasColumn('puntos_emision', 'company_id')) {
                Schema::table('puntos_emision', function (Blueprint $table) {
                    $table->renameColumn('emisor_id', 'company_id');
                });
            }
        }

        // Reverse tabla y columna de logs
        if (Schema::hasTable('emisor_deletion_logs')) {
            if (Schema::hasColumn('emisor_deletion_logs', 'emisor_id') && !Schema::hasColumn('emisor_deletion_logs', 'company_id')) {
                Schema::table('emisor_deletion_logs', function (Blueprint $table) {
                    $table->renameColumn('emisor_id', 'company_id');
                });
            }

            if (!Schema::hasTable('company_deletion_logs')) {
                Schema::rename('emisor_deletion_logs', 'company_deletion_logs');
            }
        }
    }
};

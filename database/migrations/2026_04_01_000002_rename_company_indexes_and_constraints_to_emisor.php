<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        // =============================
        // establecimientos
        // =============================
        $this->renamePgConstraintIfExists('establecimientos', 'establecimientos_company_id_foreign', 'establecimientos_emisor_id_foreign');
        $this->renamePgConstraintIfExists('establecimientos', 'establecimientos_company_id_codigo_unique', 'establecimientos_emisor_id_codigo_unique');

        $this->renamePgIndexIfExists('establecimientos_company_id_index', 'establecimientos_emisor_id_index');
        $this->renamePgIndexIfExists('establecimientos_company_id_codigo_unique', 'establecimientos_emisor_id_codigo_unique');
        $this->renamePgIndexIfExists('idx_estab_company_estado', 'idx_estab_emisor_estado');

        // =============================
        // puntos_emision
        // =============================
        $this->renamePgConstraintIfExists('puntos_emision', 'puntos_emision_company_id_foreign', 'puntos_emision_emisor_id_foreign');
        $this->renamePgConstraintIfExists('puntos_emision', 'puntos_emision_codigo_establecimiento_company_unique', 'puntos_emision_codigo_establecimiento_emisor_unique');

        $this->renamePgIndexIfExists('puntos_emision_company_id_index', 'puntos_emision_emisor_id_index');
        $this->renamePgIndexIfExists('puntos_emision_codigo_establecimiento_company_unique', 'puntos_emision_codigo_establecimiento_emisor_unique');
        $this->renamePgIndexIfExists('idx_puntos_company_estab_estado', 'idx_puntos_emisor_estab_estado');
        $this->renamePgIndexIfExists('idx_puntos_company_user', 'idx_puntos_emisor_user');

        // =============================
        // emisor_deletion_logs (antes company_deletion_logs)
        // =============================
        $this->renamePgConstraintIfExists('emisor_deletion_logs', 'company_deletion_logs_company_id_foreign', 'emisor_deletion_logs_emisor_id_foreign');
        $this->renamePgConstraintIfExists('emisor_deletion_logs', 'company_deletion_logs_user_id_foreign', 'emisor_deletion_logs_user_id_foreign');
        $this->renamePgConstraintIfExists('emisor_deletion_logs', 'company_deletion_logs_pkey', 'emisor_deletion_logs_pkey');
        $this->renamePgConstraintIfExists('emisor_deletion_logs', 'company_deletion_logs_action_type_check', 'emisor_deletion_logs_action_type_check');

        $this->renamePgIndexIfExists('company_deletion_logs_company_id_action_type_index', 'emisor_deletion_logs_emisor_id_action_type_index');
        $this->renamePgIndexIfExists('company_deletion_logs_action_type_index', 'emisor_deletion_logs_action_type_index');
        $this->renamePgIndexIfExists('company_deletion_logs_created_at_index', 'emisor_deletion_logs_created_at_index');
        $this->renamePgIndexIfExists('company_deletion_logs_pkey', 'emisor_deletion_logs_pkey');
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        // =============================
        // establecimientos
        // =============================
        $this->renamePgConstraintIfExists('establecimientos', 'establecimientos_emisor_id_foreign', 'establecimientos_company_id_foreign');
        $this->renamePgConstraintIfExists('establecimientos', 'establecimientos_emisor_id_codigo_unique', 'establecimientos_company_id_codigo_unique');

        $this->renamePgIndexIfExists('establecimientos_emisor_id_index', 'establecimientos_company_id_index');
        $this->renamePgIndexIfExists('establecimientos_emisor_id_codigo_unique', 'establecimientos_company_id_codigo_unique');
        $this->renamePgIndexIfExists('idx_estab_emisor_estado', 'idx_estab_company_estado');

        // =============================
        // puntos_emision
        // =============================
        $this->renamePgConstraintIfExists('puntos_emision', 'puntos_emision_emisor_id_foreign', 'puntos_emision_company_id_foreign');
        $this->renamePgConstraintIfExists('puntos_emision', 'puntos_emision_codigo_establecimiento_emisor_unique', 'puntos_emision_codigo_establecimiento_company_unique');

        $this->renamePgIndexIfExists('puntos_emision_emisor_id_index', 'puntos_emision_company_id_index');
        $this->renamePgIndexIfExists('puntos_emision_codigo_establecimiento_emisor_unique', 'puntos_emision_codigo_establecimiento_company_unique');
        $this->renamePgIndexIfExists('idx_puntos_emisor_estab_estado', 'idx_puntos_company_estab_estado');
        $this->renamePgIndexIfExists('idx_puntos_emisor_user', 'idx_puntos_company_user');

        // =============================
        // emisor_deletion_logs (antes company_deletion_logs)
        // =============================
        $this->renamePgConstraintIfExists('emisor_deletion_logs', 'emisor_deletion_logs_emisor_id_foreign', 'company_deletion_logs_company_id_foreign');
        $this->renamePgConstraintIfExists('emisor_deletion_logs', 'emisor_deletion_logs_user_id_foreign', 'company_deletion_logs_user_id_foreign');
        $this->renamePgConstraintIfExists('emisor_deletion_logs', 'emisor_deletion_logs_pkey', 'company_deletion_logs_pkey');
        $this->renamePgConstraintIfExists('emisor_deletion_logs', 'emisor_deletion_logs_action_type_check', 'company_deletion_logs_action_type_check');

        $this->renamePgIndexIfExists('emisor_deletion_logs_emisor_id_action_type_index', 'company_deletion_logs_company_id_action_type_index');
        $this->renamePgIndexIfExists('emisor_deletion_logs_action_type_index', 'company_deletion_logs_action_type_index');
        $this->renamePgIndexIfExists('emisor_deletion_logs_created_at_index', 'company_deletion_logs_created_at_index');
        $this->renamePgIndexIfExists('emisor_deletion_logs_pkey', 'company_deletion_logs_pkey');
    }

    private function renamePgIndexIfExists(string $from, string $to): void
    {
        if (!$this->pgIndexExists($from) || $this->pgIndexExists($to)) {
            return;
        }

        DB::statement(sprintf(
            'ALTER INDEX %s RENAME TO %s',
            $this->pgQualifiedName($from),
            $this->pgQuoteIdent($to)
        ));
    }

    private function renamePgConstraintIfExists(string $table, string $from, string $to): void
    {
        if (!$this->pgConstraintExists($table, $from) || $this->pgConstraintExists($table, $to)) {
            return;
        }

        DB::statement(sprintf(
            'ALTER TABLE %s RENAME CONSTRAINT %s TO %s',
            $this->pgQualifiedName($table),
            $this->pgQuoteIdent($from),
            $this->pgQuoteIdent($to)
        ));
    }

    private function pgIndexExists(string $indexName): bool
    {
        $result = DB::select(
            "SELECT 1 FROM pg_indexes WHERE schemaname = 'public' AND indexname = ? LIMIT 1",
            [$indexName]
        );

        return !empty($result);
    }

    private function pgConstraintExists(string $table, string $constraintName): bool
    {
        $result = DB::select(
            "SELECT 1\n" .
            "FROM pg_constraint c\n" .
            "JOIN pg_class t ON t.oid = c.conrelid\n" .
            "JOIN pg_namespace n ON n.oid = t.relnamespace\n" .
            "WHERE n.nspname = 'public' AND t.relname = ? AND c.conname = ?\n" .
            "LIMIT 1",
            [$table, $constraintName]
        );

        return !empty($result);
    }

    private function pgQuoteIdent(string $identifier): string
    {
        return '"' . str_replace('"', '""', $identifier) . '"';
    }

    private function pgQualifiedName(string $identifier): string
    {
        return $this->pgQuoteIdent('public') . '.' . $this->pgQuoteIdent($identifier);
    }
};

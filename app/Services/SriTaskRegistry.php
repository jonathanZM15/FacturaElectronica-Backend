<?php

namespace App\Services;

use App\Jobs\{ConsultarAutorizacionSriJob, ProcesarFacturaSriJob};
use App\Models\{Comprobante, SriTask};
use Illuminate\Support\Facades\{DB, Storage};

class SriTaskRegistry
{
    public function pending(SriTask $task): bool
    {
        if (!in_array($task->estado, ['pending', 'running'], true)) return false;
        $connection = config('queue.connections.'.$task->queue_connection.'.connection') ?: config('database.default');
        $table = config('queue.connections.'.$task->queue_connection.'.table', 'jobs');
        if ($task->queue_job_id && DB::connection($connection)->table($table)->where('id', $task->queue_job_id)->exists()) return true;
        // DatabaseQueue releases a job by deleting/reinserting its row. Its task
        // token survives this operation; the numeric queue ID is only a cache.
        foreach (DB::connection($connection)->table($table)->where('queue', $task->queue)->select(['id', 'payload'])->cursor() as $row) {
            $payload = json_decode($row->payload, true);
            $command = $payload['data']['command'] ?? null;
            if (is_string($command) && str_contains($command, 's:9:"taskToken";s:36:"'.$task->token.'";')
                && preg_match('/s:6:"taskId";i:'.(int) $task->id.';/', $command)) {
                $task->update(['queue_job_id' => (string) $row->id]);
                return true;
            }
        }
        return $task->lease_until?->isFuture()
            || (!$task->queue_job_id && $task->updated_at->gt(now()->subSeconds(180)));
    }

    /** Recognize pre-migration jobs without executing their serialized PHP payload. */
    public function legacyJobPending(int $invoiceId, string $stage): bool
    {
        $class = $stage === 'procesamiento' ? ProcesarFacturaSriJob::class : ConsultarAutorizacionSriJob::class;
        $connection = config('queue.connections.database.connection') ?: config('database.default');
        foreach (DB::connection($connection)->table(config('queue.connections.database.table', 'jobs'))->select('payload')->cursor() as $row) {
            $payload = json_decode($row->payload, true);
            if (($payload['data']['commandName'] ?? $payload['displayName'] ?? '') !== $class) continue;
            $command = $payload['data']['command'] ?? '';
            if (!is_string($command) || preg_match('/s:6:"taskId";i:[0-9]+;/', $command)) continue;
            if (preg_match('/s:13:"comprobanteId";i:([0-9]+);/', $command, $match) && (int) $match[1] === $invoiceId) return true;
        }
        return false;
    }

    public function begin(?int $id, ?string $token, ?int $revision, int $invoiceId): bool
    {
        if ($revision !== null && (int) Comprobante::whereKey($invoiceId)->value('revision_fiscal') !== $revision) return false;
        if ($id === null) return true; // Queued jobs created before this migration remain recoverable.
        return DB::transaction(function () use ($id, $token, $invoiceId, $revision) {
            $task = SriTask::whereKey($id)->lockForUpdate()->first();
            if (!$task || $task->token !== $token || (int) $task->comprobante_id !== $invoiceId
                || (int) $task->revision !== $revision || $task->estado === 'done') return false;
            $task->update(['estado' => 'running', 'last_started_at' => now(), 'lease_until' => now()->addSeconds(180), 'attempts' => $task->attempts + 1]);
            return true;
        });
    }

    public function finish(?int $id, ?string $token, string $state = 'done', ?string $error = null): void
    {
        if ($id === null) return;
        SriTask::whereKey($id)->where('token', $token)->update([
            'estado' => $state, 'lease_until' => null,
            'last_error' => SensitiveDataSanitizer::clean($error), 'updated_at' => now(),
        ]);
    }

    public function deleteCertificateIfUnused(string $path, int $minimumAge = 0): bool
    {
        $path = str_replace('\\', '/', $path);
        if (!str_starts_with($path, 'sri/trabajos/') || str_contains($path, '..') || !str_ends_with($path, '.p12')) return false;
        $disk = Storage::disk(config('sri.certificate_disk', 'local'));
        if (!$disk->exists($path) || $disk->lastModified($path) > time() - $minimumAge) return false;
        try {
            if (SriTask::where('certificate_path', $path)->whereIn('estado', ['pending', 'running', 'failed'])->exists()) return false;
            // Database queues retain a row while a worker is running or waiting to retry.
            // Failed payloads must also keep their certificate until explicitly resolved.
            $sources = [
                [config('queue.connections.database.connection') ?: config('database.default'), config('queue.connections.database.table', 'jobs')],
                [config('queue.failed.database') ?: config('database.default'), config('queue.failed.table', 'failed_jobs')],
            ];
            foreach ($sources as [$connection, $table]) {
                foreach (DB::connection($connection)->table($table)->select('payload')->cursor() as $row) {
                    if (str_contains($row->payload, basename($path))) return false;
                }
            }
        } catch (\Throwable $e) {
            return false; // A failed reference check is not evidence that a file is orphaned.
        }
        return $disk->delete($path);
    }
}

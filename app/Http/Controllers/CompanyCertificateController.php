<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Exceptions\SriFirmaException;
use App\Models\CompanyCertificate;
use App\Services\{BusinessAccess, SriSignatureService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Storage};

class CompanyCertificateController extends Controller
{
    public function show(Request $request, int $emisorId, SriSignatureService $signer)
    {
        abort_unless(\App\Services\PermissionService::tienePermiso($request->user(), 'comprobantes.read'), 403);
        BusinessAccess::company($request->user(), $emisorId);
        $certificate = CompanyCertificate::where('emisor_id', $emisorId)->first();
        if (!$certificate) {
            return response()->json(['configured' => false, 'original_name' => null]);
        }

        $info = null;
        try {
            $disk = Storage::disk(config('sri.certificate_disk', 'local'));
            if ($disk->exists($certificate->path)) {
                $info = $signer->verificarP12($disk->path($certificate->path), $certificate->password);
            }
        } catch (\Throwable $e) {
            $info = ['error' => $e->getMessage(), 'vigente' => false];
        }

        return response()->json([
            'configured' => true,
            'original_name' => $certificate->original_name,
            'emitido_a' => $info['subject'] ?? null,
            'emitido_por' => $info['issuer'] ?? null,
            'valido_desde' => $info['valid_from'] ?? null,
            'valido_hasta' => $info['valid_to'] ?? null,
            'vigente' => $info['vigente'] ?? false,
            'error_vigencia' => $info['error'] ?? null,
        ]);
    }

    public function store(Request $request, int $emisorId, SriSignatureService $signer)
    {
        BusinessAccess::company($request->user(), $emisorId);
        abort_unless($request->user()->role === UserRole::EMISOR, 403, 'Solo el Emisor administra la firma de su empresa.');
        $request->validate(['firma' => ['required', 'file', 'extensions:p12,pfx', 'max:5120'], 'password' => ['required', 'string']]);
        $disk = Storage::disk(config('sri.certificate_disk', 'local'));
        $path = $request->file('firma')->store('sri/empresas/'.$emisorId, config('sri.certificate_disk', 'local'));
        try {
            $signer->verificarP12($disk->path($path), $request->input('password'));
            $previous = CompanyCertificate::where('emisor_id', $emisorId)->first()?->path;
            DB::transaction(fn () => CompanyCertificate::updateOrCreate(['emisor_id' => $emisorId], ['path' => $path, 'password' => $request->input('password'), 'original_name' => $request->file('firma')->getClientOriginalName(), 'updated_by' => $request->user()->id]));
        } catch (\Throwable $e) {
            $disk->delete($path);
            if ($e instanceof SriFirmaException) return response()->json(['message' => $e->getMessage(), 'errors' => ['firma' => [$e->getMessage()]]], 422);
            throw $e;
        }
        if ($previous && $previous !== $path) $disk->delete($previous);
        return response()->json(['message' => 'Firma de la empresa configurada.', 'configured' => true]);
    }
}

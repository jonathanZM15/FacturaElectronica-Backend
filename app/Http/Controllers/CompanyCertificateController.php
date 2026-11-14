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
    public function show(Request $request, int $emisorId)
    {
        abort_unless(\App\Services\PermissionService::tienePermiso($request->user(), 'comprobantes.read'), 403);
        BusinessAccess::company($request->user(), $emisorId);
        $certificate = CompanyCertificate::where('emisor_id', $emisorId)->first();
        return response()->json(['configured' => $certificate !== null, 'original_name' => $certificate?->original_name]);
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

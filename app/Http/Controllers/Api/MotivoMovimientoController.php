<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MotivoMovimiento;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MotivoMovimientoController extends Controller
{
    use \App\Traits\ResolvesEmisor;

    public function index(Request $request, string $emisorId): JsonResponse
    {
        $resolvedId = $this->resolveEmisorId($emisorId);
        \Illuminate\Support\Facades\Log::info("MotivoMovimientoController::index called with emisorId: {$emisorId} resolved: " . ($resolvedId ?? 'NULL'));
        if (!$resolvedId) {
            return response()->json(['message' => 'Emisor no encontrado.'], 404);
        }

        $query = MotivoMovimiento::where('emisor_id', $resolvedId)
            ->where('activo', true);

        if ($request->filled('tipo_movimiento')) {
            $tipo = $request->input('tipo_movimiento');
            $query->where('tipo_movimiento', $tipo);
        }

        $motivos = $query->orderBy('codigo')->get();

        return response()->json(['data' => $motivos]);
    }
}

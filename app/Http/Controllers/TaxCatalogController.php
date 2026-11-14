<?php

namespace App\Http\Controllers;

use App\Models\TipoImpuesto;
use App\Services\PermissionService;
use Illuminate\Http\Request;

class TaxCatalogController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(PermissionService::tienePermiso($request->user(), 'productos.read'), 403);
        return response()->json(['data' => TipoImpuesto::where('estado', 'Activo')->where('tipo_impuesto', 'IVA')->orderBy('nombre')->get(['id', 'nombre', 'codigo_impuesto', 'codigo_porcentaje', 'valor_tarifa', 'tipo_tarifa'])]);
    }
}

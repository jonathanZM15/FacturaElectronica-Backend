<?php

namespace App\Http\Controllers;

use App\Models\Establecimiento;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EstablecimientoController extends Controller
{
    // List establecimientos for a company
    public function index($companyId)
    {
        $items = Establecimiento::where('company_id', $companyId)->orderBy('id', 'desc')->get();
        return response()->json(['data' => $items]);
    }

    // Check code uniqueness within a company
    public function checkCode($companyId, $code)
    {
        $exists = Establecimiento::where('company_id', $companyId)->where('codigo', $code)->exists();
        return response()->json(['exists' => $exists, 'available' => !$exists]);
    }

    public function store(Request $request, $companyId)
    {
        // Basic validation
        $rules = [
            'codigo' => ['required','string','max:100'],
            'estado' => ['required','in:ABIERTO,CERRADO'],
            'nombre' => ['required','string','max:255'],
            'nombre_comercial' => ['nullable','string','max:255'],
            'direccion' => ['required','string','max:500'],
            'correo' => ['nullable','email','max:255'],
            'telefono' => ['nullable','string','max:50'],
            'actividades_economicas' => ['nullable','string'],
            'fecha_inicio_actividades' => ['nullable','date'],
            'fecha_reinicio_actividades' => ['nullable','date'],
            'fecha_cierre_establecimiento' => ['nullable','date'],
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json(['message' => 'Validation error', 'errors' => $validator->errors()], 422);
        }

        // Check unique code per company
        $code = $request->input('codigo');
        if (Establecimiento::where('company_id', $companyId)->where('codigo', $code)->exists()) {
            return response()->json(['message' => 'Código ya registrado para este emisor', 'errors' => ['codigo' => ['Código ya registrado']]], 422);
        }

        $data = $validator->validated();
        $data['company_id'] = $companyId;

        // logo file handling
        if ($request->hasFile('logo')) {
            $path = $request->file('logo')->store('establecimientos/logos', 'public');
            $data['logo_path'] = $path;
        }

        if (Auth::check()) {
            if (Schema::hasColumn('establecimientos','created_by')) $data['created_by'] = Auth::id();
            if (Schema::hasColumn('establecimientos','updated_by')) $data['updated_by'] = Auth::id();
        }

        $est = Establecimiento::create($data);

        return response()->json(['data' => $est], 201);
    }

    public function show($companyId, $id)
    {
        $est = Establecimiento::where('company_id', $companyId)->with(['creator:id,name', 'updater:id,name'])->findOrFail($id);
        
        // Similar al emisor, verificar si el código puede ser editado
        // (Si hay comprobantes autorizados asociados al establecimiento, no se puede editar el código)
        $codigoEditable = true;
        try {
            if (Schema::hasTable('comprobantes')) {
                $exists = DB::table('comprobantes')
                    ->where('establecimiento_id', $est->id)
                    ->where('estado', 'AUTORIZADO')
                    ->exists();
                if ($exists) $codigoEditable = false;
            }
        } catch (\Exception $e) {
            Log::warning('Could not check comprobantes for establecimiento '.$est->id.': '.$e->getMessage());
            $codigoEditable = true;
        }

        $payload = array_merge($est->toArray(), [
            'logo_url' => $est->logo_url,
            'codigo_editable' => $codigoEditable,
            'created_by_name' => $est->created_by_name,
            'updated_by_name' => $est->updated_by_name,
        ]);

        return response()->json(['data' => $payload]);
    }

    public function update(Request $request, $companyId, $id)
    {
        $est = Establecimiento::where('company_id', $companyId)->findOrFail($id);

        $rules = [
            'codigo' => ['sometimes','required','string','max:100'],
            'estado' => ['sometimes','required','in:ABIERTO,CERRADO'],
            'nombre' => ['sometimes','required','string','max:255'],
            'nombre_comercial' => ['sometimes','nullable','string','max:255'],
            'direccion' => ['sometimes','required','string','max:500'],
            'correo' => ['sometimes','nullable','email','max:255'],
            'telefono' => ['sometimes','nullable','string','max:50'],
            'actividades_economicas' => ['sometimes','nullable','string'],
            'fecha_inicio_actividades' => ['sometimes','nullable','date'],
            'fecha_reinicio_actividades' => ['sometimes','nullable','date'],
            'fecha_cierre_establecimiento' => ['sometimes','nullable','date'],
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json(['message' => 'Validation error', 'errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        if (isset($data['codigo']) && $data['codigo'] !== $est->codigo) {
            if (Establecimiento::where('company_id', $companyId)->where('codigo', $data['codigo'])->exists()) {
                return response()->json(['message' => 'Código ya registrado para este emisor', 'errors' => ['codigo' => ['Código ya registrado']]], 422);
            }
        }

        if ($request->hasFile('logo')) {
            $path = $request->file('logo')->store('establecimientos/logos', 'public');
            if ($est->logo_path && Storage::disk('public')->exists($est->logo_path)) {
                try { Storage::disk('public')->delete($est->logo_path); } catch (\Exception $_) {}
            }
            $data['logo_path'] = $path;
        }

        if (Auth::check()) {
            if (Schema::hasColumn('establecimientos','updated_by')) $data['updated_by'] = Auth::id();
        }

        $est->update($data);

        return response()->json(['data' => $est]);
    }

    public function destroy($companyId, $id)
    {
        $est = Establecimiento::where('company_id', $companyId)->findOrFail($id);
        // Optionally validate no dependent records
        try {
            if ($est->logo_path && Storage::disk('public')->exists($est->logo_path)) {
                Storage::disk('public')->delete($est->logo_path);
            }
        } catch (\Exception $_) {}
        $est->delete();
        return response()->json(['message' => 'Establecimiento eliminado correctamente.']);
    }
}

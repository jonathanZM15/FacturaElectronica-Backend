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
use Illuminate\Support\Facades\Hash;

class EstablecimientoController extends Controller
{
    // List establecimientos for a company
    public function index($companyId)
    {
        $items = Establecimiento::where('company_id', $companyId)->with(['puntos_emision', 'creator:id,name', 'updater:id,name'])->orderBy('id', 'desc')->get();
        
        // Map items to include logo_url and other accessors
        $data = $items->map(function ($item) {
            return array_merge($item->toArray(), [
                'logo_url' => $item->logo_url,
                'created_by_name' => $item->created_by_name,
                'updated_by_name' => $item->updated_by_name,
            ]);
        });
        
        return response()->json(['data' => $data]);
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

        $payload = array_merge($est->toArray(), [
            'logo_url' => $est->logo_url,
            'created_by_name' => $est->created_by_name,
            'updated_by_name' => $est->updated_by_name,
        ]);

        return response()->json(['data' => $payload], 201);
    }

    public function show($companyId, $id)
    {
        $est = Establecimiento::where('company_id', $companyId)->with(['creator:id,name', 'updater:id,name', 'puntos_emision'])->findOrFail($id);
        
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

        Log::info('=== ESTABLECIMIENTO UPDATE REQUEST ===', [
            'company_id' => $companyId,
            'establecimiento_id' => $id,
            'php_method' => $request->method(),
            '_method' => $request->input('_method'),
            'hasFile' => $request->hasFile('logo'),
            'remove_logo' => $request->boolean('remove_logo'),
            'files_keys' => array_keys($request->allFiles()),
            'content_type' => $request->header('Content-Type'),
        ]);

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

        // Remove logo if requested explicitly
        if ($request->boolean('remove_logo')) {
            if ($est->logo_path && Storage::disk('public')->exists($est->logo_path)) {
                try { Storage::disk('public')->delete($est->logo_path); } catch (\Exception $_) {}
            }
            $data['logo_path'] = null;
            Log::info('Establecimiento logo removed', ['establecimiento_id' => $est->id]);
        } elseif ($request->hasFile('logo')) {
            $path = $request->file('logo')->store('establecimientos/logos', 'public');
            if ($est->logo_path && Storage::disk('public')->exists($est->logo_path)) {
                try { Storage::disk('public')->delete($est->logo_path); } catch (\Exception $_) {}
            }
            $data['logo_path'] = $path;
            Log::info('Establecimiento logo updated', ['establecimiento_id' => $est->id, 'new_path' => $path]);
        }

        if (Auth::check()) {
            if (Schema::hasColumn('establecimientos','updated_by')) $data['updated_by'] = Auth::id();
        }

        $est->update($data);

        // Reload the model to get the recalculated attributes
        $est = $est->fresh();

        $payload = array_merge($est->toArray(), [
            'logo_url' => $est->logo_url,
            'created_by_name' => $est->created_by_name,
            'updated_by_name' => $est->updated_by_name,
        ]);

        return response()->json(['data' => $payload]);
    }

    // Permanent delete of a establecimiento if it has no history (comprobantes)
    public function destroy(Request $request, $companyId, $id)
    {
        $est = Establecimiento::where('company_id', $companyId)->findOrFail($id);

        // Check for related records that would block deletion
        try {
            // comprobantes
            if (Schema::hasTable('comprobantes')) {
                $exists = DB::table('comprobantes')->where('establecimiento_id', $est->id)->exists();
                if ($exists) {
                    return response()->json(['message' => 'El establecimiento tiene historial de comprobantes y no puede ser eliminado.'], 422);
                }
            }

            // puntos_emision
            if (Schema::hasTable('puntos_emision')) {
                $exists = DB::table('puntos_emision')->where('establecimiento_id', $est->id)->exists();
                if ($exists) {
                    return response()->json(['message' => 'El establecimiento tiene puntos de emisión asociados y no puede ser eliminado.'], 422);
                }
            }
        } catch (\Exception $e) {
            Log::warning('Could not fully verify related records before delete for establecimiento '.$est->id.': '.$e->getMessage());
            // If checks can't be completed, refuse to delete to be safe
            return response()->json(['message' => 'No se pudo verificar el historial del establecimiento. Operación cancelada.'], 500);
        }

        // Verify admin password (current authenticated user must re-enter their password)
        $password = $request->input('password');
        if (!Auth::check() || !$password) {
            return response()->json(['message' => 'Se requiere autenticación y contraseña para eliminar el establecimiento.'], 403);
        }

        $user = Auth::user();
        if (!Hash::check($password, $user->password)) {
            return response()->json(['message' => 'Contraseña incorrecta.'], 403);
        }

        // Delete logo file if exists
        try {
            if ($est->logo_path && Storage::disk('public')->exists($est->logo_path)) {
                Storage::disk('public')->delete($est->logo_path);
            }
        } catch (\Exception $_) {
            // continue even if delete fails
        }

        // Perform permanent delete
        try {
            if (method_exists($est, 'forceDelete')) {
                $est->forceDelete();
            } else {
                $est->delete();
            }
        } catch (\Exception $e) {
            Log::error('Failed to delete establecimiento '.$est->id.': '.$e->getMessage());
            return response()->json(['message' => 'Error al eliminar el establecimiento.'], 500);
        }

        // Audit log
        try {
            $userId = Auth::check() ? Auth::id() : null;
            Log::info('Establecimiento permanently deleted', ['establecimiento_id' => $est->id, 'company_id' => $companyId, 'deleted_by' => $userId]);
        } catch (\Exception $_) {}

        return response()->json(['message' => 'Establecimiento eliminado correctamente.']);
    }
}

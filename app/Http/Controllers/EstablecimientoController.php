<?php

namespace App\Http\Controllers;

use App\Models\Establecimiento;
use App\Models\Company;
use App\Enums\UserRole;
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
        // Validar permisos: solo admin, creador de compañía, o usuario emisor/gerente/cajero asignado
        $currentUser = Auth::user();
        $company = Company::findOrFail($companyId);
        
        $isAdmin = $currentUser->role === UserRole::ADMINISTRADOR;
        $isCreator = $company->created_by === $currentUser->id;
        $isAssignedEmissor = ($currentUser->role === UserRole::EMISOR && $currentUser->emisor_id === (int)$companyId);
        $isAssignedGerente = ($currentUser->role === UserRole::GERENTE && $currentUser->emisor_id === (int)$companyId);
        $isAssignedCajero = ($currentUser->role === UserRole::CAJERO && $currentUser->emisor_id === (int)$companyId);
        
        if (!$isAdmin && !$isCreator && !$isAssignedEmissor && !$isAssignedGerente && !$isAssignedCajero) {
            return response()->json([
                'message' => 'No tienes permisos para ver los establecimientos de este emisor'
            ], 403);
        }
        
        // Construir query base
        $query = Establecimiento::where('company_id', $companyId)
            ->with(['puntos_emision', 'creator', 'updater']);
        
        // Para usuarios emisor, gerente o cajero: filtrar por establecimientos asignados
        if ($isAssignedEmissor || $isAssignedGerente || $isAssignedCajero) {
            $establecimientosIds = $currentUser->establecimientos_ids;
            if (is_string($establecimientosIds)) {
                $decoded = json_decode($establecimientosIds, true);
                // Manejar doble codificación JSON
                if (is_string($decoded)) {
                    $decoded = json_decode($decoded, true);
                }
                $establecimientosIds = is_array($decoded) ? $decoded : [];
            }
            if (!is_array($establecimientosIds)) {
                $establecimientosIds = [];
            }
            
            // Si establecimientos_ids está vacío, intentar inferir desde puntos_emision_ids
            if (empty($establecimientosIds)) {
                $puntosEmisionIds = $currentUser->puntos_emision_ids;
                if (is_string($puntosEmisionIds)) {
                    $decoded = json_decode($puntosEmisionIds, true);
                    // Manejar doble codificación JSON
                    if (is_string($decoded)) {
                        $decoded = json_decode($decoded, true);
                    }
                    $puntosEmisionIds = is_array($decoded) ? $decoded : [];
                }
                if (!is_array($puntosEmisionIds)) {
                    $puntosEmisionIds = [];
                }
                
                // Obtener los establecimientos de los puntos de emisión asignados
                if (!empty($puntosEmisionIds)) {
                    $establecimientosIds = \App\Models\PuntoEmision::whereIn('id', $puntosEmisionIds)
                        ->pluck('establecimiento_id')
                        ->unique()
                        ->toArray();
                }
            }
            
            // Si aún no tiene establecimientos, devolver vacío
            if (empty($establecimientosIds)) {
                return response()->json(['data' => []]);
            }
            
            $query->whereIn('id', $establecimientosIds);
        }
        
        $items = $query->orderBy('id', 'desc')->get();
        
        // Map items to include logo_url, other accessors, and associated users
        $data = $items->map(function ($item) {
            // Get users associated with this establecimiento
            $usuarios = \App\Models\User::whereNotNull('establecimientos_ids')
                ->get()
                ->filter(function ($user) use ($item) {
                    $estIds = $user->establecimientos_ids;
                    if (is_string($estIds)) {
                        // Handle double JSON encoding
                        $estIds = json_decode($estIds, true);
                        if (is_string($estIds)) {
                            $estIds = json_decode($estIds, true);
                        }
                    }
                    if (!is_array($estIds)) {
                        return false;
                    }
                    return in_array($item->id, $estIds) || in_array((string)$item->id, $estIds);
                })
                ->map(function ($user) {
                    return [
                        'id' => $user->id,
                        'username' => $user->username,
                        'role' => $user->role,
                        'nombres' => $user->nombres,
                        'apellidos' => $user->apellidos,
                    ];
                })
                ->values()
                ->all();
            
            return array_merge($item->toArray(), [
                'logo_url' => $item->logo_url,
                'created_by_name' => $item->created_by_name,
                'updated_by_name' => $item->updated_by_name,
                'created_by_info' => $item->created_by_info,
                'updated_by_info' => $item->updated_by_info,
                'usuarios' => $usuarios,
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
        // Validar permisos: solo admin, creador de compañía, o usuario emisor asignado (Gerente NO puede crear)
        $currentUser = Auth::user();
        $company = Company::findOrFail($companyId);
        
        $isAdmin = $currentUser->role === UserRole::ADMINISTRADOR;
        $isCreator = $company->created_by === $currentUser->id;
        $isAssignedEmissor = ($currentUser->role === UserRole::EMISOR && $currentUser->emisor_id === (int)$companyId);
        
        if (!$isAdmin && !$isCreator && !$isAssignedEmissor) {
            return response()->json([
                'message' => 'No tienes permisos para crear establecimientos en este emisor'
            ], 403);
        }
        
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

        // Reload with relations
        $est = $est->fresh(['creator', 'updater']);

        $payload = array_merge($est->toArray(), [
            'logo_url' => $est->logo_url,
            'created_by_name' => $est->created_by_name,
            'updated_by_name' => $est->updated_by_name,
            'created_by_info' => $est->created_by_info,
            'updated_by_info' => $est->updated_by_info,
        ]);

        return response()->json(['data' => $payload], 201);
    }

    public function show($companyId, $id)
    {
        $currentUser = Auth::user();
        $company = Company::findOrFail($companyId);

        $isAdmin = $currentUser->role === UserRole::ADMINISTRADOR;
        $isCreator = $company->created_by === $currentUser->id;
        $isAssignedEmissor = ($currentUser->role === UserRole::EMISOR && $currentUser->emisor_id === (int) $companyId);
        $isAssignedGerente = ($currentUser->role === UserRole::GERENTE && $currentUser->emisor_id === (int) $companyId);
        $isAssignedCajero = ($currentUser->role === UserRole::CAJERO && $currentUser->emisor_id === (int) $companyId);

        if (!$isAdmin && !$isCreator && !$isAssignedEmissor && !$isAssignedGerente && !$isAssignedCajero) {
            return response()->json([
                'message' => 'No tienes permisos para ver este establecimiento'
            ], 403);
        }

        $est = Establecimiento::where('company_id', $companyId)
            ->with(['creator', 'updater', 'puntos_emision'])
            ->findOrFail($id);

        if ($currentUser->role === UserRole::GERENTE || $currentUser->role === UserRole::CAJERO) {
            $establecimientosIds = $this->normalizeIds($currentUser->establecimientos_ids);
            if (!empty($establecimientosIds) && !$this->idInArray($est->id, $establecimientosIds)) {
                return response()->json([
                    'message' => 'No tienes permisos para ver este establecimiento'
                ], 403);
            }

            $puntosAsignados = $this->normalizeIds($currentUser->puntos_emision_ids);
            if (!empty($puntosAsignados)) {
                $filteredPuntos = $est->puntos_emision
                    ->filter(function ($punto) use ($puntosAsignados) {
                        return $this->idInArray($punto->id, $puntosAsignados);
                    })
                    ->values();
                $est->setRelation('puntos_emision', $filteredPuntos);
            }
        }
        
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
            'created_by_info' => $est->created_by_info,
            'updated_by_info' => $est->updated_by_info,
            'usuarios' => $this->getUsuariosAsociados($est->id),
        ]);

        return response()->json(['data' => $payload]);
    }

    private function normalizeIds($value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        if (is_numeric($value)) {
            return [(int) $value];
        }

        return [];
    }

    private function idInArray($id, array $ids): bool
    {
        if (in_array($id, $ids, true)) {
            return true;
        }

        if (in_array((string) $id, $ids, true)) {
            return true;
        }

        if (in_array((int) $id, array_map('intval', $ids), true)) {
            return true;
        }

        return false;
    }

    public function update(Request $request, $companyId, $id)
    {
        // Validar permisos: admin, creador, emisor asignado o gerente asignado pueden editar (Cajero NO)
        $currentUser = Auth::user();
        $company = Company::findOrFail($companyId);
        
        $isAdmin = $currentUser->role === UserRole::ADMINISTRADOR;
        $isCreator = $company->created_by === $currentUser->id;
        $isAssignedEmissor = ($currentUser->role === UserRole::EMISOR && $currentUser->emisor_id === (int)$companyId);
        $isAssignedGerente = ($currentUser->role === UserRole::GERENTE && $currentUser->emisor_id === (int)$companyId);
        
        if (!$isAdmin && !$isCreator && !$isAssignedEmissor && !$isAssignedGerente) {
            return response()->json([
                'message' => 'No tienes permisos para editar establecimientos en este emisor'
            ], 403);
        }
        
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

        // Reload the model with relations to get the recalculated attributes
        $est = $est->fresh(['creator', 'updater']);

        $payload = array_merge($est->toArray(), [
            'logo_url' => $est->logo_url,
            'created_by_name' => $est->created_by_name,
            'updated_by_name' => $est->updated_by_name,
            'created_by_info' => $est->created_by_info,
            'updated_by_info' => $est->updated_by_info,
        ]);

        return response()->json(['data' => $payload]);
    }

    // Permanent delete of a establecimiento if it has no history (comprobantes)
    public function destroy(Request $request, $companyId, $id)
    {
        // Validar permisos: admin, creador, emisor asignado o gerente asignado pueden eliminar (Cajero NO)
        $currentUser = Auth::user();
        $company = Company::findOrFail($companyId);
        
        $isAdmin = $currentUser->role === UserRole::ADMINISTRADOR;
        $isCreator = $company->created_by === $currentUser->id;
        $isAssignedEmissor = ($currentUser->role === UserRole::EMISOR && $currentUser->emisor_id === (int)$companyId);
        $isAssignedGerente = ($currentUser->role === UserRole::GERENTE && $currentUser->emisor_id === (int)$companyId);
        
        if (!$isAdmin && !$isCreator && !$isAssignedEmissor && !$isAssignedGerente) {
            return response()->json([
                'message' => 'No tienes permisos para eliminar establecimientos en este emisor'
            ], 403);
        }
        
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

    /**
     * Get users associated with an establecimiento
     */
    private function getUsuariosAsociados($establecimientoId): array
    {
        $usuarios = \App\Models\User::whereNotNull('establecimientos_ids')
            ->get()
            ->filter(function ($user) use ($establecimientoId) {
                $estIds = $user->establecimientos_ids;
                if (is_string($estIds)) {
                    // Handle double JSON encoding
                    $estIds = json_decode($estIds, true);
                    if (is_string($estIds)) {
                        $estIds = json_decode($estIds, true);
                    }
                }
                if (!is_array($estIds)) {
                    return false;
                }
                return in_array($establecimientoId, $estIds) || in_array((string)$establecimientoId, $estIds);
            })
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'username' => $user->username,
                    'role' => $user->role,
                    'nombres' => $user->nombres,
                    'apellidos' => $user->apellidos,
                ];
            })
            ->values()
            ->all();

        return $usuarios;
    }
}

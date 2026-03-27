<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Enums\UserRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

class EmisorController extends Controller
{
    public function index(Request $request)
    {
        // Filters and pagination parameters
        $params = $request->all();
        $page = max(1, (int)($request->input('page', 1)));
        $perPage = max(10, min(200, (int)($request->input('per_page', 20))));
        $sortBy = $request->input('sort_by', 'id');
        $sortDir = strtolower($request->input('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        $currentUser = Auth::user();
        $query = Company::query()
            ->select(['emisores.*'])
            ->with(['creator:id,name,username,nombres,apellidos,role'])
            ->with([
                'suscripcionesVigentes' => function ($q) {
                    $q->select([
                        'id',
                        'emisor_id',
                        'plan_id',
                        'estado_suscripcion',
                        'fecha_inicio',
                        'fecha_fin',
                        'cantidad_comprobantes',
                        'comprobantes_usados',
                    ])
                      ->with(['plan:id,nombre,periodo,cantidad_comprobantes,precio'])
                      ->where('estado_suscripcion', 'Vigente')
                      ->orderByDesc('id')
                      ->limit(1);
                }
            ]);

        // === Join optimizado para suscripción vigente (última vigente por emisor) ===
        $hasSuscripciones = Schema::hasTable('suscripciones');
        $hasPlanes = Schema::hasTable('planes');
        if ($hasSuscripciones) {
            $vigenteSub = DB::table('suscripciones as sv')
                ->select([
                    'sv.id',
                    'sv.emisor_id',
                    'sv.plan_id',
                    'sv.fecha_inicio',
                    'sv.fecha_fin',
                    'sv.cantidad_comprobantes',
                    'sv.comprobantes_usados',
                ])
                ->whereNull('sv.deleted_at')
                ->where('sv.estado_suscripcion', 'Vigente')
                ->whereRaw(
                    "sv.id = (select max(s2.id) from suscripciones s2 where s2.emisor_id = sv.emisor_id and s2.estado_suscripcion = ? and s2.deleted_at is null)",
                    ['Vigente']
                );

            $query->leftJoinSub($vigenteSub, 'sus_vig', function ($join) {
                $join->on('sus_vig.emisor_id', '=', 'emisores.id');
            });

            if ($hasPlanes) {
                $query->leftJoin('planes as plan_vig', 'plan_vig.id', '=', 'sus_vig.plan_id');
            }

            // Campos calculados para tabla/filtros
            $query->addSelect([
                DB::raw('sus_vig.fecha_inicio as fecha_inicio_plan'),
                DB::raw('sus_vig.fecha_fin as fecha_fin_plan'),
                DB::raw('COALESCE(sus_vig.comprobantes_usados, 0) as cantidad_creados'),
                DB::raw('(COALESCE(sus_vig.cantidad_comprobantes, 0) - COALESCE(sus_vig.comprobantes_usados, 0)) as cantidad_restantes'),
            ]);

            if ($hasPlanes) {
                $query->addSelect([
                    DB::raw('plan_vig.nombre as tipo_plan'),
                ]);
            }
        } else {
            // Fallback si no existe tabla de suscripciones
            $query->addSelect([
                DB::raw('NULL as fecha_inicio_plan'),
                DB::raw('NULL as fecha_fin_plan'),
                DB::raw('0 as cantidad_creados'),
                DB::raw('0 as cantidad_restantes'),
                DB::raw('NULL as tipo_plan'),
            ]);
        }

        // === Join optimizado para último login (max last_login_at por emisor) ===
        $hasUsers = Schema::hasTable('users');
        $hasLastLoginJoin = false;
        if ($hasUsers && Schema::hasColumn('users', 'emisor_id') && Schema::hasColumn('users', 'last_login_at')) {
            $lastLoginSub = DB::table('users')
                ->selectRaw('emisor_id, MAX(last_login_at) as ultimo_login')
                ->whereNotNull('emisor_id')
                ->groupBy('emisor_id');

            $query->leftJoinSub($lastLoginSub, 'u_login', function ($join) {
                $join->on('u_login.emisor_id', '=', 'emisores.id');
            });
            $query->addSelect(DB::raw('u_login.ultimo_login as ultimo_login'));
            $hasLastLoginJoin = true;
        } else {
            $query->addSelect(DB::raw('NULL as ultimo_login'));
        }

        // === Join optimizado para último comprobante (max created_at por emisor) ===
        $comprobantesFk = null;
        if (Schema::hasTable('comprobantes')) {
            foreach (['company_id', 'emisor_id'] as $candidate) {
                if (Schema::hasColumn('comprobantes', $candidate)) {
                    $comprobantesFk = $candidate;
                    break;
                }
            }
        }

        $hasLastCompJoin = false;
        if ($comprobantesFk && Schema::hasColumn('comprobantes', 'created_at')) {
            $lastCompSub = DB::table('comprobantes')
                ->selectRaw("{$comprobantesFk} as emisor_key, MAX(created_at) as ultimo_comprobante")
                ->whereNotNull($comprobantesFk)
                ->groupBy($comprobantesFk);

            $query->leftJoinSub($lastCompSub, 'c_last', function ($join) {
                $join->on('c_last.emisor_key', '=', 'emisores.id');
            });
            $query->addSelect(DB::raw('c_last.ultimo_comprobante as ultimo_comprobante'));
            $hasLastCompJoin = true;
        } else {
            $query->addSelect(DB::raw('NULL as ultimo_comprobante'));
        }

        // Aplicar filtro de permisos según rol del usuario actual
        if ($currentUser && $currentUser->role !== UserRole::ADMINISTRADOR) {
            if ($currentUser->role === UserRole::DISTRIBUIDOR) {
                // Distribuidor solo ve emisores que creó
                $query->where('created_by', $currentUser->id);
            } elseif ($currentUser->role === UserRole::EMISOR) {
                // Emisor ve:
                // 1. Emisores que creó (si es que creó alguno)
                // 2. El emisor específico donde está asignado (emisor_id)
                $query->where(function ($q) use ($currentUser) {
                    $q->where('created_by', $currentUser->id) // Emisores que creó
                      ->orWhere('id', $currentUser->emisor_id); // Emisor específico asignado
                });
            }
        }
        // Admin ve todos los emisores

        // Basic text filters (calificados para evitar ambigüedad con JOINs)
        if ($request->filled('ruc')) $query->where('emisores.ruc', 'ILIKE', '%'.$request->input('ruc').'%');
        if ($request->filled('razon_social')) $query->where('emisores.razon_social', 'ILIKE', '%'.$request->input('razon_social').'%');
        if ($request->filled('nombre_comercial')) $query->where('emisores.nombre_comercial', 'ILIKE', '%'.$request->input('nombre_comercial').'%');
        if ($request->filled('direccion_matriz')) $query->where('emisores.direccion_matriz', 'ILIKE', '%'.$request->input('direccion_matriz').'%');
        if ($request->filled('correo_remitente')) $query->where('emisores.correo_remitente', 'ILIKE', '%'.$request->input('correo_remitente').'%');
        if ($request->filled('codigo_artesano')) $query->where('emisores.codigo_artesano', 'ILIKE', '%'.$request->input('codigo_artesano').'%');

        if ($request->filled('estado')) $query->where('emisores.estado', $request->input('estado'));
        if ($request->filled('regimen_tributario')) $query->where('emisores.regimen_tributario', $request->input('regimen_tributario'));
        if ($request->filled('obligado_contabilidad')) $query->where('emisores.obligado_contabilidad', $request->input('obligado_contabilidad'));
        // Campos SI/NO + número de resolución: permitir búsqueda por texto en ambos
                if ($request->filled('contribuyente_especial')) {
            $term = $request->input('contribuyente_especial');
            $query->where(function ($q) use ($term) {
                                $q->where('emisores.contribuyente_especial', 'ILIKE', '%'.$term.'%')
                                    ->orWhere('emisores.numero_resolucion_contribuyente_especial', 'ILIKE', '%'.$term.'%');
            });
        }
        if ($request->filled('agente_retencion')) {
            $term = $request->input('agente_retencion');
            $query->where(function ($q) use ($term) {
                                $q->where('emisores.agente_retencion', 'ILIKE', '%'.$term.'%')
                                    ->orWhere('emisores.numero_resolucion_agente_retencion', 'ILIKE', '%'.$term.'%');
            });
        }
                if ($request->filled('tipo_persona')) $query->where('emisores.tipo_persona', $request->input('tipo_persona'));
                if ($request->filled('ambiente')) $query->where('emisores.ambiente', $request->input('ambiente'));
                if ($request->filled('tipo_emision')) $query->where('emisores.tipo_emision', $request->input('tipo_emision'));

        // === Rango de fechas: creación / actualización (el frontend ya los envía) ===
        if ($request->filled('created_at_from')) $query->whereDate('emisores.created_at', '>=', $request->input('created_at_from'));
        if ($request->filled('created_at_to')) $query->whereDate('emisores.created_at', '<=', $request->input('created_at_to'));
        if ($request->filled('updated_at_from')) $query->whereDate('emisores.updated_at', '>=', $request->input('updated_at_from'));
        if ($request->filled('updated_at_to')) $query->whereDate('emisores.updated_at', '<=', $request->input('updated_at_to'));

        // === Filtros de suscripción vigente (plan/fechas/cantidades) ===
        if ($hasSuscripciones) {
            $planIds = $request->input('plan_ids');
            if (!empty($planIds)) {
                if (!is_array($planIds)) {
                    $planIds = explode(',', (string)$planIds);
                }
                $planIds = array_values(array_filter(array_map(function ($v) {
                    return is_numeric($v) ? (int)$v : null;
                }, $planIds)));
                if (count($planIds) > 0) {
                    $query->whereIn('sus_vig.plan_id', $planIds);
                }
            }

            if ($request->filled('vigente_inicio_from')) $query->whereDate('sus_vig.fecha_inicio', '>=', $request->input('vigente_inicio_from'));
            if ($request->filled('vigente_inicio_to')) $query->whereDate('sus_vig.fecha_inicio', '<=', $request->input('vigente_inicio_to'));
            if ($request->filled('vigente_fin_from')) $query->whereDate('sus_vig.fecha_fin', '>=', $request->input('vigente_fin_from'));
            if ($request->filled('vigente_fin_to')) $query->whereDate('sus_vig.fecha_fin', '<=', $request->input('vigente_fin_to'));

            $opMap = ['gte' => '>=', 'lte' => '<=', 'eq' => '='];
            $creadosOpKey = (string)$request->input('vigente_creados_op', '');
            $restantesOpKey = (string)$request->input('vigente_restantes_op', '');
            $creadosOp = $opMap[$creadosOpKey] ?? null;
            $restantesOp = $opMap[$restantesOpKey] ?? null;

            $creadosVal = $request->input('vigente_creados');
            if ($creadosOp && $creadosVal !== null && $creadosVal !== '' && is_numeric($creadosVal)) {
                $query->where('sus_vig.comprobantes_usados', $creadosOp, (int)$creadosVal);
            }

            $restantesVal = $request->input('vigente_restantes');
            if ($restantesOp && $restantesVal !== null && $restantesVal !== '' && is_numeric($restantesVal)) {
                $query->whereRaw(
                    '(COALESCE(sus_vig.cantidad_comprobantes, 0) - COALESCE(sus_vig.comprobantes_usados, 0)) ' . $restantesOp . ' ?',
                    [(int)$restantesVal]
                );
            }
        }

        // === Filtros por registrador (usuario creador) ===
        if ($request->filled('registrador')) {
            $term = $request->input('registrador');
            $like = '%'.$term.'%';
            $query->whereHas('creator', function ($q) use ($like) {
                $q->where('username', 'ILIKE', $like)
                  ->orWhere('name', 'ILIKE', $like)
                  ->orWhere('nombres', 'ILIKE', $like)
                  ->orWhere('apellidos', 'ILIKE', $like)
                  ->orWhere('email', 'ILIKE', $like);
            });
        }

        // === Filtros por rangos de fechas: último login / último comprobante ===
        if ($hasLastLoginJoin) {
            if ($request->filled('ultimo_login_from')) $query->whereDate('u_login.ultimo_login', '>=', $request->input('ultimo_login_from'));
            if ($request->filled('ultimo_login_to')) $query->whereDate('u_login.ultimo_login', '<=', $request->input('ultimo_login_to'));
        }
        if ($hasLastCompJoin) {
            if ($request->filled('ultimo_comprobante_from')) $query->whereDate('c_last.ultimo_comprobante', '>=', $request->input('ultimo_comprobante_from'));
            if ($request->filled('ultimo_comprobante_to')) $query->whereDate('c_last.ultimo_comprobante', '<=', $request->input('ultimo_comprobante_to'));
        }

        // Simple search 'q' affects some text fields
        if ($request->filled('q')) {
            $q = $request->input('q');
            $like = "%{$q}%";
            $query->where(function ($qq) use ($like) {
                $qq->where('ruc', 'ILIKE', $like)
                   ->orWhere('razon_social', 'ILIKE', $like)
                   ->orWhere('nombre_comercial', 'ILIKE', $like)
                   ->orWhere('direccion_matriz', 'ILIKE', $like);
            });
        }

        // Sorting: allow only known columns
        $allowedSorts = ['id','ruc','razon_social','estado','created_at','updated_at','tipo_plan','cantidad_creados','cantidad_restantes','fecha_inicio_plan','fecha_fin_plan','ultimo_login','ultimo_comprobante'];
        if (!in_array($sortBy, $allowedSorts)) $sortBy = 'id';

        // Cuando hay joins, columnas como "id" pueden ser ambiguas. Calificar columnas base.
        $baseSorts = ['id','ruc','razon_social','estado','created_at','updated_at'];
        $orderColumn = in_array($sortBy, $baseSorts) ? ('emisores.' . $sortBy) : $sortBy;

        $query->orderBy($orderColumn, $sortDir);

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        // Append logo_url for each item (optimizado)
        $items = $paginator->getCollection()->map(function ($c) {
            return array_merge($c->toArray(), [
                'logo_url' => $c->logo_url ?? Storage::url($c->logo_path ?? ''),
                'created_by_name' => $c->created_by_name,
                'updated_by_name' => $c->updated_by_name,
            ]);
        });

        return response()->json([
            'data' => $items,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $rules = [
            'ruc' => ['required','string','max:13','min:10','unique:emisores,ruc'],
            'razon_social' => ['required','string','max:255'],
            'nombre_comercial' => ['nullable','string','max:255'],
            'direccion_matriz' => ['required','string','max:500'],

            'regimen_tributario' => ['required','in:GENERAL,RIMPE_POPULAR,RIMPE_EMPRENDEDOR,MICRO_EMPRESA'],
            'obligado_contabilidad' => ['required','in:SI,NO'],
            'contribuyente_especial' => ['required','in:SI,NO'],
            'numero_resolucion_contribuyente_especial' => ['nullable','string','max:50'],
            'agente_retencion' => ['required','in:SI,NO'],
            'numero_resolucion_agente_retencion' => ['nullable','string','max:50'],
            'tipo_persona' => ['required','in:NATURAL,JURIDICA'],
            // Hacer opcional el código artesano
            'codigo_artesano' => ['nullable','string','max:50'],

            'correo_remitente' => ['required','email','max:255'],
            'estado' => ['required','in:ACTIVO,INACTIVO,DESACTIVADO'],
            'ambiente' => ['required','in:PRODUCCION,PRUEBAS'],
            'tipo_emision' => ['required','in:NORMAL,INDISPONIBILIDAD'],

            'logo' => ['required','image','mimes:jpg,jpeg,png','max:2048'],
        ];

        $validator = Validator::make($request->all(), $rules);
        $validator->after(function ($v) use ($request) {
            $ruc = $request->input('ruc');
            if ($ruc && !$this->validateRucSRI($ruc)) {
                $v->errors()->add('ruc', 'RUC no válido según reglas del SRI');
            }
        });

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation error', 'errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        $company = new Company($data);

        // Asignar el usuario que está creando el registro (si la columna existe)
        if (Auth::check()) {
            if (Schema::hasColumn('emisores', 'created_by')) {
                $company->created_by = Auth::id();
            }
            if (Schema::hasColumn('emisores', 'updated_by')) {
                $company->updated_by = Auth::id();
            }
        }

        if ($request->hasFile('logo')) {
            $path = $request->file('logo')->store('emisores/logos', 'public');
            $company->logo_path = $path;
        }

        $company->save();

        $payload = array_merge($company->toArray(), [
            'logo_url' => $company->logo_url,
            'created_by_name' => $company->created_by_name,
            'updated_by_name' => $company->updated_by_name,
        ]);

        return response()->json(['data' => $payload], 201);
    }

    /**
     * Check if RUC already exists in the database
     */
    public function checkRuc($ruc)
    {
        $exists = Company::where('ruc', $ruc)->exists();
        return response()->json(['exists' => $exists, 'available' => !$exists]);
    }

    // Show a single emisor with editable flags
    public function show($id)
    {
        $company = Company::with(['creator:id,name,nombres,apellidos,username,email,role', 'updater:id,name,nombres,apellidos,username,email,role'])->findOrFail($id);

        // Determine if RUC can be edited: if there's a comprobantes table and
        // there are any records with estado = 'AUTORIZADO' for this company, disallow.
        $rucEditable = true;
        try {
            if (Schema::hasTable('comprobantes')) {
                $exists = DB::table('comprobantes')
                    ->where('company_id', $company->id)
                    ->where('estado', 'AUTORIZADO')
                    ->exists();
                if ($exists) $rucEditable = false;
            }
        } catch (\Exception $e) {
            // If the table doesn't exist or query fails, assume editable (no comprobantes)
            Log::warning('Could not check comprobantes for company '.$company->id.': '.$e->getMessage());
            $rucEditable = true;
        }

        // Build creator object for frontend
        $creatorData = null;
        if ($company->creator) {
            $creatorData = [
                'id' => $company->creator->id,
                'role' => $company->creator->role instanceof \App\Enums\UserRole 
                    ? $company->creator->role->value 
                    : $company->creator->role,
                'username' => $company->creator->username,
                'email' => $company->creator->email,
                'name' => $company->creator->name,
                'nombres' => $company->creator->nombres,
                'apellidos' => $company->creator->apellidos,
            ];
        }

        $payload = array_merge($company->toArray(), [
            'logo_url' => $company->logo_url,
            'ruc_editable' => $rucEditable,
            'created_by_name' => $company->created_by_name,
            'created_by_username' => $company->created_by_username,
            'updated_by_name' => $company->updated_by_name,
            'creator' => $creatorData,
        ]);

        return response()->json(['data' => $payload]);
    }

    // Update existing emisor
    public function update(Request $request, $id)
    {
        Log::info('=== EMISOR UPDATE REQUEST ===', [
            'company_id' => $id,
            'php_method' => $request->method(),
            '_method' => $request->input('_method'),
            'hasFile' => $request->hasFile('logo'),
            'files_keys' => array_keys($request->allFiles()),
            'request_all_keys' => array_keys($request->all()),
            'content_type' => $request->header('Content-Type'),
        ]);
        
        $company = Company::findOrFail($id);
        
        // Validar permisos: solo admin o el creador del emisor pueden editarlo
        $currentUser = Auth::user();
        if ($currentUser->role !== UserRole::ADMINISTRADOR && $company->created_by !== $currentUser->id) {
            return response()->json([
                'message' => 'No tienes permisos para editar este emisor'
            ], 403);
        }

        // Check if there are authorized comprobantes
        $hasAuthorized = false;
        try {
            if (Schema::hasTable('comprobantes')) {
                $hasAuthorized = DB::table('comprobantes')
                    ->where('company_id', $company->id)
                    ->where('estado', 'AUTORIZADO')
                    ->exists();
            }
        } catch (\Exception $e) {
            Log::warning('Could not check comprobantes during update for company '.$company->id.': '.$e->getMessage());
            $hasAuthorized = false;
        }

        // Build validation rules (allow same RUC for this record)
        // En modo edición, todos los campos son opcionales excepto cuando se envían
        $rules = [
            'ruc' => ['sometimes','required','string','max:13','min:10', Rule::unique('emisores','ruc')->ignore($company->id)],
            'razon_social' => ['sometimes','required','string','max:255'],
            'nombre_comercial' => ['sometimes','nullable','string','max:255'],
            'direccion_matriz' => ['sometimes','nullable','string','max:500'],

            'regimen_tributario' => ['sometimes','nullable','in:GENERAL,RIMPE_POPULAR,RIMPE_EMPRENDEDOR,MICRO_EMPRESA'],
            'obligado_contabilidad' => ['sometimes','nullable','in:SI,NO'],
            'contribuyente_especial' => ['sometimes','nullable','in:SI,NO'],
            'numero_resolucion_contribuyente_especial' => ['sometimes','nullable','string','max:50'],
            'agente_retencion' => ['sometimes','nullable','in:SI,NO'],
            'numero_resolucion_agente_retencion' => ['sometimes','nullable','string','max:50'],
            'tipo_persona' => ['sometimes','nullable','in:NATURAL,JURIDICA'],
            'codigo_artesano' => ['sometimes','nullable','string','max:50'],

            'correo_remitente' => ['sometimes','nullable','email','max:255'],
            'estado' => ['sometimes','required','in:ACTIVO,INACTIVO,DESACTIVADO'],
            'ambiente' => ['sometimes','required','in:PRODUCCION,PRUEBAS'],
            'tipo_emision' => ['sometimes','required','in:NORMAL,INDISPONIBILIDAD'],

            'logo' => ['sometimes','nullable','image','mimes:jpg,jpeg,png','max:2048'],
        ];

        // use Validator so we can add an after-hook for RUC SRI validation
        $validator = Validator::make($request->all(), $rules);
        $validator->after(function ($v) use ($request) {
            $ruc = $request->input('ruc');
            if ($ruc && !$this->validateRucSRI($ruc)) {
                $v->errors()->add('ruc', 'RUC no válido según reglas del SRI');
            }
        });

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation error', 'errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        // If there are authorized comprobantes, do not allow RUC modification
        if ($hasAuthorized && isset($data['ruc']) && $data['ruc'] !== $company->ruc) {
            return response()->json([
                'message' => 'El RUC no puede ser modificado porque existen comprobantes autorizados.'
            ], 422);
        }

        // Update allowed fields
        $company->fill($data);

        // Actualizar el usuario que está modificando el registro (si la columna existe)
        if (Auth::check()) {
            if (Schema::hasColumn('emisores', 'updated_by')) {
                $company->updated_by = Auth::id();
            }
        }

        if ($request->hasFile('logo')) {
            Log::info('Logo file received for update', [
                'company_id' => $company->id,
                'filename' => $request->file('logo')->getClientOriginalName(),
                'size' => $request->file('logo')->getSize(),
                'mime' => $request->file('logo')->getMimeType(),
            ]);
            $path = $request->file('logo')->store('emisores/logos', 'public');
            // delete old
            if ($company->logo_path && Storage::disk('public')->exists($company->logo_path)) {
                try { Storage::disk('public')->delete($company->logo_path); } catch (\Exception $_) {}
            }
            $company->logo_path = $path;
            Log::info('Logo updated for company', ['company_id' => $company->id, 'new_path' => $path]);
        }

        $company->save();

        // Audit log
        try {
            $userId = Auth::check() ? Auth::id() : null;
            Log::info('Emisor updated', ['company_id' => $company->id, 'updated_by' => $userId]);
        } catch (\Exception $_) {}

        $payload = array_merge($company->toArray(), [
            'logo_url' => $company->logo_url,
            'created_by_name' => $company->created_by_name,
            'updated_by_name' => $company->updated_by_name,
        ]);

        return response()->json(['data' => $payload]);
    }

    /**
     * Validate RUC according to Ecuador SRI rules (basic implementation).
     * Supports natural persons (third digit 0-5), public (6) and private (9) companies.
     */
    private function validateRucSRI(string $ruc): bool
    {
        $ruc = preg_replace('/\D/', '', $ruc);
        $len = strlen($ruc);
        if ($len < 10) return false;

        $third = intval($ruc[2]);

        if ($third >= 0 && $third < 6) {
            // natural person: validate cédula (first 10 digits)
            $cedula = substr($ruc, 0, 10);
            if (!$this->validateCedula($cedula)) return false;
            if ($len == 10) return true;
            if ($len == 13) {
                $suffix = intval(substr($ruc, 10, 3));
                return $suffix > 0; // common suffix like 001
            }
            return false;
        }

        if ($third == 6) {
            // public companies
            return $this->validatePublicCompany($ruc);
        }

        if ($third == 9) {
            // private companies
            return $this->validatePrivateCompany($ruc);
        }

        return false;
    }

    private function validateCedula(string $cedula): bool
    {
        if (strlen($cedula) !== 10) return false;
        if (!ctype_digit($cedula)) return false;

        $province = intval(substr($cedula, 0, 2));
        if ($province < 1 || $province > 24) return false;
        // Ecuador cédula algorithm: multiply digits by coefficients, if product>=10 subtract 9,
        // sum and compute check digit via modulo 10.
        $coeff = [2,1,2,1,2,1,2,1,2];
        $sum = 0;
        for ($i = 0; $i < 9; $i++) {
            $v = intval($cedula[$i]) * $coeff[$i];
            if ($v >= 10) $v -= 9;
            $sum += $v;
        }
        $mod = $sum % 10;
        $check = $mod === 0 ? 0 : 10 - $mod;
        return $check === intval($cedula[9]);
    }

    private function validatePublicCompany(string $ruc): bool
    {
        $ruc = preg_replace('/\D/', '', $ruc);
        if (strlen($ruc) < 9) return false;
        if (!ctype_digit($ruc)) return false;

        $coeff = [3,2,7,6,5,4,3,2];
        $sum = 0;
        for ($i = 0; $i < 8; $i++) {
            $sum += intval($ruc[$i]) * $coeff[$i];
        }
        $mod = $sum % 11;
        $check = $mod == 0 ? 0 : 11 - $mod;
        if ($check == 10) return false;
        if ($check != intval($ruc[8])) return false;

        if (strlen($ruc) == 13) {
            $suffix = intval(substr($ruc, 9, 4));
            return $suffix > 0;
        }
        return true;
    }

    /**
     * Validate RUC for private companies (third digit = 9) using Ecuador SRI rules.
     * Validates structural format without strict check digit verification,
     * as the SRI may accept RUCs that are structurally valid even if check digit calculation differs.
     */
    private function validatePrivateCompany(string $ruc): bool
    {
        $ruc = preg_replace('/\D/', '', $ruc);
        // Private company RUC must be 13 digits
        if (strlen($ruc) !== 13) return false;
        if (!ctype_digit($ruc)) return false;

        // Third digit must be 9
        if ($ruc[2] !== '9') return false;

        // Last three digits must be 001
        if (substr($ruc, 10, 3) !== '001') return false;

        // Structural validation passed - accept the RUC
        // The SRI may accept RUCs that pass structural validation even if check digit calculation differs
        return true;
    }

    /**
     * Validate if a company can be deleted (no suscripciones, no usuarios asociados).
     * This endpoint is used by the frontend to check before opening the delete modal.
     */
    public function validateDelete($id)
    {
        $company = Company::findOrFail($id);

        $canDelete = true;
        $blockers = [];

        try {
            // Check for suscripciones (subscriptions)
            // Prefer new table name/column: suscripciones.emisor_id (soft deletes)
            if (Schema::hasTable('suscripciones')) {
                $q = DB::table('suscripciones')->where('emisor_id', $company->id);
                if (Schema::hasColumn('suscripciones', 'deleted_at')) $q->whereNull('deleted_at');
                if ($q->exists()) {
                    $canDelete = false;
                    $blockers[] = 'El emisor tiene suscripciones registradas';
                }
            }
            // Backward-compat: legacy table name
            if ($canDelete && Schema::hasTable('subscriptions')) {
                $subCol = Schema::hasColumn('subscriptions', 'emisor_id') ? 'emisor_id'
                    : (Schema::hasColumn('subscriptions', 'company_id') ? 'company_id' : null);
                if ($subCol) {
                    $q = DB::table('subscriptions')->where($subCol, $company->id);
                    if (Schema::hasColumn('subscriptions', 'deleted_at')) $q->whereNull('deleted_at');
                    if ($q->exists()) {
                        $canDelete = false;
                        $blockers[] = 'El emisor tiene suscripciones registradas';
                    }
                }
            }

            // Check for usuarios asociados
            if (Schema::hasTable('users')) {
                $userCol = Schema::hasColumn('users', 'emisor_id') ? 'emisor_id'
                    : (Schema::hasColumn('users', 'company_id') ? 'company_id' : null);
                if ($userCol && DB::table('users')->where($userCol, $company->id)->exists()) {
                    $canDelete = false;
                    $blockers[] = 'El emisor tiene usuarios asociados';
                }
            }

            // Check for comprobantes
            if (Schema::hasTable('comprobantes')) {
                $hasComprobantes = DB::table('comprobantes')->where('company_id', $company->id)->exists();
                if ($hasComprobantes) {
                    $canDelete = false;
                    $blockers[] = 'El emisor tiene historial de comprobantes';
                }
            }

            // Check for planes
            if (Schema::hasTable('plans')) {
                $hasPlans = DB::table('plans')->where('company_id', $company->id)->exists();
                if ($hasPlans) {
                    $canDelete = false;
                    $blockers[] = 'El emisor tiene planes asociados';
                }
            }
        } catch (\Exception $e) {
            Log::warning('Could not fully verify if company '.$company->id.' can be deleted: '.$e->getMessage());
            // If checks fail, return error to be safe
            return response()->json([
                'can_delete' => false,
                'message' => 'No se pudo verificar si el emisor puede ser eliminado',
                'blockers' => []
            ], 500);
        }

        return response()->json([
            'can_delete' => $canDelete,
            'message' => $canDelete ? 'El emisor puede ser eliminado' : 'No se puede eliminar este emisor',
            'blockers' => $blockers
        ]);
    }

    // Permanent delete of a company (emisor) if it has no history (comprobantes), plans or users.
    public function destroy(Request $request, $id)
    {
        $company = Company::findOrFail($id);
        
        // Validar permisos: solo admin o el creador del emisor pueden eliminarlo
        $currentUser = Auth::user();
        if ($currentUser->role !== UserRole::ADMINISTRADOR && $company->created_by !== $currentUser->id) {
            return response()->json([
                'message' => 'No tienes permisos para eliminar este emisor'
            ], 403);
        }

        // Check for related records that would block deletion
        try {
            $blockers = [];

            // suscripciones
            if (Schema::hasTable('suscripciones')) {
                $q = DB::table('suscripciones')->where('emisor_id', $company->id);
                if (Schema::hasColumn('suscripciones', 'deleted_at')) $q->whereNull('deleted_at');
                if ($q->exists()) {
                    $blockers[] = 'El emisor tiene suscripciones registradas';
                }
            }

            // comprobantes
            if (Schema::hasTable('comprobantes')) {
                $exists = DB::table('comprobantes')->where('company_id', $company->id)->exists();
                if ($exists) {
                    $blockers[] = 'El emisor tiene historial de comprobantes';
                }
            }

            // plans/subscriptions
            if (Schema::hasTable('plans')) {
                $exists = DB::table('plans')->where('company_id', $company->id)->exists();
                if ($exists) {
                    $blockers[] = 'El emisor tiene planes asociados';
                }
            }
            if (Schema::hasTable('subscriptions')) {
                $subCol = Schema::hasColumn('subscriptions', 'emisor_id') ? 'emisor_id'
                    : (Schema::hasColumn('subscriptions', 'company_id') ? 'company_id' : null);
                if ($subCol) {
                    $q = DB::table('subscriptions')->where($subCol, $company->id);
                    if (Schema::hasColumn('subscriptions', 'deleted_at')) $q->whereNull('deleted_at');
                    if ($q->exists()) {
                        $blockers[] = 'El emisor tiene suscripciones registradas';
                    }
                }
            }

            // users linked via emisor_id (not company_id)
            if (Schema::hasTable('users')) {
                $userCol = Schema::hasColumn('users', 'emisor_id') ? 'emisor_id'
                    : (Schema::hasColumn('users', 'company_id') ? 'company_id' : null);
                if ($userCol && DB::table('users')->where($userCol, $company->id)->exists()) {
                    $blockers[] = 'El emisor tiene usuarios asociados';
                }
            }

            if (!empty($blockers)) {
                return response()->json([
                    'message' => 'No se puede eliminar este emisor',
                    'blockers' => $blockers,
                ], 422);
            }
        } catch (\Exception $e) {
            Log::warning('Could not fully verify related records before delete for company '.$company->id.': '.$e->getMessage());
            // If checks can't be completed, refuse to delete to be safe
            return response()->json(['message' => 'No se pudo verificar el historial del emisor. Operación cancelada.'], 500);
        }

        // Verify admin password (current authenticated user must re-enter their password)
        $password = $request->input('password');
        if (!Auth::check() || !$password) {
            return response()->json(['message' => 'Se requiere autenticación y contraseña para eliminar el emisor.'], 403);
        }

        $user = Auth::user();
        if (!Hash::check($password, $user->password)) {
            return response()->json(['message' => 'Contraseña incorrecta.'], 403);
        }

        // Delete logo file if exists
        try {
            if ($company->logo_path && Storage::disk('public')->exists($company->logo_path)) {
                Storage::disk('public')->delete($company->logo_path);
            }
        } catch (\Exception $_) {
            // continue even if delete fails
        }

        // Perform permanent delete
        try {
            if (method_exists($company, 'forceDelete')) {
                $company->forceDelete();
            } else {
                $company->delete();
            }
        } catch (\Exception $e) {
            Log::error('Failed to delete company '.$company->id.': '.$e->getMessage());
            return response()->json(['message' => 'Error al eliminar el emisor.'], 500);
        }

        // Audit log
        try {
            $userId = Auth::check() ? Auth::id() : null;
            Log::info('Emisor permanently deleted', ['company_id' => $company->id, 'deleted_by' => $userId]);
        } catch (\Exception $_) {}

        return response()->json(['message' => 'Emisor eliminado correctamente.']);
    }

    /**
     * Prepare deletion for a company that has been DESACTIVADO for at least 1 year.
     * Generates CSV exports, zips them, stores backup and emails the client a copy/notification.
     */
    public function prepareDeletion(Request $request, $id)
    {
        $company = Company::findOrFail($id);

        // Only allow if company has estado DESACTIVADO and last updated at least 1 year ago
        try {
            $cutoff = Carbon::now()->subYear();
            $updatedAt = $company->updated_at ?? $company->created_at;
            if (!($company->estado === 'INACTIVO' && Carbon::parse($updatedAt)->lessThanOrEqualTo($cutoff))) {
                return response()->json(['message' => 'Solo se pueden preparar para eliminación emisores desactivados por al menos 1 año.'], 422);
            }
        } catch (\Exception $e) {
            return response()->json(['message' => 'No se pudo verificar la antigüedad del emisor.'], 500);
        }

        // Create a temp dir and CSVs
        $tmpDir = sys_get_temp_dir().'/emisor_backup_'.$company->id.'_'.time();
        if (!is_dir($tmpDir)) @mkdir($tmpDir, 0777, true);

        $filesCreated = [];
        // helper to dump a set of rows to CSV
        $dumpTable = function ($filename, $rows) use (&$filesCreated, $tmpDir) {
            $path = $tmpDir.'/'.$filename;
            $fh = fopen($path, 'w');
            if ($fh === false) return null;
            if (!empty($rows)) {
                $first = (array)$rows[0];
                fputcsv($fh, array_keys($first));
                foreach ($rows as $r) {
                    fputcsv($fh, array_values((array)$r));
                }
            } else {
                // write header placeholder
                fputcsv($fh, ['empty']);
            }
            fclose($fh);
            $filesCreated[] = $path;
            return $path;
        };

        // company single row
        $dumpTable('company.csv', [ (array) $company->toArray() ]);

        // comprobantes (facturas)
        try {
            if (Schema::hasTable('comprobantes')) {
                $rows = DB::table('comprobantes')->where('company_id', $company->id)->get()->toArray();
                $dumpTable('comprobantes.csv', $rows);
            }
        } catch (\Exception $_) {}

        // productos
        try {
            if (Schema::hasTable('productos')) {
                $rows = DB::table('productos')->where('company_id', $company->id)->get()->toArray();
                $dumpTable('productos.csv', $rows);
            }
        } catch (\Exception $_) {}

        // plans / subscriptions
        try {
            if (Schema::hasTable('plans')) {
                $rows = DB::table('plans')->where('company_id', $company->id)->get()->toArray();
                $dumpTable('plans.csv', $rows);
            }
            if (Schema::hasTable('subscriptions')) {
                $rows = DB::table('subscriptions')->where('company_id', $company->id)->get()->toArray();
                $dumpTable('subscriptions.csv', $rows);
            }
        } catch (\Exception $_) {}

        // users
        try {
            if (Schema::hasTable('users') && Schema::hasColumn('users', 'company_id')) {
                $rows = DB::table('users')->where('company_id', $company->id)->get()->toArray();
                $dumpTable('users.csv', $rows);
            }
        } catch (\Exception $_) {}

        // create zip
        $zipName = 'emisor_'.$company->id.'_backup_'.time().'.zip';
        $zipPath = $tmpDir.'/'.$zipName;
        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE) === true) {
            foreach ($filesCreated as $f) {
                $zip->addFile($f, basename($f));
            }
            $zip->close();
        } else {
            return response()->json(['message' => 'No se pudo crear el archivo de respaldo.'], 500);
        }

        // store zip in public backups so admin/client can download
        try {
            $storePath = 'backups/'.$zipName;
            Storage::disk('public')->put($storePath, file_get_contents($zipPath));
            $publicUrl = url(Storage::url($storePath));
        } catch (\Exception $e) {
            Log::error('Could not store backup zip for company '.$company->id.': '.$e->getMessage());
            return response()->json(['message' => 'No se pudo almacenar el respaldo.'], 500);
        }

        // send email notification to company contact (if exists) or to authenticated user
        try {
            $to = $company->correo_remitente ?? (Auth::check() ? Auth::user()->email : null);
            $subject = 'Respaldo y notificación de eliminación de cuenta';
            $body = "Se ha generado un respaldo de su emisor (RUC: {$company->ruc}). Puede descargarlo desde: {$publicUrl}.\n\nSi desea evitar la eliminación, reactive su cuenta antes de la fecha indicada.";
            if ($to) {
                Mail::send([], [], function ($message) use ($to, $subject, $body, $zipPath, $company) {
                    $message->to($to)
                        ->subject($subject)
                        ->setBody($body, 'text/plain')
                        ->attach($zipPath, ['as' => 'respaldo_emisor_'.$company->ruc.'.zip']);
                });
            }
        } catch (\Exception $e) {
            Log::warning('Could not send deletion notification for company '.$company->id.': '.$e->getMessage());
        }

        // cleanup temp files
        foreach ($filesCreated as $f) { @unlink($f); }
        @unlink($zipPath);
        @rmdir($tmpDir);

        return response()->json(['message' => 'Respaldo generado y notificación enviada (si aplica).', 'backup_url' => $publicUrl ?? null]);
    }

    /**
     * Permanently delete a company that has history, only after admin confirmation.
     */
    public function destroyWithHistory(Request $request, $id)
    {
        $company = Company::findOrFail($id);

        // Rule: no suscripciones registradas and no usuarios asociados
        try {
            $blockers = [];
            if (Schema::hasTable('suscripciones')) {
                $q = DB::table('suscripciones')->where('emisor_id', $company->id);
                if (Schema::hasColumn('suscripciones', 'deleted_at')) $q->whereNull('deleted_at');
                if ($q->exists()) {
                    $blockers[] = 'El emisor tiene suscripciones registradas';
                }
            }
            if (Schema::hasTable('subscriptions')) {
                $subCol = Schema::hasColumn('subscriptions', 'emisor_id') ? 'emisor_id'
                    : (Schema::hasColumn('subscriptions', 'company_id') ? 'company_id' : null);
                if ($subCol) {
                    $q = DB::table('subscriptions')->where($subCol, $company->id);
                    if (Schema::hasColumn('subscriptions', 'deleted_at')) $q->whereNull('deleted_at');
                    if ($q->exists()) {
                        $blockers[] = 'El emisor tiene suscripciones registradas';
                    }
                }
            }
            if (Schema::hasTable('users')) {
                $userCol = Schema::hasColumn('users', 'emisor_id') ? 'emisor_id'
                    : (Schema::hasColumn('users', 'company_id') ? 'company_id' : null);
                if ($userCol && DB::table('users')->where($userCol, $company->id)->exists()) {
                    $blockers[] = 'El emisor tiene usuarios asociados';
                }
            }

            if (!empty($blockers)) {
                return response()->json([
                    'message' => 'No se puede eliminar este emisor',
                    'blockers' => $blockers,
                ], 422);
            }
        } catch (\Exception $e) {
            Log::warning('Could not fully verify subscription/user blockers before delete-with-history for company '.$company->id.': '.$e->getMessage());
            return response()->json(['message' => 'No se pudo verificar si el emisor puede ser eliminado.'], 500);
        }

        // must be inactive for at least 1 year
        try {
            $cutoff = Carbon::now()->subYear();
            $updatedAt = $company->updated_at ?? $company->created_at;
            if (!($company->estado === 'INACTIVO' && Carbon::parse($updatedAt)->lessThanOrEqualTo($cutoff))) {
                return response()->json(['message' => 'Solo se pueden eliminar emisores desactivados por al menos 1 año.'], 422);
            }
        } catch (\Exception $e) {
            return response()->json(['message' => 'No se pudo verificar la antigüedad del emisor.'], 500);
        }

        // verify admin password
        $password = $request->input('password');
        if (!Auth::check() || !$password) {
            return response()->json(['message' => 'Se requiere autenticación y contraseña para eliminar el emisor.'], 403);
        }
        $user = Auth::user();
        if (!Hash::check($password, $user->password)) {
            return response()->json(['message' => 'Contraseña incorrecta.'], 403);
        }

        DB::beginTransaction();
        try {
            // delete related records permissively
            if (Schema::hasTable('comprobantes')) {
                DB::table('comprobantes')->where('company_id', $company->id)->delete();
            }
            if (Schema::hasTable('productos')) {
                DB::table('productos')->where('company_id', $company->id)->delete();
            }
            if (Schema::hasTable('plans')) {
                DB::table('plans')->where('company_id', $company->id)->delete();
            }
            if (Schema::hasTable('subscriptions')) {
                DB::table('subscriptions')->where('company_id', $company->id)->delete();
            }
            if (Schema::hasTable('users')) {
                $userCol = Schema::hasColumn('users', 'emisor_id') ? 'emisor_id'
                    : (Schema::hasColumn('users', 'company_id') ? 'company_id' : null);
                if ($userCol) DB::table('users')->where($userCol, $company->id)->delete();
            }

            // delete logo
            try {
                if ($company->logo_path && Storage::disk('public')->exists($company->logo_path)) {
                    Storage::disk('public')->delete($company->logo_path);
                }
            } catch (\Exception $_) {}

            // delete company
            if (method_exists($company, 'forceDelete')) {
                $company->forceDelete();
            } else {
                $company->delete();
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to permanently delete company '.$company->id.': '.$e->getMessage());
            return response()->json(['message' => 'Error al eliminar el emisor.'], 500);
        }

        try { Log::info('Emisor permanently deleted with history', ['company_id' => $company->id, 'deleted_by' => Auth::id()]); } catch (\Exception $_) {}

        return response()->json(['message' => 'Emisor eliminado permanentemente.']);
    }
}
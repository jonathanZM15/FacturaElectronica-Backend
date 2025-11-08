<?php

namespace App\Http\Controllers;

use App\Models\Company;
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

        $query = Company::query()->select('companies.*')
            ->with(['creator:id,name', 'updater:id,name']);

        // Basic text filters
        if ($request->filled('ruc')) $query->where('ruc', 'like', '%'.$request->input('ruc').'%');
        if ($request->filled('razon_social')) $query->where('razon_social', 'like', '%'.$request->input('razon_social').'%');
        if ($request->filled('nombre_comercial')) $query->where('nombre_comercial', 'like', '%'.$request->input('nombre_comercial').'%');
        if ($request->filled('direccion_matriz')) $query->where('direccion_matriz', 'like', '%'.$request->input('direccion_matriz').'%');
        if ($request->filled('correo_remitente')) $query->where('correo_remitente', 'like', '%'.$request->input('correo_remitente').'%');

        if ($request->filled('estado')) $query->where('estado', $request->input('estado'));
        if ($request->filled('regimen_tributario')) $query->where('regimen_tributario', $request->input('regimen_tributario'));
        if ($request->filled('tipo_persona')) $query->where('tipo_persona', $request->input('tipo_persona'));
        if ($request->filled('ambiente')) $query->where('ambiente', $request->input('ambiente'));
        if ($request->filled('tipo_emision')) $query->where('tipo_emision', $request->input('tipo_emision'));

        // Computed fields via subqueries (cantidad_creados, ultimo_comprobante, tipo_plan, plan dates, cantidad_restantes)
        if (Schema::hasTable('comprobantes')) {
            $query->selectSub(function ($q) {
                $q->from('comprobantes')->selectRaw('count(*)')->whereColumn('comprobantes.company_id', 'companies.id');
            }, 'cantidad_creados');

            $query->selectSub(function ($q) {
                $q->from('comprobantes')->selectRaw('max(created_at)')->whereColumn('comprobantes.company_id', 'companies.id');
            }, 'ultimo_comprobante');
        } else {
            $query->selectRaw('NULL as cantidad_creados, NULL as ultimo_comprobante');
        }

        if (Schema::hasTable('plans')) {
            // Latest plan info
            $query->selectSub(function ($q) {
                $q->from('plans')->select('tipo_plan')->whereColumn('plans.company_id', 'companies.id')->orderByDesc('id')->limit(1);
            }, 'tipo_plan');
            $query->selectSub(function ($q) {
                $q->from('plans')->select('fecha_inicio')->whereColumn('plans.company_id', 'companies.id')->orderByDesc('id')->limit(1);
            }, 'fecha_inicio_plan');
            $query->selectSub(function ($q) {
                $q->from('plans')->select('fecha_fin')->whereColumn('plans.company_id', 'companies.id')->orderByDesc('id')->limit(1);
            }, 'fecha_fin_plan');

            // if plans have 'cantidad' column, compute remaining = cantidad - cantidad_creados
            try {
                if (Schema::hasColumn('plans', 'cantidad')) {
                    $query->selectSub(function ($q) {
                        $q->from('plans as p')->selectRaw("COALESCE(p.cantidad,0) - (
                            select count(*) from comprobantes c where c.company_id = p.company_id
                        )")->whereColumn('p.company_id', 'companies.id')->orderByDesc('p.id')->limit(1);
                    }, 'cantidad_restantes');
                } else {
                    $query->selectRaw('NULL as cantidad_restantes');
                }
            } catch (\Exception $_) {
                $query->selectRaw('NULL as cantidad_restantes');
            }
        } else {
            $query->selectRaw('NULL as tipo_plan, NULL as fecha_inicio_plan, NULL as fecha_fin_plan, NULL as cantidad_restantes');
        }

        // Filter by numeric comparisons
        if ($request->filled('cantidad_creados_gt') && is_numeric($request->input('cantidad_creados_gt'))) {
            $query->havingRaw('cantidad_creados > ?', [(int)$request->input('cantidad_creados_gt')]);
        }
        if ($request->filled('cantidad_restantes_lt') && is_numeric($request->input('cantidad_restantes_lt'))) {
            $query->havingRaw('(cantidad_restantes IS NOT NULL AND cantidad_restantes < ?) ', [(int)$request->input('cantidad_restantes_lt')]);
        }

        // Date filters (multiple fields) - accept *_from and *_to params
        $dateFields = [
            'fecha_inicio_plan' => 'fecha_inicio_plan',
            'fecha_fin_plan' => 'fecha_fin_plan',
            'created_at' => 'created_at',
            'updated_at' => 'updated_at',
            'ultimo_login' => 'ultimo_login',
            'ultimo_comprobante' => 'ultimo_comprobante',
        ];
        foreach ($dateFields as $param => $column) {
            $from = $request->input($param.'_from');
            $to = $request->input($param.'_to');
            if ($from) $query->whereDate($column, '>=', $from);
            if ($to) $query->whereDate($column, '<=', $to);
        }

        // Registrador: try to match users.name if such relation exists
        if ($request->filled('registrador') && Schema::hasTable('users')) {
            $registrador = $request->input('registrador');
            // companies may have a registrador column or we try to match via users table
            if (Schema::hasColumn('companies', 'registrador')) {
                $query->where('registrador', 'like', '%'.$registrador.'%');
            } else {
                // filter companies that have a user with that name
                $query->whereExists(function ($q) use ($registrador) {
                    $q->selectRaw('1')->from('users')->whereColumn('users.company_id','companies.id')->where('users.name','like','%'.$registrador.'%');
                });
            }
        }

        // Simple search 'q' affects some text fields
        if ($request->filled('q')) {
            $q = $request->input('q');
            $query->where(function ($qq) use ($q) {
                $qq->where('ruc', 'like', "%{$q}%")
                   ->orWhere('razon_social', 'like', "%{$q}%")
                   ->orWhere('nombre_comercial', 'like', "%{$q}%");
            });
        }

        // Sorting: allow only known columns
        $allowedSorts = ['id','ruc','razon_social','estado','tipo_plan','fecha_inicio_plan','fecha_fin_plan','cantidad_creados','cantidad_restantes','created_at','updated_at','registrador','ultimo_login','ultimo_comprobante'];
        if (!in_array($sortBy, $allowedSorts)) $sortBy = 'id';
        // apply sorting (if sorting by computed alias, use orderByRaw)
        if (in_array($sortBy, ['tipo_plan','fecha_inicio_plan','fecha_fin_plan','cantidad_creados','cantidad_restantes','ultimo_comprobante'])) {
            $query->orderByRaw("\"{$sortBy}\" {$sortDir}");
        } else {
            $query->orderBy($sortBy, $sortDir);
        }

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        // Append logo_url for each item
        $items = $paginator->getCollection()->map(function ($c) {
            if ($c instanceof Company) {
                return array_merge($c->toArray(), [
                    'logo_url' => $c->logo_url,
                    'created_by_name' => $c->created_by_name,
                    'updated_by_name' => $c->updated_by_name,
                ]);
            }
            $arr = (array) $c;
            // if model-like, ensure logo_url computed
            $logo = $arr['logo_path'] ?? null;
            if ($logo && !isset($arr['logo_url'])) $arr['logo_url'] = Storage::url($logo);
            return $arr;
        });

        $result = [
            'data' => $items,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ];

        return response()->json($result);
    }

    public function store(Request $request)
    {
        $rules = [
            'ruc' => ['required','string','max:13','min:10','unique:companies,ruc'],
            'razon_social' => ['required','string','max:255'],
            'nombre_comercial' => ['nullable','string','max:255'],
            'direccion_matriz' => ['nullable','string','max:500'],

            'regimen_tributario' => ['required','in:GENERAL,RIMPE_POPULAR,RIMPE_EMPRENDEDOR,MICRO_EMPRESA'],
            'obligado_contabilidad' => ['required','in:SI,NO'],
            'contribuyente_especial' => ['required','in:SI,NO'],
            'agente_retencion' => ['required','in:SI,NO'],
            'tipo_persona' => ['required','in:NATURAL,JURIDICA'],
            'codigo_artesano' => ['required','string','max:50'],

            'correo_remitente' => ['required','email','max:255'],
            'estado' => ['required','in:ACTIVO,INACTIVO'],
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

        // Asignar el usuario que está creando el registro
        if (Auth::check()) {
            $company->created_by = Auth::id();
            $company->updated_by = Auth::id();
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
        $company = Company::with(['creator:id,name', 'updater:id,name'])->findOrFail($id);

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

        $payload = array_merge($company->toArray(), [
            'logo_url' => $company->logo_url,
            'ruc_editable' => $rucEditable,
            'created_by_name' => $company->created_by_name,
            'updated_by_name' => $company->updated_by_name,
        ]);

        return response()->json(['data' => $payload]);
    }

    // Update existing emisor
    public function update(Request $request, $id)
    {
        $company = Company::findOrFail($id);

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
            'ruc' => ['sometimes','required','string','max:13','min:10', Rule::unique('companies','ruc')->ignore($company->id)],
            'razon_social' => ['sometimes','required','string','max:255'],
            'nombre_comercial' => ['sometimes','nullable','string','max:255'],
            'direccion_matriz' => ['sometimes','nullable','string','max:500'],

            'regimen_tributario' => ['sometimes','nullable','in:GENERAL,RIMPE_POPULAR,RIMPE_EMPRENDEDOR,MICRO_EMPRESA'],
            'obligado_contabilidad' => ['sometimes','nullable','in:SI,NO'],
            'contribuyente_especial' => ['sometimes','nullable','in:SI,NO'],
            'agente_retencion' => ['sometimes','nullable','in:SI,NO'],
            'tipo_persona' => ['sometimes','nullable','in:NATURAL,JURIDICA'],
            'codigo_artesano' => ['sometimes','nullable','string','max:50'],

            'correo_remitente' => ['sometimes','nullable','email','max:255'],
            'estado' => ['sometimes','required','in:ACTIVO,INACTIVO'],
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

        // Actualizar el usuario que está modificando el registro
        if (Auth::check()) {
            $company->updated_by = Auth::id();
        }

        if ($request->hasFile('logo')) {
            $path = $request->file('logo')->store('emisores/logos', 'public');
            // delete old
            if ($company->logo_path && Storage::disk('public')->exists($company->logo_path)) {
                try { Storage::disk('public')->delete($company->logo_path); } catch (\Exception $_) {}
            }
            $company->logo_path = $path;
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
        if ($check !== intval($cedula[9])) return false;

        // if length is 13, last three digits should be > 0
        if (strlen($cedula) == 13) {
            $suffix = intval(substr($cedula, 10, 3));
            return $suffix > 0;
        }
        return true;
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

    // Permanent delete of a company (emisor) if it has no history (comprobantes), plans or users.
    public function destroy(Request $request, $id)
    {
        $company = Company::findOrFail($id);

        // Check for related records that would block deletion
        try {
            // comprobantes
            if (Schema::hasTable('comprobantes')) {
                $exists = DB::table('comprobantes')->where('company_id', $company->id)->exists();
                if ($exists) {
                    return response()->json(['message' => 'El emisor tiene historial de comprobantes y no puede ser eliminado.'], 422);
                }
            }

            // plans/subscriptions
            if (Schema::hasTable('plans')) {
                $exists = DB::table('plans')->where('company_id', $company->id)->exists();
                if ($exists) {
                    return response()->json(['message' => 'El emisor tiene planes asociados y no puede ser eliminado.'], 422);
                }
            }
            if (Schema::hasTable('subscriptions')) {
                $exists = DB::table('subscriptions')->where('company_id', $company->id)->exists();
                if ($exists) {
                    return response()->json(['message' => 'El emisor tiene suscripciones asociadas y no puede ser eliminado.'], 422);
                }
            }

            // users linked via company_id
            if (Schema::hasTable('users') && Schema::hasColumn('users', 'company_id')) {
                $exists = DB::table('users')->where('company_id', $company->id)->exists();
                if ($exists) {
                    return response()->json(['message' => 'El emisor tiene usuarios asociados y no puede ser eliminado.'], 422);
                }
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
     * Prepare deletion for a company that has been INACTIVO for at least 1 year.
     * Generates CSV exports, zips them, stores backup and emails the client a copy/notification.
     */
    public function prepareDeletion(Request $request, $id)
    {
        $company = Company::findOrFail($id);

        // Only allow if company has estado INACTIVO and last updated at least 1 year ago
        try {
            $cutoff = Carbon::now()->subYear();
            $updatedAt = $company->updated_at ?? $company->created_at;
            if (!($company->estado === 'INACTIVO' && Carbon::parse($updatedAt)->lessThanOrEqualTo($cutoff))) {
                return response()->json(['message' => 'Solo se pueden preparar para eliminación emisores inactivos por al menos 1 año.'], 422);
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

        // must be inactive for at least 1 year
        try {
            $cutoff = Carbon::now()->subYear();
            $updatedAt = $company->updated_at ?? $company->created_at;
            if (!($company->estado === 'INACTIVO' && Carbon::parse($updatedAt)->lessThanOrEqualTo($cutoff))) {
                return response()->json(['message' => 'Solo se pueden eliminar emisores inactivos por al menos 1 año.'], 422);
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
            if (Schema::hasTable('users') && Schema::hasColumn('users', 'company_id')) {
                DB::table('users')->where('company_id', $company->id)->delete();
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
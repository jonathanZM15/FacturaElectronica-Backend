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

class EmisorController extends Controller
{
    public function index(Request $request)
    {
        $q = $request->string('q')->toString();
        $estado = $request->string('estado')->toString();
        $desde = $request->date('fecha_inicio');
        $hasta = $request->date('fecha_fin');

        $query = Company::query();

        if ($estado !== '') {
            $query->where('estado', $estado);
        }

        if ($q !== '') {
            $query->where(function ($qq) use ($q) {
                $qq->where('ruc', 'like', "%{$q}%")
                   ->orWhere('razon_social', 'like', "%{$q}%")
                   ->orWhere('nombre_comercial', 'like', "%{$q}%");
            });
        }

        if ($desde) $query->whereDate('created_at', '>=', $desde);
        if ($hasta) $query->whereDate('created_at', '<=', $hasta);

        $items = $query->orderByDesc('id')->get()->map(function ($c) {
            return array_merge($c->toArray(), [
                'logo_url' => $c->logo_url, // accessor
            ]);
        });

        return response()->json(['data' => $items]);
    }

    public function store(Request $request)
    {
        $rules = [
            'ruc' => ['required','string','max:13','min:10','unique:companies,ruc'],
            'razon_social' => ['required','string','max:255'],
            'nombre_comercial' => ['nullable','string','max:255'],
            'direccion_matriz' => ['nullable','string','max:500'],

            'regimen_tributario' => ['nullable','in:GENERAL,RIMPE_POPULAR,RIMPE_EMPRENDEDOR,MICRO_EMPRESA'],
            'obligado_contabilidad' => ['nullable','in:SI,NO'],
            'contribuyente_especial' => ['nullable','in:SI,NO'],
            'agente_retencion' => ['nullable','in:SI,NO'],
            'tipo_persona' => ['nullable','in:NATURAL,JURIDICA'],
            'codigo_artesano' => ['nullable','string','max:50'],

            'correo_remitente' => ['nullable','email','max:255'],
            'estado' => ['required','in:ACTIVO,INACTIVO'],
            'ambiente' => ['required','in:PRODUCCION,PRUEBAS'],
            'tipo_emision' => ['required','in:NORMAL,INDISPONIBILIDAD'],

            'logo' => ['nullable','image','mimes:jpg,jpeg,png','max:2048'],
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

        if ($request->hasFile('logo')) {
            $path = $request->file('logo')->store('emisores/logos', 'public');
            $company->logo_path = $path;
        }

        $company->save();

        $payload = array_merge($company->toArray(), [
            'logo_url' => $company->logo_url,
        ]);

        return response()->json(['data' => $payload], 201);
    }

    // Show a single emisor with editable flags
    public function show($id)
    {
        $company = Company::findOrFail($id);

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
        $rules = [
            'ruc' => ['required','string','max:13','min:10', Rule::unique('companies','ruc')->ignore($company->id)],
            'razon_social' => ['required','string','max:255'],
            'nombre_comercial' => ['nullable','string','max:255'],
            'direccion_matriz' => ['nullable','string','max:500'],

            'regimen_tributario' => ['nullable','in:GENERAL,RIMPE_POPULAR,RIMPE_EMPRENDEDOR,MICRO_EMPRESA'],
            'obligado_contabilidad' => ['nullable','in:SI,NO'],
            'contribuyente_especial' => ['nullable','in:SI,NO'],
            'agente_retencion' => ['nullable','in:SI,NO'],
            'tipo_persona' => ['nullable','in:NATURAL,JURIDICA'],
            'codigo_artesano' => ['nullable','string','max:50'],

            'correo_remitente' => ['nullable','email','max:255'],
            'estado' => ['required','in:ACTIVO,INACTIVO'],
            'ambiente' => ['required','in:PRODUCCION,PRUEBAS'],
            'tipo_emision' => ['required','in:NORMAL,INDISPONIBILIDAD'],

            'logo' => ['nullable','image','mimes:jpg,jpeg,png','max:2048'],
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

        if ($request->hasFile('logo')) {
            $path = $request->file('logo')->store('emisores/logos', 'public');
            // delete old
            if ($company->logo_path && Storage::disk('public')->exists($company->logo_path)) {
                try { Storage::disk('public')->delete($company->logo_path); } catch (\Exception $_) {}
            }
            $company->logo_path = $path;
        }

        // Attempt to set updated_by if column exists and user is authenticated
        try {
            if (Schema::hasColumn('companies', 'updated_by') && Auth::check()) {
                $company->updated_by = Auth::id();
            }
        } catch (\Exception $_) {
            // ignore
        }

        $company->save();

        // Audit log
        try {
            $userId = Auth::check() ? Auth::id() : null;
            Log::info('Emisor updated', ['company_id' => $company->id, 'updated_by' => $userId]);
        } catch (\Exception $_) {}

        $payload = array_merge($company->toArray(), [
            'logo_url' => $company->logo_url,
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

        $coeff = [2,1,2,1,2,1,2,1,2];
        $sum = 0;
        for ($i = 0; $i < 9; $i++) {
            $v = intval($cedula[$i]) * $coeff[$i];
            if ($v >= 10) $v -= 9;
            $sum += $v;
        }
        $mod = $sum % 10;
        $check = $mod == 0 ? 0 : 10 - $mod;
        return $check === intval($cedula[9]);
    }

    private function validatePrivateCompany(string $ruc): bool
    {
        $ruc = preg_replace('/\D/', '', $ruc);
        if (strlen($ruc) < 10) return false;
        if (!ctype_digit($ruc)) return false;

        $coeff = [4,3,2,7,6,5,4,3,2];
        $sum = 0;
        for ($i = 0; $i < 9; $i++) {
            $sum += intval($ruc[$i]) * $coeff[$i];
        }
        $mod = $sum % 11;
        $check = $mod == 0 ? 0 : 11 - $mod;
        if ($check == 10) return false;
        if ($check != intval($ruc[9])) return false;

        // if length is 13, last three digits should be > 0
        if (strlen($ruc) == 13) {
            $suffix = intval(substr($ruc, 10, 3));
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
}
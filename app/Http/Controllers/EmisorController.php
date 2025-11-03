<?php

namespace App\Http\Controllers;

use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

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

        $data = $request->validate($rules);

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
}
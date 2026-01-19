<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Establecimiento extends Model
{
    protected $table = 'establecimientos';

    protected $fillable = [
        'company_id', 'codigo', 'estado', 'nombre', 'nombre_comercial', 'direccion', 'correo', 'telefono', 'logo_path', 'actividades_economicas', 'fecha_inicio_actividades', 'fecha_reinicio_actividades', 'fecha_cierre_establecimiento', 'created_by', 'updated_by'
    ];

    protected $casts = [
        'actividades_economicas' => 'string'
    ];

    protected $appends = ['logo_url', 'created_by_name', 'updated_by_name', 'created_by_info', 'updated_by_info'];

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    /**
     * Relación: Un establecimiento tiene muchos puntos de emisión
     */
    public function puntos_emision()
    {
        return $this->hasMany(PuntoEmision::class, 'establecimiento_id');
    }

    /**
     * Relación con el usuario que creó el registro
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Relación con el usuario que actualizó el registro
     */
    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Accessor para obtener el nombre del usuario que creó el registro
     */
    public function getCreatedByNameAttribute(): ?string
    {
        return $this->creator ? $this->creator->name : null;
    }

    /**
     * Accessor para obtener el nombre del usuario que actualizó el registro
     */
    public function getUpdatedByNameAttribute(): ?string
    {
        return $this->updater ? $this->updater->name : null;
    }

    /**
     * Accessor para obtener información completa del usuario que creó el registro
     * Formato: { id, role, username, nombres, apellidos, full_display }
     */
    public function getCreatedByInfoAttribute(): ?array
    {
        if (!$this->creator) {
            return null;
        }
        $user = $this->creator;
        // Handle role as enum or string
        $roleValue = $user->role instanceof \App\Enums\UserRole ? $user->role->value : ($user->role ?? '');
        // Use username or fallback to name
        $usernameDisplay = $user->username ?? $user->name ?? '';
        // Use nombres/apellidos or fallback to name
        $nombresDisplay = $user->nombres ?? '';
        $apellidosDisplay = $user->apellidos ?? '';
        // If no nombres/apellidos, use name as full name
        $fullNameDisplay = trim($nombresDisplay . ' ' . $apellidosDisplay);
        if (empty($fullNameDisplay) && $user->name) {
            $fullNameDisplay = $user->name;
        }
        return [
            'id' => $user->id,
            'role' => strtoupper($roleValue),
            'username' => $usernameDisplay,
            'nombres' => $nombresDisplay ?: ($user->name ?? ''),
            'apellidos' => $apellidosDisplay,
            'full_display' => strtoupper($roleValue) . ' - ' . $usernameDisplay . ' - ' . $fullNameDisplay
        ];
    }

    /**
     * Accessor para obtener información completa del usuario que actualizó el registro
     * Formato: { id, role, username, nombres, apellidos, full_display }
     */
    public function getUpdatedByInfoAttribute(): ?array
    {
        if (!$this->updater) {
            return null;
        }
        $user = $this->updater;
        // Handle role as enum or string
        $roleValue = $user->role instanceof \App\Enums\UserRole ? $user->role->value : ($user->role ?? '');
        // Use username or fallback to name
        $usernameDisplay = $user->username ?? $user->name ?? '';
        // Use nombres/apellidos or fallback to name
        $nombresDisplay = $user->nombres ?? '';
        $apellidosDisplay = $user->apellidos ?? '';
        // If no nombres/apellidos, use name as full name
        $fullNameDisplay = trim($nombresDisplay . ' ' . $apellidosDisplay);
        if (empty($fullNameDisplay) && $user->name) {
            $fullNameDisplay = $user->name;
        }
        return [
            'id' => $user->id,
            'role' => strtoupper($roleValue),
            'username' => $usernameDisplay,
            'nombres' => $nombresDisplay ?: ($user->name ?? ''),
            'apellidos' => $apellidosDisplay,
            'full_display' => strtoupper($roleValue) . ' - ' . $usernameDisplay . ' - ' . $fullNameDisplay
        ];
    }

    /**
     * Accessor para obtener la URL del logo
     */
    public function getLogoUrlAttribute(): ?string
    {
        if (!$this->logo_path) {
            return null;
        }
        
        // Use API route to serve the image, which avoids depending on the public/storage symlink
        try {
            if (Storage::disk('public')->exists($this->logo_path)) {
                // Add cache-busting parameter using updated_at timestamp
                $timestamp = $this->updated_at ? $this->updated_at->getTimestamp() : time();
                return url('/api/emisores/' . $this->company_id . '/establecimientos/' . $this->id . '/logo-file?v=' . $timestamp);
            }
        } catch (\Exception $_) {
            // ignore and fallback
        }

        // Fallback: generate URL using asset (for backward compatibility)
        $url = asset('storage/' . $this->logo_path);
        
        // Add cache-busting parameter
        $timestamp = $this->updated_at ? $this->updated_at->getTimestamp() : time();
        $url .= (str_contains($url, '?') ? '&' : '?') . 'v=' . $timestamp;
        
        return $url;
    }
}

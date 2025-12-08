<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Company extends Model
{
    protected $table = 'emisores';
    
    protected $fillable = [
        'ruc','razon_social','nombre_comercial','direccion_matriz',
        'regimen_tributario','obligado_contabilidad','contribuyente_especial','agente_retencion',
        'tipo_persona','codigo_artesano','correo_remitente','estado','ambiente','tipo_emision','logo_path',
        'created_by', 'updated_by',
    ];

    protected $appends = ['logo_url', 'created_by_name', 'created_by_username', 'updated_by_name'];

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
        if (!$this->creator) return null;

        // Prefer nombres+apellidos if present, else username/email
        $fullName = trim(($this->creator->nombres ?? '') . ' ' . ($this->creator->apellidos ?? ''));
        if ($fullName !== '') {
            return $fullName;
        }

        return $this->creator->username
            ?? $this->creator->email
            ?? null;
    }

    public function getCreatedByUsernameAttribute(): ?string
    {
        if (!$this->creator) return null;
        return $this->creator->username
            ?? $this->creator->email
            ?? null;
    }

    /**
     * Accessor para obtener el nombre del usuario que actualizó el registro
     */
    public function getUpdatedByNameAttribute(): ?string
    {
        return $this->updater ? $this->updater->name : null;
    }

    public function getLogoUrlAttribute(): ?string
    {
        if (!$this->logo_path) {
            return null;
        }
        // If the file exists on the public disk, prefer serving it through
        // an API route that streams the image. This avoids depending on
        // the presence of the `public/storage` symlink on the server.
        try {
            if (Storage::disk('public')->exists($this->logo_path)) {
                // Add cache-busting parameter using updated_at timestamp
                $timestamp = $this->updated_at ? $this->updated_at->getTimestamp() : time();
                return url('/api/companies/' . $this->id . '/logo-file?v=' . $timestamp);
            }
        } catch (\Exception $_) {
            // ignore and fallback to Storage::url()
        }

        // Fallback: generate URL using the configured filesystem URL
        $url = Storage::url($this->logo_path);

        // If the URL is relative, prefix with APP_URL
        if (!str_starts_with($url, 'http')) {
            $url = rtrim(config('app.url'), '/') . $url;
        }

        // Add cache-busting parameter
        $timestamp = $this->updated_at ? $this->updated_at->getTimestamp() : time();
        $url .= (str_contains($url, '?') ? '&' : '?') . 'v=' . $timestamp;

        return $url;
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Company extends Model
{
    protected $fillable = [
        'ruc','razon_social','nombre_comercial','direccion_matriz',
        'regimen_tributario','obligado_contabilidad','contribuyente_especial','agente_retencion',
        'tipo_persona','codigo_artesano','correo_remitente','estado','ambiente','tipo_emision','logo_path',
    ];

    protected $appends = ['logo_url'];

    public function getLogoUrlAttribute(): ?string
    {
        if (!$this->logo_path) {
            return null;
        }
        
        // Generar URL completa con el dominio
        $url = Storage::url($this->logo_path);
        
        // Si la URL es relativa, agregarle el APP_URL
        if (!str_starts_with($url, 'http')) {
            $url = rtrim(config('app.url'), '/') . $url;
        }
        
        return $url;
    }
}

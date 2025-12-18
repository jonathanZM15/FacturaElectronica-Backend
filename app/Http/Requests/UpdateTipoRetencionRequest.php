<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\TipoRetencion;

class UpdateTipoRetencionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'tipo_retencion' => [
                'sometimes',
                'string',
                'in:' . implode(',', TipoRetencion::TIPOS_RETENCION),
            ],
            'codigo' => [
                'sometimes',
                'string',
                'max:50',
                'regex:/^[a-zA-Z0-9]+$/', // Solo letras y números
            ],
            'nombre' => [
                'sometimes',
                'string',
                'max:255',
            ],
            'porcentaje' => [
                'sometimes',
                'numeric',
                'min:0',
                'max:100',
                'regex:/^\d+(\.\d{1,2})?$/', // Hasta 2 decimales
            ],
            'password' => [
                'required',
                'string',
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'tipo_retencion.in' => 'El tipo de retención seleccionado no es válido',
            'codigo.max' => 'El código no puede exceder 50 caracteres',
            'codigo.regex' => 'El código solo puede contener letras y números, sin espacios ni caracteres especiales',
            'nombre.max' => 'El nombre no puede exceder 255 caracteres',
            'porcentaje.numeric' => 'El porcentaje debe ser un valor numérico',
            'porcentaje.min' => 'El porcentaje no puede ser negativo',
            'porcentaje.max' => 'El porcentaje no puede ser mayor a 100',
            'porcentaje.regex' => 'El porcentaje solo puede tener hasta 2 decimales',
            'password.required' => 'La contraseña de confirmación es obligatoria',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'tipo_retencion' => 'tipo de retención',
            'codigo' => 'código',
            'nombre' => 'nombre',
            'porcentaje' => 'porcentaje',
            'password' => 'contraseña',
        ];
    }
}

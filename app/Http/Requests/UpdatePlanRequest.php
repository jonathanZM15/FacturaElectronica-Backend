<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use App\Enums\UserRole;

class UpdatePlanRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Solo el administrador puede actualizar planes
        if (!Auth::check()) {
            return false;
        }

        /** @var \App\Models\User|null $user */
        $user = Auth::user();
        
        return $user && $user->role === UserRole::ADMINISTRADOR;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        // Obtener el ID del plan desde los parámetros de ruta
        // La ruta es /planes/{id}, así que accedemos con 'id'
        $planId = $this->route('id');
        
        // Si no viene el ID desde la ruta, intentar desde el request
        if (!$planId || $planId === '' || $planId === null) {
            $planId = $this->input('id') ?? 0;
        }
        
        // Asegurar que siempre sea un número válido
        $planId = (int)$planId;
        if ($planId <= 0) {
            $planId = 0;
        }
        
        return [
            'nombre' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                // Validación de nombre único excluyendo el plan actual
                'unique:planes,nombre,' . $planId . ',id',
            ],
            'cantidad_comprobantes' => [
                'sometimes',
                'required',
                'integer',
                'min:1',
                'numeric',
            ],
            'precio' => [
                'sometimes',
                'required',
                'numeric',
                'min:0.01', // Debe ser positivo (mayor a 0)
                'regex:/^\d+(\.\d{1,2})?$/', // Permite hasta 2 decimales
            ],
            'periodo' => [
                'sometimes',
                'required',
                'in:Mensual,Trimestral,Semestral,Anual,Bianual,Trianual',
            ],
            'observacion' => [
                'nullable',
                'string',
            ],
            'color_fondo' => [
                'sometimes',
                'required',
                'string',
                'regex:/^#[0-9A-Fa-f]{6}$/', // Formato hexadecimal válido
            ],
            'color_texto' => [
                'sometimes',
                'required',
                'string',
                'regex:/^#[0-9A-Fa-f]{6}$/', // Formato hexadecimal válido
            ],
            'estado' => [
                'sometimes',
                'required',
                'in:Activo,Desactivado',
            ],
            'comprobantes_minimos' => [
                'sometimes',
                'required',
                'integer',
                'min:1',
                'numeric',
            ],
            'dias_minimos' => [
                'sometimes',
                'required',
                'integer',
                'min:1',
                'numeric',
            ],
        ];
    }

    /**
     * Prepara los datos para validación (normaliza el nombre)
     */
    protected function prepareForValidation(): void
    {
        // Normalizar nombre: trim y convertir espacios múltiples en uno
        if ($this->has('nombre')) {
            $this->merge([
                'nombre' => trim(preg_replace('/\s+/', ' ', $this->nombre ?? '')),
            ]);
        }
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'nombre' => 'nombre del plan',
            'cantidad_comprobantes' => 'cantidad de comprobantes',
            'precio' => 'precio',
            'periodo' => 'período',
            'observacion' => 'observación',
            'color_fondo' => 'color de fondo',
            'color_texto' => 'color de texto',
            'estado' => 'estado',
            'comprobantes_minimos' => 'comprobantes mínimos',
            'dias_minimos' => 'días mínimos',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre del plan es obligatorio.',
            'cantidad_comprobantes.required' => 'La cantidad de comprobantes es obligatoria.',
            'cantidad_comprobantes.integer' => 'La cantidad de comprobantes debe ser un número entero.',
            'cantidad_comprobantes.min' => 'La cantidad de comprobantes debe ser al menos 1.',
            'cantidad_comprobantes.numeric' => 'La cantidad de comprobantes debe ser un número positivo.',
            'comprobantes_minimos.numeric' => 'Los comprobantes mínimos deben ser un número positivo.',
            'dias_minimos.numeric' => 'Los días mínimos deben ser un número positivo.',
            'precio.required' => 'El precio es obligatorio.',
            'precio.numeric' => 'El precio debe ser un valor numérico.',
            'precio.min' => 'El precio debe ser mayor a 0 (valores positivos).',
            'precio.regex' => 'El precio debe ser un decimal válido con hasta 2 dígitos después del punto (ej: 99.99).',
            'nombre.unique' => 'El nombre del plan ya existe. Por favor usa un nombre diferente.',
            'periodo.required' => 'El período es obligatorio.',
            'periodo.in' => 'El período debe ser: Mensual, Trimestral, Semestral, Anual, Bianual o Trianual.',
            'color_fondo.required' => 'El color de fondo es obligatorio.',
            'color_fondo.regex' => 'El color de fondo debe estar en formato hexadecimal (ej: #808080).',
            'color_texto.required' => 'El color de texto es obligatorio.',
            'color_texto.regex' => 'El color de texto debe estar en formato hexadecimal (ej: #000000).',
            'estado.required' => 'El estado es obligatorio.',
            'estado.in' => 'El estado debe ser: Activo o Desactivado.',
            'comprobantes_minimos.required' => 'Los comprobantes mínimos son obligatorios.',
            'comprobantes_minimos.integer' => 'Los comprobantes mínimos deben ser un número entero.',
            'comprobantes_minimos.min' => 'Los comprobantes mínimos deben ser al menos 1.',
            'dias_minimos.required' => 'Los días mínimos son obligatorios.',
            'dias_minimos.integer' => 'Los días mínimos deben ser un número entero.',
            'dias_minimos.min' => 'Los días mínimos deben ser al menos 1.',
        ];
    }
}

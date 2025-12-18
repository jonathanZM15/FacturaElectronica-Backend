<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\TipoImpuesto;
use Illuminate\Validation\Rule;

class UpdateTipoImpuestoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // La autorización se maneja en el controlador
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $tipoImpuestoId = $this->route('id');

        return [
            'tipo_impuesto' => [
                'sometimes',
                'required',
                'string',
                'in:' . implode(',', TipoImpuesto::TIPOS_IMPUESTO),
            ],
            'tipo_tarifa' => [
                'sometimes',
                'required',
                'string',
                'in:' . implode(',', TipoImpuesto::TIPOS_TARIFA),
            ],
            'codigo' => [
                'sometimes',
                'required',
                'integer',
                'min:1',
                Rule::unique('tipos_impuesto', 'codigo')->ignore($tipoImpuestoId),
            ],
            'nombre' => [
                'sometimes',
                'required',
                'string',
                'max:100',
                Rule::unique('tipos_impuesto', 'nombre')->ignore($tipoImpuestoId),
            ],
            'valor_tarifa' => [
                'sometimes',
                'required',
                'numeric',
                'min:0',
                'max:999999.99',
            ],
            'estado' => [
                'sometimes',
                'required',
                'string',
                'in:' . implode(',', TipoImpuesto::ESTADOS),
            ],
            'password' => [
                'required',
                'string',
            ],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Validar que el tipo de tarifa corresponda al tipo de impuesto
            $tipoImpuesto = $this->input('tipo_impuesto');
            $tipoTarifa = $this->input('tipo_tarifa');

            if ($tipoImpuesto && $tipoTarifa) {
                $tarifasPermitidas = TipoImpuesto::TARIFA_POR_TIPO[$tipoImpuesto] ?? [];
                
                if (!in_array($tipoTarifa, $tarifasPermitidas)) {
                    $validator->errors()->add(
                        'tipo_tarifa',
                        "El tipo de tarifa '{$tipoTarifa}' no es válido para el tipo de impuesto '{$tipoImpuesto}'. " .
                        "Valores permitidos: " . implode(', ', $tarifasPermitidas)
                    );
                }
            }
        });
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'tipo_impuesto.required' => 'El tipo de impuesto es obligatorio.',
            'tipo_impuesto.in' => 'El tipo de impuesto debe ser: ' . implode(', ', TipoImpuesto::TIPOS_IMPUESTO),
            'tipo_tarifa.required' => 'El tipo de tarifa es obligatorio.',
            'tipo_tarifa.in' => 'El tipo de tarifa debe ser: ' . implode(', ', TipoImpuesto::TIPOS_TARIFA),
            'codigo.required' => 'El código es obligatorio.',
            'codigo.integer' => 'El código debe ser un número entero.',
            'codigo.min' => 'El código debe ser un número positivo.',
            'codigo.unique' => 'Ya existe un tipo de impuesto con este código.',
            'nombre.required' => 'El nombre es obligatorio.',
            'nombre.max' => 'El nombre no puede exceder 100 caracteres.',
            'nombre.unique' => 'Ya existe un tipo de impuesto con este nombre.',
            'valor_tarifa.required' => 'El valor de la tarifa es obligatorio.',
            'valor_tarifa.numeric' => 'El valor de la tarifa debe ser numérico.',
            'valor_tarifa.min' => 'El valor de la tarifa debe ser positivo.',
            'estado.required' => 'El estado es obligatorio.',
            'estado.in' => 'El estado debe ser: ' . implode(', ', TipoImpuesto::ESTADOS),
            'password.required' => 'La contraseña es obligatoria para confirmar los cambios.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'tipo_impuesto' => 'tipo de impuesto',
            'tipo_tarifa' => 'tipo de tarifa',
            'codigo' => 'código',
            'nombre' => 'nombre',
            'valor_tarifa' => 'valor de tarifa',
            'estado' => 'estado',
            'password' => 'contraseña',
        ];
    }
}

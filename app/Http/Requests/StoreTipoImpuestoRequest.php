<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\TipoImpuesto;

class StoreTipoImpuestoRequest extends FormRequest
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
        return [
            'tipo_impuesto' => [
                'required',
                'string',
                'in:' . implode(',', TipoImpuesto::TIPOS_IMPUESTO),
            ],
            'tipo_tarifa' => [
                'required',
                'string',
                'in:' . implode(',', TipoImpuesto::TIPOS_TARIFA),
            ],
            'codigo' => [
                'required',
                'integer',
                'min:1',
                'unique:tipos_impuesto,codigo',
            ],
            'nombre' => [
                'required',
                'string',
                'max:100',
                'unique:tipos_impuesto,nombre',
            ],
            'valor_tarifa' => [
                'required',
                'numeric',
                'min:0',
                'max:999999.99',
            ],
            'estado' => [
                'required',
                'string',
                'in:' . implode(',', TipoImpuesto::ESTADOS),
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
        ];
    }
}

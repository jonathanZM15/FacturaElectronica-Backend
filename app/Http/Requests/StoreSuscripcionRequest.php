<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use App\Enums\UserRole;
use App\Models\Suscripcion;
use Carbon\Carbon;

class StoreSuscripcionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        if (!Auth::check()) {
            return false;
        }

        /** @var \App\Models\User|null $user */
        $user = Auth::user();
        
        // Solo Administrador o Distribuidor pueden crear suscripciones
        return $user && in_array($user->role, [UserRole::ADMINISTRADOR, UserRole::DISTRIBUIDOR]);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'emisor_id' => [
                'required',
                'integer',
                'exists:emisores,id',
            ],
            'plan_id' => [
                'required',
                'integer',
                'exists:planes,id',
            ],
            'fecha_inicio' => [
                'required',
                'date',
                'after_or_equal:today',
                'before_or_equal:' . Carbon::today()->addDays(30)->format('Y-m-d'),
            ],
            'fecha_fin' => [
                'required',
                'date',
                'after:fecha_inicio',
            ],
            'monto' => [
                'required',
                'numeric',
                'min:0',
            ],
            'cantidad_comprobantes' => [
                'required',
                'integer',
                'min:1',
            ],
            'estado_suscripcion' => [
                'sometimes',
                'in:Vigente,Suspendido',
            ],
            'forma_pago' => [
                'required',
                'in:Efectivo,Transferencia,Otro',
            ],
            'estado_transaccion' => [
                'sometimes',
                'in:Pendiente,Confirmada',
            ],
            'comprobante_pago' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png',
                'max:5120', // 5MB
            ],
            'factura' => [
                'nullable',
                'file',
                'mimes:pdf',
                'max:10240', // 10MB
            ],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Verificar que no exista una suscripción vigente para el emisor
            if ($this->estado_suscripcion === 'Vigente' || 
                (Auth::user()->role === UserRole::ADMINISTRADOR && $this->estado_suscripcion === 'Vigente')) {
                
                $existeVigente = Suscripcion::where('emisor_id', $this->emisor_id)
                    ->where('estado_suscripcion', 'Vigente')
                    ->exists();
                
                if ($existeVigente) {
                    $validator->errors()->add(
                        'estado_suscripcion',
                        'Ya existe una suscripción activa para este emisor.'
                    );
                }
            }

            // Verificar que la cantidad de comprobantes sea >= a la del plan
            if ($this->plan_id && $this->cantidad_comprobantes) {
                $plan = \App\Models\Plan::find($this->plan_id);
                if ($plan && $this->cantidad_comprobantes < $plan->cantidad_comprobantes) {
                    $validator->errors()->add(
                        'cantidad_comprobantes',
                        'La cantidad de comprobantes no puede ser menor a la definida en el plan (' . $plan->cantidad_comprobantes . ').'
                    );
                }
            }

            // Verificar que el plan esté activo
            if ($this->plan_id) {
                $plan = \App\Models\Plan::find($this->plan_id);
                if ($plan && $plan->estado !== 'Activo') {
                    $validator->errors()->add(
                        'plan_id',
                        'El plan seleccionado no está activo.'
                    );
                }
            }
        });
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'emisor_id' => 'emisor',
            'plan_id' => 'plan',
            'fecha_inicio' => 'fecha de inicio',
            'fecha_fin' => 'fecha de fin',
            'monto' => 'monto',
            'cantidad_comprobantes' => 'cantidad de comprobantes',
            'estado_suscripcion' => 'estado de suscripción',
            'forma_pago' => 'forma de pago',
            'estado_transaccion' => 'estado de transacción',
            'comprobante_pago' => 'comprobante de pago',
            'factura' => 'factura',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'emisor_id.required' => 'El emisor es obligatorio.',
            'emisor_id.exists' => 'El emisor seleccionado no existe.',
            'plan_id.required' => 'El plan es obligatorio.',
            'plan_id.exists' => 'El plan seleccionado no existe.',
            'fecha_inicio.required' => 'La fecha de inicio es obligatoria.',
            'fecha_inicio.after_or_equal' => 'La fecha de inicio no puede ser anterior a hoy.',
            'fecha_inicio.before_or_equal' => 'Fecha de inicio fuera del rango permitido.',
            'fecha_fin.required' => 'La fecha de fin es obligatoria.',
            'fecha_fin.after' => 'La fecha de fin debe ser posterior a la fecha de inicio.',
            'monto.required' => 'El monto es obligatorio.',
            'monto.numeric' => 'El monto debe ser un valor numérico.',
            'monto.min' => 'El monto debe ser un valor positivo.',
            'cantidad_comprobantes.required' => 'La cantidad de comprobantes es obligatoria.',
            'cantidad_comprobantes.integer' => 'La cantidad de comprobantes debe ser un número entero.',
            'cantidad_comprobantes.min' => 'La cantidad de comprobantes debe ser al menos 1.',
            'forma_pago.required' => 'La forma de pago es obligatoria.',
            'forma_pago.in' => 'La forma de pago debe ser: Efectivo, Transferencia u Otro.',
            'comprobante_pago.image' => 'El comprobante debe ser una imagen.',
            'comprobante_pago.mimes' => 'El comprobante debe ser un archivo JPG, JPEG o PNG.',
            'comprobante_pago.max' => 'El comprobante no puede exceder 5MB.',
            'factura.mimes' => 'La factura debe ser un archivo PDF.',
            'factura.max' => 'La factura no puede exceder 10MB.',
        ];
    }
}

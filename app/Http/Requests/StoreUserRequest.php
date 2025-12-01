<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use App\Services\PermissionService;

class StoreUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // El usuario debe estar autenticado
        if (!Auth::check()) {
            return false;
        }

        /** @var \App\Models\User|null $user */
        $user = Auth::user();
        
        // Verifica que el rol a crear sea permitido
        $rolACrear = $this->input('role');
        return PermissionService::puedoCrearRol($user, $rolACrear);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'cedula' => [
                'required',
                'string',
                'size:10',
                'digits:10',
                'unique:users,cedula'
            ],
            'nombres' => [
                'required',
                'string',
                'min:3',
                'max:255',
                'regex:/^[\p{L}\s\-\']+$/u' // Solo caracteres alfabéticos
            ],
            'apellidos' => [
                'required',
                'string',
                'min:3',
                'max:255',
                'regex:/^[\p{L}\s\-\']+$/u' // Solo caracteres alfabéticos
            ],
            'username' => [
                'required',
                'string',
                'min:3',
                'max:255',
                'unique:users,username'
            ],
            'email' => [
                'required',
                'email',
                'max:255',
                'unique:users,email' // Email único en la tabla users
            ],
            'password' => [
                'required',
                'string',
                'min:8',
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/', // Al menos 1 mayúscula, 1 minúscula, 1 número, 1 carácter especial
                'confirmed' // Debe coincidir con password_confirmation
            ],
            'password_confirmation' => [
                'required',
                'string'
            ],
            'role' => [
                'required',
                'in:administrador,distribuidor,emisor,gerente,cajero',
                function ($attribute, $value, $fail) {
                    /** @var \App\Models\User|null $user */
                    $user = Auth::user();
                    $rolesPermitidos = $user->rolesPuedoCrear();
                    if (!in_array($value, $rolesPermitidos)) {
                        $fail("No tienes permiso para crear usuarios con rol '{$value}'");
                    }
                },
            ],
            'distribuidor_id' => 'nullable|exists:users,id',
            'emisor_id' => 'nullable|exists:users,id',
            'estado' => 'nullable|in:nuevo,activo,pendiente_verificacion,suspendido,retirado',
            'establecimientos_ids' => 'nullable|array',
            'establecimientos_ids.*' => 'integer',
            'puntos_emision_ids' => 'nullable|array',
            'puntos_emision_ids.*' => 'integer',
        ];
    }

    /**
     * Get custom error messages.
     */
    public function messages(): array
    {
        return [
            'cedula.required' => 'La cédula es obligatoria',
            'cedula.size' => 'La cédula debe tener exactamente 10 dígitos',
            'cedula.digits' => 'La cédula debe contener solo números',
            'cedula.unique' => 'Esta cédula ya está registrada',
            
            'nombres.required' => 'Los nombres son obligatorios',
            'nombres.min' => 'El nombre debe tener al menos 3 caracteres',
            'nombres.max' => 'El nombre no puede exceder 255 caracteres',
            'nombres.regex' => 'El nombre solo puede contener letras, espacios y guiones',
            
            'apellidos.required' => 'Los apellidos son obligatorios',
            'apellidos.min' => 'El apellido debe tener al menos 3 caracteres',
            'apellidos.max' => 'El apellido no puede exceder 255 caracteres',
            'apellidos.regex' => 'El apellido solo puede contener letras, espacios y guiones',
            
            'username.required' => 'El nombre de usuario es obligatorio',
            'username.min' => 'El nombre de usuario debe tener al menos 3 caracteres',
            'username.unique' => 'Este nombre de usuario ya está registrado',
            
            'email.required' => 'El email es obligatorio',
            'email.email' => 'El email no es válido',
            'email.unique' => 'El email ya está registrado',
            
            'password.required' => 'La contraseña es obligatoria',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres',
            'password.regex' => 'La contraseña debe contener mayúscula, minúscula, número y carácter especial (@$!%*?&)',
            'password.confirmed' => 'Las contraseñas no coinciden',
            
            'role.required' => 'El rol es obligatorio',
            'role.in' => 'El rol seleccionado no es válido',
            
            'distribuidor_id.exists' => 'El distribuidor seleccionado no existe',
            'emisor_id.exists' => 'El emisor seleccionado no existe',
            'establecimientos_ids.array' => 'Los establecimientos deben ser un array',
            'establecimientos_ids.*.integer' => 'Cada ID de establecimiento debe ser un número entero',
            'puntos_emision_ids.array' => 'Los puntos de emisión deben ser un array',
            'puntos_emision_ids.*.integer' => 'Cada ID de punto de emisión debe ser un número entero',
        ];
    }
}

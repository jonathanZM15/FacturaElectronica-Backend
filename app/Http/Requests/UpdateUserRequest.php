<?php

namespace App\Http\Requests;

use App\Services\PermissionService;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class UpdateUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        /** @var User|null $currentUser */
        $currentUser = Auth::user();
        $userIdToUpdate = $this->route('usuario');
        $userToUpdate = User::find($userIdToUpdate);

        if (!$userToUpdate) {
            return false;
        }

        // Usar PermissionService para validar si puede gestionar este usuario
        return PermissionService::puedeGestionarUsuario($currentUser, $userToUpdate);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        $userId = $this->route('usuario'); // Obtener ID del usuario a actualizar
        /** @var User|null $currentUser */
        $currentUser = Auth::user();

        return [
            'cedula' => [
                'sometimes',
                'string',
                'size:10',
                'digits:10',
                'unique:users,cedula,' . $userId // Cédula única excepto el usuario actual
            ],
            'nombres' => [
                'sometimes',
                'string',
                'min:3',
                'max:255',
                'regex:/^[\p{L}\s\-\'áéíóúñÁÉÍÓÚÑ]+$/u'
            ],
            'apellidos' => [
                'sometimes',
                'string',
                'min:3',
                'max:255',
                'regex:/^[\p{L}\s\-\'áéíóúñÁÉÍÓÚÑ]+$/u'
            ],
            'username' => [
                'sometimes',
                'string',
                'min:3',
                'max:255',
                'unique:users,username,' . $userId // Username único excepto el usuario actual
            ],
            'email' => [
                'sometimes',
                'email',
                'max:255',
                'unique:users,email,' . $userId // Email único excepto el usuario actual
            ],
            'password' => [
                'sometimes',
                'string',
                'min:8',
                'confirmed',
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/'
            ],
            'role' => [
                'sometimes',
                'string',
                'in:administrador,distribuidor,emisor,gerente,cajero',
                function ($attribute, $value, $fail) use ($currentUser) {
                    // Validar que el rol es uno que el usuario puede crear
                    $rolesPermitidos = $currentUser->rolesPuedoCrear();
                    if (!in_array($value, $rolesPermitidos)) {
                        $fail('No tienes permiso para asignar el rol ' . $value);
                    }
                }
            ],
            'estado' => [
                'sometimes',
                'string',
                'in:nuevo,activo,pendiente_verificacion,suspendido,retirado',
                function ($attribute, $value, $fail) use ($userId) {
                    $user = User::find($userId);
                    if ($user && !$user->puedeTransicionarA($value)) {
                        $fail($user->getMensajeTransicionInvalida($value));
                    }
                }
            ],
            'distribuidor_id' => [
                'sometimes',
                'nullable',
                'integer',
                'exists:users,id'
            ],
            'emisor_id' => [
                'sometimes',
                'nullable',
                'integer',
                'exists:users,id'
            ],
            'establecimientos_ids' => [
                'sometimes',
                'nullable',
                'array'
            ],
            'establecimientos_ids.*' => [
                'integer',
                'exists:establecimientos,id'
            ],
            'puntos_emision_ids' => [
                'sometimes',
                'nullable',
                'array'
            ],
            'puntos_emision_ids.*' => [
                'integer',
                'exists:puntos_emision,id'
            ]
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'cedula.sometimes' => 'La cédula es opcional',
            'cedula.string' => 'La cédula debe ser texto',
            'cedula.size' => 'La cédula debe tener exactamente 10 dígitos',
            'cedula.digits' => 'La cédula solo debe contener números',
            'cedula.unique' => 'Esta cédula ya está registrada en el sistema',

            'nombres.sometimes' => 'Los nombres son opcionales',
            'nombres.string' => 'Los nombres deben ser texto',
            'nombres.min' => 'Los nombres deben tener al menos 3 caracteres',
            'nombres.max' => 'Los nombres no pueden exceder 255 caracteres',
            'nombres.regex' => 'Los nombres solo pueden contener letras, espacios, guiones y apóstrofes',

            'apellidos.sometimes' => 'Los apellidos son opcionales',
            'apellidos.string' => 'Los apellidos deben ser texto',
            'apellidos.min' => 'Los apellidos deben tener al menos 3 caracteres',
            'apellidos.max' => 'Los apellidos no pueden exceder 255 caracteres',
            'apellidos.regex' => 'Los apellidos solo pueden contener letras, espacios, guiones y apóstrofes',

            'username.sometimes' => 'El nombre de usuario es opcional',
            'username.string' => 'El nombre de usuario debe ser texto',
            'username.min' => 'El nombre de usuario debe tener al menos 3 caracteres',
            'username.max' => 'El nombre de usuario no puede exceder 255 caracteres',
            'username.unique' => 'Este nombre de usuario ya está registrado en el sistema',
            // HU: el nombre de usuario acepta todo tipo de caracteres

            'email.sometimes' => 'El correo electrónico es opcional',
            'email.email' => 'El correo electrónico debe ser válido',
            'email.unique' => 'Este correo electrónico ya está registrado en el sistema',
            'email.max' => 'El correo electrónico no puede exceder 255 caracteres',

            'password.sometimes' => 'La contraseña es opcional',
            'password.string' => 'La contraseña debe ser texto',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres',
            'password.confirmed' => 'Las contraseñas no coinciden',
            'password.regex' => 'La contraseña debe contener mayúsculas, minúsculas, números y caracteres especiales (@$!%*?&)',

            'role.sometimes' => 'El rol es opcional',
            'role.string' => 'El rol debe ser texto',
            'role.in' => 'El rol debe ser uno de: administrador, distribuidor, emisor, gerente, cajero',

            'estado.sometimes' => 'El estado es opcional',
            'estado.string' => 'El estado debe ser texto',
            'estado.in' => 'El estado debe ser: nuevo, activo, pendiente_verificacion, suspendido o retirado',

            'distribuidor_id.sometimes' => 'El distribuidor es opcional',
            'distribuidor_id.integer' => 'El distribuidor debe ser un número entero',
            'distribuidor_id.exists' => 'El distribuidor no existe',

            'emisor_id.sometimes' => 'El emisor es opcional',
            'emisor_id.integer' => 'El emisor debe ser un número entero',
            'emisor_id.exists' => 'El emisor no existe',

            'establecimientos_ids.sometimes' => 'Los establecimientos son opcionales',
            'establecimientos_ids.array' => 'Los establecimientos debe ser un arreglo',
            'establecimientos_ids.*.integer' => 'Cada establecimiento debe ser un número entero',
            'establecimientos_ids.*.exists' => 'Uno de los establecimientos no existe',

            'puntos_emision_ids.sometimes' => 'Los puntos de emisión son opcionales',
            'puntos_emision_ids.array' => 'Los puntos de emisión deben ser un arreglo',
            'puntos_emision_ids.*.integer' => 'Cada punto de emisión debe ser un número entero',
            'puntos_emision_ids.*.exists' => 'Uno de los puntos de emisión no existe'
        ];
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Services\EcuadorIdentificationValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ClienteController extends Controller
{
    use \App\Traits\ResolvesEmisor;

    public const TIPOS_IDENTIFICACION_SRI = [
        'RUC',
        'CEDULA',
        'PASAPORTE',
        'CONSUMIDOR_FINAL',
        'IDENTIFICACION_EXTERIOR',
        'PLACA',
    ];

    /**
     * Listar clientes paginados (20 por página) con búsqueda por ?search=
     * filtrando por identificacion, razon_social y email, scoped por emisor_id.
     */
    public function index(Request $request, string $emisorId): JsonResponse
    {
        $resolvedId = $this->resolveEmisorId($emisorId);

        $query = Cliente::where('emisor_id', $resolvedId)
            ->orderBy('razon_social');

        // Búsqueda por identificacion, razon_social o email mediante ?search=
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('identificacion', 'ILIKE', "%{$search}%")
                  ->orWhere('razon_social', 'ILIKE', "%{$search}%")
                  ->orWhere('email', 'ILIKE', "%{$search}%");
            });
        }

        // Filtro opcional por tipo de identificación
        if ($tipo = $request->query('tipo_identificacion')) {
            $query->where('tipo_identificacion', strtoupper($tipo));
        }

        // Si se solicita ?all=true devuelve todos sin paginar (útil para selects/combos)
        if ($request->boolean('all')) {
            return response()->json(['data' => $query->get()]);
        }

        $clientes = $query->paginate($request->integer('per_page', 20));

        return response()->json($clientes);
    }

    /**
     * Crear un cliente nuevo validando formato SRI y dígito verificador.
     */
    public function store(Request $request, string $emisorId): JsonResponse
    {
        $resolvedId = $this->resolveEmisorId($emisorId);

        $validator = $this->buildValidator($request->all(), $resolvedId);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error de validación',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $data['emisor_id']        = $resolvedId;
        $data['nombre_comercial'] = $data['nombre_comercial'] ?? null;
        $data['direccion']        = $data['direccion'] ?? '';
        $data['email']            = $data['email'] ?? '';
        $data['telefono']         = $data['telefono'] ?? null;
        $data['created_by']       = Auth::id();
        $data['updated_by']       = Auth::id();

        $cliente = Cliente::create($data);

        return response()->json([
            'data'    => $cliente,
            'message' => 'Cliente creado correctamente.',
        ], 201);
    }

    /**
     * Mostrar un cliente por ID validando que pertenezca al emisor autenticado.
     */
    public function show(string $emisorId, int $cliente): JsonResponse
    {
        $resolvedId = $this->resolveEmisorId($emisorId);

        $clienteModel = Cliente::where('emisor_id', $resolvedId)
            ->findOrFail($cliente);

        return response()->json(['data' => $clienteModel]);
    }

    /**
     * Actualizar cliente validando reglas SRI e ignorando unique del propio registro.
     */
    public function update(Request $request, string $emisorId, int $cliente): JsonResponse
    {
        $resolvedId = $this->resolveEmisorId($emisorId);

        $clienteModel = Cliente::where('emisor_id', $resolvedId)
            ->findOrFail($cliente);

        $validator = $this->buildValidator($request->all(), $resolvedId, $clienteModel->id);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error de validación',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $data['nombre_comercial'] = $data['nombre_comercial'] ?? null;
        $data['direccion']        = $data['direccion'] ?? '';
        $data['email']            = $data['email'] ?? '';
        $data['telefono']         = $data['telefono'] ?? null;
        $data['updated_by']       = Auth::id();

        $clienteModel->update($data);

        return response()->json([
            'data'    => $clienteModel,
            'message' => 'Cliente actualizado correctamente.',
        ]);
    }

    /**
     * Eliminar cliente físico. Si tiene comprobantes emitidos, bloquea con 422.
     */
    public function destroy(string $emisorId, int $cliente): JsonResponse
    {
        $resolvedId = $this->resolveEmisorId($emisorId);

        $clienteModel = Cliente::where('emisor_id', $resolvedId)
            ->findOrFail($cliente);

        if ($clienteModel->comprobantes()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar el cliente porque tiene comprobantes asociados.',
            ], 422);
        }

        $clienteModel->delete();

        return response()->json([
            'message' => 'Cliente eliminado correctamente.',
        ]);
    }

    /**
     * Buscar cliente por identificación exacta (para autocompletado rápido en facturas).
     */
    public function buscarPorIdentificacion(Request $request, string $emisorId): JsonResponse
    {
        $resolvedId = $this->resolveEmisorId($emisorId);

        $request->validate([
            'identificacion' => ['required', 'string'],
        ]);

        $clienteModel = Cliente::where('emisor_id', $resolvedId)
            ->where('identificacion', $request->query('identificacion'))
            ->first();

        return response()->json(['data' => $clienteModel]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Validaciones privadas
    // ─────────────────────────────────────────────────────────────────────────

    private function buildValidator(array $data, int $emisorId, ?int $excludeId = null): \Illuminate\Validation\Validator
    {
        $uniqueRule = Rule::unique('clientes')
            ->where('emisor_id', $emisorId)
            ->where('tipo_identificacion', $data['tipo_identificacion'] ?? '');

        if ($excludeId) {
            $uniqueRule->ignore($excludeId);
        }

        $validator = Validator::make($data, [
            'tipo_identificacion' => ['required', 'string', Rule::in(self::TIPOS_IDENTIFICACION_SRI)],
            'identificacion'      => ['required', 'string', 'max:13', $uniqueRule],
            'razon_social'        => ['required', 'string', 'max:255'],
            'nombre_comercial'    => ['nullable', 'string', 'max:255'],
            'direccion'           => ['nullable', 'string', 'max:500'],
            'email'               => ['nullable', 'email', 'max:255'],
            'telefono'            => ['nullable', 'string', 'max:50'],
        ], [
            'tipo_identificacion.in' => 'El tipo de identificación debe ser uno de los permitidos por el SRI: ' . implode(', ', self::TIPOS_IDENTIFICACION_SRI),
            'identificacion.unique'  => 'Ya existe un cliente con esta identificación y tipo para este emisor.',
        ]);

        $validator->after(function ($v) use ($data) {
            $tipo = strtoupper((string) ($data['tipo_identificacion'] ?? ''));
            $id   = (string) ($data['identificacion'] ?? '');

            if ($tipo === 'CEDULA') {
                if (!EcuadorIdentificationValidator::validateCedula($id)) {
                    $v->errors()->add('identificacion', 'Cédula no válida según las reglas del Registro Civil.');
                }
            } elseif ($tipo === 'RUC') {
                if (!EcuadorIdentificationValidator::validateRuc($id)) {
                    $v->errors()->add('identificacion', 'RUC no válido según las reglas del SRI.');
                }
            } elseif ($tipo === 'CONSUMIDOR_FINAL') {
                if ($id !== '9999999999999') {
                    $v->errors()->add('identificacion', 'Consumidor Final debe usar 9999999999999.');
                }
            }
        });

        return $validator;
    }
}

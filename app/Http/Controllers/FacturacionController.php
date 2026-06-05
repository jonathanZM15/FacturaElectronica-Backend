<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Comprobante;
use App\Models\ComprobanteDetalle;
use App\Models\ComprobanteImpuesto;
use App\Models\Establecimiento;
use App\Models\PuntoEmision;
use App\Models\TipoImpuesto;
use App\Services\EcuadorIdentificationValidator;
use App\Services\FacturaCalculatorService;
use App\Services\SriSoapService;
use App\Services\SriSignatureService;
use App\Services\SriXmlGeneratorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class FacturacionController extends Controller
{
    public function __construct(
        private readonly FacturaCalculatorService $calculator,
        private readonly SriXmlGeneratorService $xmlService,
        private readonly SriSignatureService $signatureService,
        private readonly SriSoapService $soapService
    ) {
    }

    public function emitirFactura(Request $request): JsonResponse
    {
        $rules = [
            'emisor_id' => ['required', 'integer', 'exists:emisores,id'],
            'establecimiento_id' => ['required', 'integer', 'exists:establecimientos,id'],
            'punto_emision_id' => ['required', 'integer', 'exists:puntos_emision,id'],
            'cliente.tipo_identificacion' => ['required', 'string'],
            'cliente.identificacion' => ['required', 'string'],
            'cliente.razon_social' => ['required', 'string', 'max:255'],
            'cliente.direccion' => ['required', 'string', 'max:500'],
            'cliente.email' => ['required', 'email', 'max:255'],
            'cliente.telefono' => ['nullable', 'string', 'max:50'],
            'detalles' => ['required', 'array', 'min:1'],
            'detalles.*.descripcion' => ['required', 'string', 'max:500'],
            'detalles.*.cantidad' => ['required', 'numeric', 'min:0.000001'],
            'detalles.*.precio_unitario' => ['required', 'numeric', 'min:0'],
            'detalles.*.descuento' => ['nullable', 'numeric', 'min:0'],
            'detalles.*.impuesto.tipo_impuesto_id' => ['nullable', 'integer', 'exists:tipos_impuesto,id'],
            'detalles.*.impuesto.tarifa' => ['nullable', 'numeric', 'min:0'],
            'detalles.*.impuesto.tipo' => ['nullable', 'string'],
            'detalles.*.impuesto.codigo_porcentaje' => ['nullable', 'numeric'],
            'detalles.*.impuesto.codigo_impuesto' => ['nullable', 'numeric'],
            'detalles.*.impuesto.codigo' => ['nullable', 'numeric'],
        ];

        $validator = Validator::make($request->all(), $rules);
        $validator->after(function ($v) use ($request) {
            $cliente = $request->input('cliente', []);
            $tipo = strtoupper((string) ($cliente['tipo_identificacion'] ?? ''));
            $id = (string) ($cliente['identificacion'] ?? '');

            if ($tipo === 'RUC') {
                if (!EcuadorIdentificationValidator::validateRuc($id)) {
                    $v->errors()->add('cliente.identificacion', 'RUC no valido segun reglas del SRI.');
                }
                return;
            }

            if ($tipo === 'CEDULA') {
                if (!EcuadorIdentificationValidator::validateCedula($id)) {
                    $v->errors()->add('cliente.identificacion', 'Cedula no valida segun reglas del Registro Civil.');
                }
                return;
            }

            if ($tipo === 'CONSUMIDOR_FINAL') {
                if ($id !== '9999999999999') {
                    $v->errors()->add('cliente.identificacion', 'Consumidor final debe usar 9999999999999.');
                }
                return;
            }
        });

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation error', 'errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();
        $detalles = $this->resolverImpuestosDetalle($data['detalles']);
        if ($detalles === null) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => ['detalles' => ['No se pudo resolver el tipo de impuesto para uno o mas detalles.']],
            ], 422);
        }
        $data['detalles'] = $detalles;
        $emisorId = (int) $data['emisor_id'];
        $establecimientoId = (int) $data['establecimiento_id'];
        $puntoEmisionId = (int) $data['punto_emision_id'];

        $transactionResult = DB::transaction(function () use ($data, $detalles, $emisorId, $establecimientoId, $puntoEmisionId) {
            $calculo = $this->calculator->calcularComprobante($detalles);

            $clienteData = $data['cliente'];
            $cliente = Cliente::firstOrCreate(
                [
                    'emisor_id' => $emisorId,
                    'tipo_identificacion' => $clienteData['tipo_identificacion'],
                    'identificacion' => $clienteData['identificacion'],
                ],
                [
                    'razon_social' => $clienteData['razon_social'],
                    'nombre_comercial' => $clienteData['razon_social'],
                    'direccion' => $clienteData['direccion'],
                    'email' => $clienteData['email'],
                    'telefono' => $clienteData['telefono'] ?? null,
                    'created_by' => Auth::id(),
                    'updated_by' => Auth::id(),
                ]
            );

            $cliente->fill([
                'razon_social' => $clienteData['razon_social'],
                'direccion' => $clienteData['direccion'],
                'email' => $clienteData['email'],
                'telefono' => $clienteData['telefono'] ?? null,
                'updated_by' => Auth::id(),
            ]);
            $cliente->save();

            $establecimiento = Establecimiento::where('emisor_id', $emisorId)->findOrFail($establecimientoId);
            $punto = PuntoEmision::where('emisor_id', $emisorId)
                ->where('establecimiento_id', $establecimientoId)
                ->findOrFail($puntoEmisionId);

            $secuencialData = $punto->nextSecuencialFactura();
            $subtotales = $this->buildSubtotales($calculo['detalles']);

            $comprobante = Comprobante::create([
                'emisor_id' => $emisorId,
                'establecimiento_id' => $establecimientoId,
                'punto_emision_id' => $puntoEmisionId,
                'cliente_id' => $cliente->id,
                'tipo_comprobante' => 'FACTURA',
                'secuencial' => $secuencialData['secuencial'],
                'secuencial_formateado' => $secuencialData['secuencial_formateado'],
                'codigo_establecimiento' => $establecimiento->codigo,
                'punto_emision_codigo' => $punto->codigo,
                'fecha_emision' => now()->toDateString(),
                'subtotal_sin_impuestos' => $calculo['totales']['subtotal_sin_impuestos'],
                'subtotal_iva_0' => $subtotales['subtotal_iva_0'],
                'subtotal_iva' => $subtotales['subtotal_iva'],
                'subtotal_no_objeto' => $subtotales['subtotal_no_objeto'],
                'subtotal_exento' => $subtotales['subtotal_exento'],
                'total_descuento' => $calculo['totales']['total_descuento'],
                'total_iva' => $calculo['totales']['total_iva'],
                'total_impuestos' => $calculo['totales']['total_iva'],
                'total' => $calculo['totales']['importe_total'],
                'estado_sri' => 'CREADA',
                'ambiente' => 'PRUEBAS',
                'tipo_emision' => 'NORMAL',
            ]);

            foreach ($calculo['detalles'] as $detalle) {
                $detalleModel = ComprobanteDetalle::create([
                    'comprobante_id' => $comprobante->id,
                    'producto_id' => $detalle['producto_id'] ?? null,
                    'descripcion' => $detalle['descripcion'],
                    'cantidad' => $detalle['cantidad'],
                    'precio_unitario' => $detalle['precio_unitario'],
                    'descuento' => $detalle['descuento'] ?? 0,
                    'subtotal' => $detalle['precio_total_sin_impuesto'],
                ]);

                $impuesto = $detalle['impuesto'] ?? null;
                if ($impuesto) {
                    $tarifa = (float) ($impuesto['tarifa'] ?? 0);
                    $valor = round($detalle['precio_total_sin_impuesto'] * ($tarifa / 100), 2, PHP_ROUND_HALF_UP);

                    ComprobanteImpuesto::create([
                        'comprobante_id' => $comprobante->id,
                        'comprobante_detalle_id' => $detalleModel->id,
                        'tipo_impuesto_id' => $impuesto['tipo_impuesto_id'] ?? null,
                        'base_imponible' => $detalle['precio_total_sin_impuesto'],
                        'tarifa' => $tarifa,
                        'valor' => $valor,
                    ]);
                }
            }

            foreach ($calculo['impuestos'] as $impuesto) {
                ComprobanteImpuesto::create([
                    'comprobante_id' => $comprobante->id,
                    'comprobante_detalle_id' => null,
                    'tipo_impuesto_id' => $impuesto['tipo_impuesto_id'] ?? null,
                    'base_imponible' => $impuesto['base_imponible'],
                    'tarifa' => $impuesto['tarifa'],
                    'valor' => $impuesto['valor'],
                ]);
            }

            $comprobante->load([
                'company',
                'cliente',
                'establecimiento',
                'detalles.impuestos.tipoImpuesto',
                'impuestos.tipoImpuesto',
            ]);

            $xmlData = $this->xmlService->generarXmlFactura($comprobante);
            $comprobante->clave_acceso = $xmlData['clave_acceso'];
            $comprobante->save();

            $xmlFirmado = $this->signatureService->firmarXml(
                $xmlData['xml'],
                env('SRI_FIRMA_PATH', ''),
                env('SRI_FIRMA_PASSWORD', '')
            );

            $comprobante->estado_sri = 'FIRMADA';
            $comprobante->save();

            return [
                'comprobante' => $comprobante,
                'xml_firmado' => $xmlFirmado,
            ];
        });

        /** @var Comprobante $comprobante */
        $comprobante = $transactionResult['comprobante'];
        $xmlFirmado = $transactionResult['xml_firmado'];

        $recepcion = $this->soapService->enviarComprobante($xmlFirmado);
        if (!($recepcion['success'] ?? false) || ($recepcion['estado'] ?? '') !== 'RECIBIDA') {
            $comprobante->estado_sri = 'RECHAZADA';
            $comprobante->save();

            return response()->json([
                'success' => false,
                'estado' => $recepcion['estado'] ?? 'RECHAZADA',
                'errores' => $recepcion['errores'] ?? [],
            ], 422);
        }

        $comprobante->estado_sri = 'ENVIADA';
        $comprobante->save();

        $autorizacion = $this->soapService->autorizarComprobante($comprobante->clave_acceso);
        if ($autorizacion['success'] ?? false) {
            $comprobante->estado_sri = 'AUTORIZADA';
            $comprobante->numero_autorizacion = $autorizacion['numero_autorizacion'] ?? $comprobante->clave_acceso;
            $comprobante->fecha_autorizacion = $autorizacion['fecha_autorizacion'] ?? null;
            $comprobante->xml_autorizado = $autorizacion['xml_autorizado'] ?? null;
            $comprobante->save();

            return response()->json([
                'success' => true,
                'estado' => 'AUTORIZADA',
                'clave_acceso' => $comprobante->clave_acceso,
                'numero_autorizacion' => $comprobante->numero_autorizacion,
                'fecha_autorizacion' => $comprobante->fecha_autorizacion,
            ]);
        }

        $comprobante->estado_sri = $autorizacion['estado'] ?? 'DEVUELTA';
        $comprobante->save();

        return response()->json([
            'success' => false,
            'estado' => $autorizacion['estado'] ?? 'DEVUELTA',
            'errores' => $autorizacion['errores'] ?? [],
        ], 422);
    }

    private function buildSubtotales(array $detalles): array
    {
        $subtotalIva0 = 0.0;
        $subtotalIva = 0.0;
        $subtotalNoObjeto = 0.0;
        $subtotalExento = 0.0;

        foreach ($detalles as $detalle) {
            $base = (float) ($detalle['precio_total_sin_impuesto'] ?? 0);
            $impuesto = $detalle['impuesto'] ?? null;

            if (!$impuesto) {
                $subtotalNoObjeto += $base;
                continue;
            }

            $tipo = strtoupper((string) ($impuesto['tipo'] ?? ''));
            if (str_contains($tipo, 'NO OBJETO') || str_contains($tipo, 'NO_OBJETO')) {
                $subtotalNoObjeto += $base;
                continue;
            }
            if (str_contains($tipo, 'EXENTO')) {
                $subtotalExento += $base;
                continue;
            }

            $tarifa = (float) ($impuesto['tarifa'] ?? 0);
            if (abs($tarifa - 0.0) < 0.00001) {
                $subtotalIva0 += $base;
            } else {
                $subtotalIva += $base;
            }
        }

        return [
            'subtotal_iva_0' => round($subtotalIva0, 2, PHP_ROUND_HALF_UP),
            'subtotal_iva' => round($subtotalIva, 2, PHP_ROUND_HALF_UP),
            'subtotal_no_objeto' => round($subtotalNoObjeto, 2, PHP_ROUND_HALF_UP),
            'subtotal_exento' => round($subtotalExento, 2, PHP_ROUND_HALF_UP),
        ];
    }

    private function resolverImpuestosDetalle(array $detalles): ?array
    {
        foreach ($detalles as $index => $detalle) {
            $impuesto = $detalle['impuesto'] ?? null;
            if (!$impuesto) {
                continue;
            }

            if (!empty($impuesto['tipo_impuesto_id'])) {
                $detalles[$index]['impuesto']['tipo_impuesto_id'] = (int) $impuesto['tipo_impuesto_id'];
                continue;
            }

            $tipo = strtoupper((string) ($impuesto['tipo'] ?? ''));
            $codigoPorcentaje = $impuesto['codigo_porcentaje'] ?? $impuesto['codigo'] ?? null;
            $codigoImpuesto = $impuesto['codigo_impuesto'] ?? null;
            $tarifa = $impuesto['tarifa'] ?? null;

            $query = TipoImpuesto::query()->where('estado', 'Activo');

            if ($tipo !== '') {
                $query->where('tipo_impuesto', $tipo);
            }

            if ($codigoImpuesto !== null && $codigoImpuesto !== '') {
                $query->where('codigo_impuesto', (int) $codigoImpuesto);
            }

            if ($codigoPorcentaje !== null && $codigoPorcentaje !== '') {
                $query->where('codigo_porcentaje', (int) $codigoPorcentaje);
            }

            if ($tarifa !== null && $tarifa !== '') {
                $query->where('valor_tarifa', (float) $tarifa);
            }

            $tipoImpuesto = $query->first();
            if (!$tipoImpuesto) {
                return null;
            }

            $detalles[$index]['impuesto']['tipo_impuesto_id'] = $tipoImpuesto->id;
        }

        return $detalles;
    }
}

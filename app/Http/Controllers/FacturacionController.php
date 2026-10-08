<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Company;
use App\Models\Comprobante;
use App\Models\ComprobanteDetalle;
use App\Models\ComprobanteImpuesto;
use App\Models\Establecimiento;
use App\Models\PuntoEmision;
use App\Models\TipoImpuesto;
use App\Exceptions\SriFirmaException;
use App\Jobs\ProcesarFacturaSriJob;
use App\Jobs\ConsultarAutorizacionSriJob;
use Illuminate\Support\Facades\Crypt;
use App\Services\EcuadorIdentificationValidator;
use App\Services\FacturaCalculatorService;
use App\Services\SriSignatureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class FacturacionController extends Controller
{
    public function __construct(
        private readonly FacturaCalculatorService $calculator,
        private readonly SriSignatureService $signatureService
    ) {
    }


    public function emitirLiquidacionCompra(Request $request): JsonResponse
    {
        $request->validate([
            'firma' => ['required', 'file', 'extensions:p12,pfx'],
            'password' => ['required', 'string'],
            'payload' => ['required', 'json'],
        ]);

        $payloadData = json_decode($request->input('payload'), true);

        $rules = [
            'emisor_id' => ['required', 'integer', 'exists:emisores,id'],
            'establecimiento_id' => ['required', 'integer', 'exists:establecimientos,id'],
            'punto_emision_id' => ['required', 'integer', 'exists:puntos_emision,id'],
            'proveedor.tipo_identificacion' => ['required', 'string'],
            'proveedor.identificacion' => ['required', 'string'],
            'proveedor.razon_social' => ['required', 'string', 'max:255'],
            'proveedor.direccion' => ['required', 'string', 'max:500'],
            'proveedor.email' => ['required', 'email', 'max:255'],
            'proveedor.telefono' => ['nullable', 'string', 'max:50'],
            'detalles' => ['required', 'array', 'min:1'],
            'detalles.*.descripcion' => ['required', 'string', 'max:500'],
            'detalles.*.cantidad' => ['required', 'numeric', 'min:0.000001'],
            'detalles.*.precio_unitario' => ['required', 'numeric', 'min:0'],
            'detalles.*.descuento' => ['nullable', 'numeric', 'min:0'],
            'detalles.*.impuesto.tipo_impuesto_id' => ['nullable', 'integer', 'exists:tipos_impuesto,id'],
            'detalles.*.impuesto.tarifa' => ['nullable', 'numeric', 'min:0'],
        ];

        $validator = \Illuminate\Support\Facades\Validator::make($payloadData, $rules);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation error', 'errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        $archivoFirma = $request->file('firma');
        $passwordFirma = trim((string) $request->input('password'));
        $extension = strtolower($archivoFirma->getClientOriginalExtension() ?: 'p12');
        $nombreAlmacenado = uniqid('cert_', true) . '.' . $extension;
        $disk = config('sri.certificate_disk', 'local');

        $pathFirma = $archivoFirma->storeAs('sri/certificados', $nombreAlmacenado, $disk);
        $rutaAbsoluta = \Illuminate\Support\Facades\Storage::disk($disk)->path($pathFirma);

        try {
            $this->signatureService->verificarP12($rutaAbsoluta, $passwordFirma);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Storage::disk($disk)->delete($pathFirma);
            return response()->json([
                'message' => 'Firma electronica invalida o contrasena incorrecta.',
                'error' => $e->getMessage()
            ], 422);
        }

        $detalles = $data['detalles'];
        $emisorId = $data['emisor_id'];
        $establecimientoId = $data['establecimiento_id'];
        $puntoEmisionId = $data['punto_emision_id'];

        $transactionResult = \Illuminate\Support\Facades\DB::transaction(function () use ($data, $detalles, $emisorId, $establecimientoId, $puntoEmisionId) {
            $calculo = $this->calculator->calcularComprobante($detalles);

            $proveedorData = $data['proveedor'];
            $proveedor = \App\Models\Proveedor::firstOrCreate(
                [
                    'emisor_id' => $emisorId,
                    'tipo_identificacion' => $proveedorData['tipo_identificacion'],
                    'identificacion' => $proveedorData['identificacion'],
                ],
                [
                    'razon_social' => $proveedorData['razon_social'],
                    'nombre_comercial' => $proveedorData['razon_social'],
                    'direccion' => $proveedorData['direccion'],
                    'email' => $proveedorData['email'],
                    'telefono' => $proveedorData['telefono'] ?? null,
                    'created_by' => \Illuminate\Support\Facades\Auth::id(),
                    'updated_by' => \Illuminate\Support\Facades\Auth::id(),
                ]
            );

            $proveedor->fill([
                'razon_social' => $proveedorData['razon_social'],
                'direccion' => $proveedorData['direccion'],
                'email' => $proveedorData['email'],
                'telefono' => $proveedorData['telefono'] ?? null,
                'updated_by' => \Illuminate\Support\Facades\Auth::id(),
            ]);
            $proveedor->save();

            $company = \App\Models\Company::findOrFail($emisorId);
            $establecimiento = \App\Models\Establecimiento::where('emisor_id', $emisorId)->findOrFail($establecimientoId);
            $punto = \App\Models\PuntoEmision::where('emisor_id', $emisorId)
                ->where('establecimiento_id', $establecimientoId)
                ->findOrFail($puntoEmisionId);

            $secuencialData = $punto->nextSecuencialLiquidacionCompra();
            $subtotales = $this->buildSubtotales($calculo['detalles']);

            $comprobante = \App\Models\Comprobante::create([
                'emisor_id' => $emisorId,
                'establecimiento_id' => $establecimientoId,
                'punto_emision_id' => $puntoEmisionId,
                'proveedor_id' => $proveedor->id,
                'cliente_id' => null,
                'tipo_comprobante' => 'LIQUIDACION_COMPRA',
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
                'estado_sri' => 'BORRADOR',
                'ambiente' => $company->ambiente ?? 'PRUEBAS',
                'tipo_emision' => $company->tipo_emision ?? 'NORMAL',
            ]);

            foreach ($calculo['detalles'] as $detalle) {
                $detalleModel = \App\Models\ComprobanteDetalle::create([
                    'comprobante_id' => $comprobante->id,
                    'producto_id' => $detalle['producto_id'] ?? null,
                    'descripcion' => $detalle['descripcion'],
                    'cantidad' => $detalle['cantidad'],
                    'precio_unitario' => $detalle['precio_unitario'],
                    'descuento' => $detalle['descuento'] ?? 0,
                    'subtotal' => $detalle['precio_total_sin_impuesto'],
                ]);

                // INVENTARIO: INGRESAR MERCADERÍA
                if (!empty($detalle['producto_id'])) {
                    $producto = \App\Models\Producto::find($detalle['producto_id']);
                    $bodega = \App\Models\Bodega::where('establecimiento_id', $establecimientoId)
                                    ->where('tipo', 'PRINCIPAL')
                                    ->first();
                    if (!$bodega) {
                        $bodega = \App\Models\Bodega::where('establecimiento_id', $establecimientoId)->first();
                    }
                    if ($bodega && $producto) {
                        $stockRow = \App\Models\ProductoBodegaStock::where('producto_id', $producto->id)
                                        ->where('bodega_id', $bodega->id)
                                        ->lockForUpdate()
                                        ->first();
                        
                        if ($stockRow) {
                            $stockRow->increment('stock_actual', $detalle['cantidad']);
                            $saldo = $stockRow->fresh()->stock_actual;
                        } else {
                            \App\Models\ProductoBodegaStock::create([
                                'producto_id' => $producto->id,
                                'bodega_id' => $bodega->id,
                                'stock_actual' => $detalle['cantidad']
                            ]);
                            $saldo = $detalle['cantidad'];
                        }

                        \App\Models\Kardex::create([
                            'fecha_hora' => now(),
                            'producto_id' => $producto->id,
                            'bodega_id' => $bodega->id,
                            'tipo_movimiento' => \App\Enums\TipoMovimientoInventario::MOV_02_COMPRA,
                            'documento_origen_tipo' => 'Comprobante',
                            'documento_origen_id' => $comprobante->id,
                            'numero_documento' => $comprobante->secuencial_formateado,
                            'entrada' => $detalle['cantidad'],
                            'salida' => 0,
                            'saldo' => $saldo,
                            'usuario_id' => \Illuminate\Support\Facades\Auth::id() ?? 1,
                        ]);
                    }
                }
                
                $impuesto = $detalle['impuesto'] ?? null;
                if ($impuesto) {
                    $tarifa = (float) ($impuesto['tarifa'] ?? 0);
                    $valor = round($detalle['precio_total_sin_impuesto'] * ($tarifa / 100), 2, PHP_ROUND_HALF_UP);

                    \App\Models\ComprobanteImpuesto::create([
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
                \App\Models\ComprobanteImpuesto::create([
                    'comprobante_id' => $comprobante->id,
                    'comprobante_detalle_id' => null,
                    'tipo_impuesto_id' => $impuesto['tipo_impuesto_id'] ?? null,
                    'base_imponible' => $impuesto['base_imponible'],
                    'tarifa' => $impuesto['tarifa'],
                    'valor' => $impuesto['valor'],
                ]);
            }

            return [
                'comprobante_id' => $comprobante->id,
                'secuencial' => $secuencialData['secuencial'],
                'secuencial_formateado' => $secuencialData['secuencial_formateado'],
            ];
        });

        \App\Jobs\ProcesarFacturaSriJob::dispatch(
            $transactionResult['comprobante_id'],
            $pathFirma,
            \Illuminate\Support\Facades\Crypt::encryptString($passwordFirma)
        )->afterCommit();

        return response()->json([
            'message' => 'Liquidación de compra generada y enviada a procesamiento SRI exitosamente.',
            'comprobante_id' => $transactionResult['comprobante_id'],
            'secuencial' => $transactionResult['secuencial_formateado']
        ]);
    }

    public function emitirFactura(Request $request): JsonResponse
    {
        $request->validate([
            'firma' => ['required', 'file', 'extensions:p12,pfx'],
            'password' => ['required', 'string'],
            'payload' => ['required', 'json'],
        ]);

        $payloadData = json_decode($request->input('payload'), true);

        $rules = [
            'emisor_id' => ['required', 'integer', 'exists:emisores,id'],
            'establecimiento_id' => ['required', 'integer', 'exists:establecimientos,id'],
            'punto_emision_id' => ['required', 'integer', 'exists:puntos_emision,id'],
            'pos_turno_id' => ['nullable', 'integer', 'exists:pos_turnos,id'],
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

        $validator = Validator::make($payloadData, $rules);
        $validator->after(function ($v) use ($payloadData) {
            $cliente = $payloadData['cliente'] ?? [];
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

        $archivoFirma = $request->file('firma');
        $passwordFirma = trim((string) $request->input('password'));
        $extension = strtolower($archivoFirma->getClientOriginalExtension() ?: 'p12');
        $nombreAlmacenado = uniqid('cert_', true) . '.' . $extension;
        $disk = config('sri.certificate_disk', 'local');

        Log::info('Recibiendo certificado P12 para emision SRI.', [
            'nombre_original' => $archivoFirma->getClientOriginalName(),
            'extension' => $extension,
            'mime' => $archivoFirma->getMimeType(),
            'tamanio_bytes' => $archivoFirma->getSize(),
            'longitud_clave' => strlen($passwordFirma),
        ]);

        $pathFirma = $archivoFirma->storeAs('sri/certificados', $nombreAlmacenado, $disk);
        $rutaAbsoluta = Storage::disk($disk)->path($pathFirma);

        Log::info('Certificado P12 almacenado temporalmente.', [
            'path_relativo' => $pathFirma,
            'ruta_absoluta' => $rutaAbsoluta,
            'bytes_en_disco' => is_file($rutaAbsoluta) ? filesize($rutaAbsoluta) : null,
        ]);

        try {
            $this->signatureService->verificarP12($rutaAbsoluta, $passwordFirma);
        } catch (SriFirmaException $e) {
            Storage::disk($disk)->delete($pathFirma);

            Log::error('Validacion temprana de P12 fallida.', [
                'path' => $pathFirma,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => $e->getMessage(),
                'errors' => ['firma' => [$e->getMessage()]],
            ], 422);
        }
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

            $company = Company::findOrFail($emisorId);
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
                'pos_turno_id' => $data['pos_turno_id'] ?? null,
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
                'estado_sri' => 'BORRADOR',
                'ambiente' => $company->ambiente ?? 'PRUEBAS',
                'tipo_emision' => $company->tipo_emision ?? 'NORMAL',
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

                if ($comprobante->tipo_comprobante === 'FACTURA' && !empty($detalle['producto_id'])) {
                    $producto = \App\Models\Producto::find($detalle['producto_id']);
                    $bodega = \App\Models\Bodega::where('establecimiento_id', $establecimientoId)
                                    ->where('tipo', 'PRINCIPAL')
                                    ->first();
                    if (!$bodega) {
                        $bodega = \App\Models\Bodega::where('establecimiento_id', $establecimientoId)->first();
                    }
                    if ($bodega && $producto) {
                        $stockRow = \App\Models\ProductoBodegaStock::where('producto_id', $producto->id)
                                        ->where('bodega_id', $bodega->id)
                                        ->lockForUpdate()
                                        ->first();
                        
                        if ($stockRow) {
                            $stockRow->decrement('stock_actual', $detalle['cantidad']);
                            $saldo = $stockRow->fresh()->stock_actual;
                        } else {
                            $saldo = 0;
                        }

                        \App\Models\Kardex::create([
                            'fecha_hora' => now(),
                            'producto_id' => $producto->id,
                            'bodega_id' => $bodega->id,
                            'tipo_movimiento' => \App\Enums\TipoMovimientoInventario::MOV_03_VENTA_INMEDIATA,
                            'documento_origen_tipo' => 'Comprobante',
                            'documento_origen_id' => $comprobante->id,
                            'numero_documento' => $comprobante->secuencial_formateado,
                            'entrada' => 0,
                            'salida' => $detalle['cantidad'],
                            'saldo' => $saldo,
                            'usuario_id' => \Illuminate\Support\Facades\Auth::id() ?? 1,
                        ]);
                    }
                }
                
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

            return [
                'comprobante_id' => $comprobante->id,
                'secuencial' => $secuencialData['secuencial'],
                'secuencial_formateado' => $secuencialData['secuencial_formateado'],
            ];
        });

        ProcesarFacturaSriJob::dispatch(
            $transactionResult['comprobante_id'],
            $pathFirma,
            Crypt::encryptString($passwordFirma)
        )->afterCommit();

        return response()->json([
            'success' => true,
            'estado' => 'PROCESANDO',
            'comprobante_id' => $transactionResult['comprobante_id'],
            'secuencial' => $transactionResult['secuencial'],
            'secuencial_formateado' => $transactionResult['secuencial_formateado'],
        ], 202);
    }


    public function emitirGuiaRemision(Request $request): JsonResponse
    {
        $request->validate([
            'firma' => ['required', 'file', 'extensions:p12,pfx'],
            'password' => ['required', 'string'],
            'payload' => ['required', 'json'],
        ]);

        $payloadData = json_decode($request->input('payload'), true);

        $rules = [
            'emisor_id' => ['required', 'integer', 'exists:emisores,id'],
            'establecimiento_id' => ['required', 'integer', 'exists:establecimientos,id'],
            'punto_emision_id' => ['required', 'integer', 'exists:puntos_emision,id'],
            'pos_turno_id' => ['nullable', 'integer', 'exists:pos_turnos,id'],
            
            // Cliente (Destinatario)
            'cliente.tipo_identificacion' => ['required', 'string'],
            'cliente.identificacion' => ['required', 'string'],
            'cliente.razon_social' => ['required', 'string', 'max:255'],
            'cliente.direccion' => ['required', 'string', 'max:500'],
            'cliente.email' => ['nullable', 'email', 'max:255'],
            
            // Datos propios de la Guia de Remision
            'guia_remision_data' => ['required', 'array'],
            'guia_remision_data.direccion_partida' => ['required', 'string', 'max:300'],
            'guia_remision_data.transportista_nombre' => ['required', 'string', 'max:300'],
            'guia_remision_data.transportista_identificacion' => ['required', 'string', 'max:20'],
            'guia_remision_data.placa_vehiculo' => ['required', 'string', 'max:20'],
            'guia_remision_data.fecha_inicio_transporte' => ['required', 'date'],
            'guia_remision_data.fecha_fin_transporte' => ['required', 'date'],
            'guia_remision_data.motivo_traslado' => ['required', 'string', 'max:300'],
            'guia_remision_data.direccion_destino' => ['required', 'string', 'max:300'],
            'guia_remision_data.ruta' => ['nullable', 'string', 'max:300'],
            
            // Sustento (Opcional, puede ir sin factura de sustento)
            'comprobante_modificado_id' => ['nullable', 'integer', 'exists:comprobantes,id'],
            
            // Detalles a transportar
            'detalles' => ['required', 'array', 'min:1'],
            'detalles.*.producto_id' => ['nullable', 'integer'],
            'detalles.*.descripcion' => ['required', 'string', 'max:500'],
            'detalles.*.cantidad' => ['required', 'numeric', 'min:0.000001'],
        ];

        $validator = Validator::make($payloadData, $rules);

        if ($validator->fails()) {
            return response()->json(['message' => 'Validation error', 'errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        $archivoFirma = $request->file('firma');
        $passwordFirma = trim((string) $request->input('password'));
        $extension = strtolower($archivoFirma->getClientOriginalExtension() ?: 'p12');
        $nombreAlmacenado = uniqid('cert_', true) . '.' . $extension;
        $disk = config('sri.certificate_disk', 'local');

        $pathFirma = $archivoFirma->storeAs('sri/certificados', $nombreAlmacenado, $disk);
        $rutaAbsoluta = Storage::disk($disk)->path($pathFirma);

        try {
            $this->signatureService->verificarP12($rutaAbsoluta, $passwordFirma);
        } catch (SriFirmaException $e) {
            Storage::disk($disk)->delete($pathFirma);
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => ['firma' => [$e->getMessage()]],
            ], 422);
        }

        $emisorId = (int) $data['emisor_id'];
        $establecimientoId = (int) $data['establecimiento_id'];
        $puntoEmisionId = (int) $data['punto_emision_id'];

        $transactionResult = DB::transaction(function () use ($data, $emisorId, $establecimientoId, $puntoEmisionId) {
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
                    'email' => $clienteData['email'] ?? null,
                    'created_by' => Auth::id(),
                    'updated_by' => Auth::id(),
                ]
            );

            $company = Company::findOrFail($emisorId);
            $establecimiento = Establecimiento::where('emisor_id', $emisorId)->findOrFail($establecimientoId);
            $punto = PuntoEmision::where('emisor_id', $emisorId)
                ->where('establecimiento_id', $establecimientoId)
                ->findOrFail($puntoEmisionId);

            $secuencialData = $punto->nextSecuencialGuiaRemision();

            $comprobante = Comprobante::create([
                'emisor_id' => $emisorId,
                'establecimiento_id' => $establecimientoId,
                'punto_emision_id' => $puntoEmisionId,
                'pos_turno_id' => $data['pos_turno_id'] ?? null,
                'cliente_id' => $cliente->id,
                'tipo_comprobante' => 'GUIA_REMISION',
                'comprobante_modificado_id' => $data['comprobante_modificado_id'] ?? null,
                'guia_remision_data' => $data['guia_remision_data'],
                'secuencial' => $secuencialData['secuencial'],
                'secuencial_formateado' => $secuencialData['secuencial_formateado'],
                'codigo_establecimiento' => $establecimiento->codigo,
                'punto_emision_codigo' => $punto->codigo,
                'fecha_emision' => now()->toDateString(),
                'subtotal_sin_impuestos' => 0,
                'subtotal_iva_0' => 0,
                'subtotal_iva' => 0,
                'subtotal_no_objeto' => 0,
                'subtotal_exento' => 0,
                'total_descuento' => 0,
                'total_iva' => 0,
                'total_impuestos' => 0,
                'total' => 0,
                'estado_sri' => 'BORRADOR',
                'ambiente' => $company->ambiente ?? 'PRUEBAS',
                'tipo_emision' => $company->tipo_emision ?? 'NORMAL',
            ]);

            foreach ($data['detalles'] as $detalle) {
                ComprobanteDetalle::create([
                    'comprobante_id' => $comprobante->id,
                    'producto_id' => $detalle['producto_id'] ?? null,
                    'descripcion' => $detalle['descripcion'],
                    'cantidad' => $detalle['cantidad'],
                    'precio_unitario' => 0,
                    'descuento' => 0,
                    'subtotal' => 0,
                ]);
            }

            return [
                'comprobante_id' => $comprobante->id,
                'secuencial' => $secuencialData['secuencial'],
                'secuencial_formateado' => $secuencialData['secuencial_formateado'],
            ];
        });

        ProcesarFacturaSriJob::dispatch(
            $transactionResult['comprobante_id'],
            $pathFirma,
            Crypt::encryptString($passwordFirma)
        )->afterCommit();

        return response()->json([
            'success' => true,
            'estado' => 'PROCESANDO',
            'comprobante_id' => $transactionResult['comprobante_id'],
            'secuencial' => $transactionResult['secuencial'],
            'secuencial_formateado' => $transactionResult['secuencial_formateado'],
        ], 202);
    }

    public function emitirNotaCredito(Request $request): JsonResponse
    {
        $request->validate([
            'firma' => ['required', 'file', 'extensions:p12,pfx'],
            'password' => ['required', 'string'],
            'payload' => ['required', 'json'],
        ]);

        $payloadData = json_decode($request->input('payload'), true);

        $rules = [
            'emisor_id' => ['required', 'integer', 'exists:emisores,id'],
            'establecimiento_id' => ['required', 'integer', 'exists:establecimientos,id'],
            'punto_emision_id' => ['required', 'integer', 'exists:puntos_emision,id'],
            'pos_turno_id' => ['nullable', 'integer', 'exists:pos_turnos,id'],
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

        $validator = Validator::make($payloadData, $rules);
        $validator->after(function ($v) use ($payloadData) {
            $cliente = $payloadData['cliente'] ?? [];
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

        $archivoFirma = $request->file('firma');
        $passwordFirma = trim((string) $request->input('password'));
        $extension = strtolower($archivoFirma->getClientOriginalExtension() ?: 'p12');
        $nombreAlmacenado = uniqid('cert_', true) . '.' . $extension;
        $disk = config('sri.certificate_disk', 'local');

        Log::info('Recibiendo certificado P12 para emision SRI.', [
            'nombre_original' => $archivoFirma->getClientOriginalName(),
            'extension' => $extension,
            'mime' => $archivoFirma->getMimeType(),
            'tamanio_bytes' => $archivoFirma->getSize(),
            'longitud_clave' => strlen($passwordFirma),
        ]);

        $pathFirma = $archivoFirma->storeAs('sri/certificados', $nombreAlmacenado, $disk);
        $rutaAbsoluta = Storage::disk($disk)->path($pathFirma);

        Log::info('Certificado P12 almacenado temporalmente.', [
            'path_relativo' => $pathFirma,
            'ruta_absoluta' => $rutaAbsoluta,
            'bytes_en_disco' => is_file($rutaAbsoluta) ? filesize($rutaAbsoluta) : null,
        ]);

        try {
            $this->signatureService->verificarP12($rutaAbsoluta, $passwordFirma);
        } catch (SriFirmaException $e) {
            Storage::disk($disk)->delete($pathFirma);

            Log::error('Validacion temprana de P12 fallida.', [
                'path' => $pathFirma,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => $e->getMessage(),
                'errors' => ['firma' => [$e->getMessage()]],
            ], 422);
        }
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

            $company = Company::findOrFail($emisorId);
            $establecimiento = Establecimiento::where('emisor_id', $emisorId)->findOrFail($establecimientoId);
            $punto = PuntoEmision::where('emisor_id', $emisorId)
                ->where('establecimiento_id', $establecimientoId)
                ->findOrFail($puntoEmisionId);

            $secuencialData = $punto->nextSecuencialNotaCredito();
            $subtotales = $this->buildSubtotales($calculo['detalles']);

            $comprobante = Comprobante::create([
                'emisor_id' => $emisorId,
                'establecimiento_id' => $establecimientoId,
                'punto_emision_id' => $puntoEmisionId,
                'pos_turno_id' => $data['pos_turno_id'] ?? null,
                'cliente_id' => $cliente->id,
                'tipo_comprobante' => 'NOTA_CREDITO',
                'comprobante_modificado_id' => $data['comprobante_modificado_id'],
                'motivo_modificacion' => $data['motivo_modificacion'],
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
                'estado_sri' => 'BORRADOR',
                'ambiente' => $company->ambiente ?? 'PRUEBAS',
                'tipo_emision' => $company->tipo_emision ?? 'NORMAL',
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

                if ($comprobante->tipo_comprobante === 'FACTURA' && !empty($detalle['producto_id'])) {
                    $producto = \App\Models\Producto::find($detalle['producto_id']);
                    $bodega = \App\Models\Bodega::where('establecimiento_id', $establecimientoId)
                                    ->where('tipo', 'PRINCIPAL')
                                    ->first();
                    if (!$bodega) {
                        $bodega = \App\Models\Bodega::where('establecimiento_id', $establecimientoId)->first();
                    }
                    if ($bodega && $producto) {
                        $stockRow = \App\Models\ProductoBodegaStock::where('producto_id', $producto->id)
                                        ->where('bodega_id', $bodega->id)
                                        ->lockForUpdate()
                                        ->first();
                        
                        if ($stockRow) {
                            $stockRow->decrement('stock_actual', $detalle['cantidad']);
                            $saldo = $stockRow->fresh()->stock_actual;
                        } else {
                            $saldo = 0;
                        }

                        \App\Models\Kardex::create([
                            'fecha_hora' => now(),
                            'producto_id' => $producto->id,
                            'bodega_id' => $bodega->id,
                            'tipo_movimiento' => \App\Enums\TipoMovimientoInventario::MOV_03_VENTA_INMEDIATA,
                            'documento_origen_tipo' => 'Comprobante',
                            'documento_origen_id' => $comprobante->id,
                            'numero_documento' => $comprobante->secuencial_formateado,
                            'entrada' => 0,
                            'salida' => $detalle['cantidad'],
                            'saldo' => $saldo,
                            'usuario_id' => \Illuminate\Support\Facades\Auth::id() ?? 1,
                        ]);
                    }
                }
                
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

            return [
                'comprobante_id' => $comprobante->id,
                'secuencial' => $secuencialData['secuencial'],
                'secuencial_formateado' => $secuencialData['secuencial_formateado'],
            ];
        });

        ProcesarFacturaSriJob::dispatch(
            $transactionResult['comprobante_id'],
            $pathFirma,
            Crypt::encryptString($passwordFirma)
        )->afterCommit();

        return response()->json([
            'success' => true,
            'estado' => 'PROCESANDO',
            'comprobante_id' => $transactionResult['comprobante_id'],
            'secuencial' => $transactionResult['secuencial'],
            'secuencial_formateado' => $transactionResult['secuencial_formateado'],
        ], 202);
    }

    public function emitirNotaDebito(Request $request): JsonResponse
    {
        $request->validate([
            'firma' => ['required', 'file', 'extensions:p12,pfx'],
            'password' => ['required', 'string'],
            'payload' => ['required', 'json'],
        ]);

        $payloadData = json_decode($request->input('payload'), true);

        $rules = [
            'emisor_id' => ['required', 'integer', 'exists:emisores,id'],
            'establecimiento_id' => ['required', 'integer', 'exists:establecimientos,id'],
            'punto_emision_id' => ['required', 'integer', 'exists:puntos_emision,id'],
            'pos_turno_id' => ['nullable', 'integer', 'exists:pos_turnos,id'],
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

        $validator = Validator::make($payloadData, $rules);
        $validator->after(function ($v) use ($payloadData) {
            $cliente = $payloadData['cliente'] ?? [];
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

        $archivoFirma = $request->file('firma');
        $passwordFirma = trim((string) $request->input('password'));
        $extension = strtolower($archivoFirma->getClientOriginalExtension() ?: 'p12');
        $nombreAlmacenado = uniqid('cert_', true) . '.' . $extension;
        $disk = config('sri.certificate_disk', 'local');

        Log::info('Recibiendo certificado P12 para emision SRI.', [
            'nombre_original' => $archivoFirma->getClientOriginalName(),
            'extension' => $extension,
            'mime' => $archivoFirma->getMimeType(),
            'tamanio_bytes' => $archivoFirma->getSize(),
            'longitud_clave' => strlen($passwordFirma),
        ]);

        $pathFirma = $archivoFirma->storeAs('sri/certificados', $nombreAlmacenado, $disk);
        $rutaAbsoluta = Storage::disk($disk)->path($pathFirma);

        Log::info('Certificado P12 almacenado temporalmente.', [
            'path_relativo' => $pathFirma,
            'ruta_absoluta' => $rutaAbsoluta,
            'bytes_en_disco' => is_file($rutaAbsoluta) ? filesize($rutaAbsoluta) : null,
        ]);

        try {
            $this->signatureService->verificarP12($rutaAbsoluta, $passwordFirma);
        } catch (SriFirmaException $e) {
            Storage::disk($disk)->delete($pathFirma);

            Log::error('Validacion temprana de P12 fallida.', [
                'path' => $pathFirma,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => $e->getMessage(),
                'errors' => ['firma' => [$e->getMessage()]],
            ], 422);
        }
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

            $company = Company::findOrFail($emisorId);
            $establecimiento = Establecimiento::where('emisor_id', $emisorId)->findOrFail($establecimientoId);
            $punto = PuntoEmision::where('emisor_id', $emisorId)
                ->where('establecimiento_id', $establecimientoId)
                ->findOrFail($puntoEmisionId);

            $secuencialData = $punto->nextSecuencialNotaDebito();
            $subtotales = $this->buildSubtotales($calculo['detalles']);

            $comprobante = Comprobante::create([
                'emisor_id' => $emisorId,
                'establecimiento_id' => $establecimientoId,
                'punto_emision_id' => $puntoEmisionId,
                'pos_turno_id' => $data['pos_turno_id'] ?? null,
                'cliente_id' => $cliente->id,
                'tipo_comprobante' => 'NOTA_DEBITO',
                'comprobante_modificado_id' => $data['comprobante_modificado_id'],
                'motivo_modificacion' => $data['motivo_modificacion'],
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
                'estado_sri' => 'BORRADOR',
                'ambiente' => $company->ambiente ?? 'PRUEBAS',
                'tipo_emision' => $company->tipo_emision ?? 'NORMAL',
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

                if ($comprobante->tipo_comprobante === 'FACTURA' && !empty($detalle['producto_id'])) {
                    $producto = \App\Models\Producto::find($detalle['producto_id']);
                    $bodega = \App\Models\Bodega::where('establecimiento_id', $establecimientoId)
                                    ->where('tipo', 'PRINCIPAL')
                                    ->first();
                    if (!$bodega) {
                        $bodega = \App\Models\Bodega::where('establecimiento_id', $establecimientoId)->first();
                    }
                    if ($bodega && $producto) {
                        $stockRow = \App\Models\ProductoBodegaStock::where('producto_id', $producto->id)
                                        ->where('bodega_id', $bodega->id)
                                        ->lockForUpdate()
                                        ->first();
                        
                        if ($stockRow) {
                            $stockRow->decrement('stock_actual', $detalle['cantidad']);
                            $saldo = $stockRow->fresh()->stock_actual;
                        } else {
                            $saldo = 0;
                        }

                        \App\Models\Kardex::create([
                            'fecha_hora' => now(),
                            'producto_id' => $producto->id,
                            'bodega_id' => $bodega->id,
                            'tipo_movimiento' => \App\Enums\TipoMovimientoInventario::MOV_03_VENTA_INMEDIATA,
                            'documento_origen_tipo' => 'Comprobante',
                            'documento_origen_id' => $comprobante->id,
                            'numero_documento' => $comprobante->secuencial_formateado,
                            'entrada' => 0,
                            'salida' => $detalle['cantidad'],
                            'saldo' => $saldo,
                            'usuario_id' => \Illuminate\Support\Facades\Auth::id() ?? 1,
                        ]);
                    }
                }
                
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

            return [
                'comprobante_id' => $comprobante->id,
                'secuencial' => $secuencialData['secuencial'],
                'secuencial_formateado' => $secuencialData['secuencial_formateado'],
            ];
        });

        ProcesarFacturaSriJob::dispatch(
            $transactionResult['comprobante_id'],
            $pathFirma,
            Crypt::encryptString($passwordFirma)
        )->afterCommit();

        return response()->json([
            'success' => true,
            'estado' => 'PROCESANDO',
            'comprobante_id' => $transactionResult['comprobante_id'],
            'secuencial' => $transactionResult['secuencial'],
            'secuencial_formateado' => $transactionResult['secuencial_formateado'],
        ], 202);
    }

    public function estadoComprobante(Comprobante $comprobante): JsonResponse
    {
        return response()->json([
            'comprobante_id' => $comprobante->id,
            'clave_acceso' => $comprobante->clave_acceso,
            'estado_sri' => $comprobante->estado_sri,
            'estado_recepcion' => $comprobante->sri_estado_recepcion,
            'estado_autorizacion' => $comprobante->sri_estado_autorizacion,
            'numero_autorizacion' => $comprobante->numero_autorizacion,
            'fecha_autorizacion' => $comprobante->fecha_autorizacion,
            'ultimo_error_sri' => $comprobante->ultimo_error_sri,
            'intentos_envio' => $comprobante->sri_intentos_envio,
            'intentos_autorizacion' => $comprobante->sri_intentos_autorizacion,
            'firmado_en' => $comprobante->firmado_en,
            'enviado_en' => $comprobante->enviado_en,
            'recibido_en' => $comprobante->recibido_en,
            'autorizado_en' => $comprobante->autorizado_en,
            'logs' => $comprobante->sriLogs()->latest()->take(20)->get(),
        ]);
    }

    public function reintentarProcesamiento(Comprobante $comprobante): JsonResponse
    {
        if (in_array($comprobante->estado_sri, ['AUTORIZADO'], true)) {
            return response()->json([
                'message' => 'El comprobante ya fue autorizado y no requiere reintento.',
            ], 409);
        }

        if (!$comprobante->clave_acceso) {
            return response()->json([
                'message' => 'El comprobante no tiene clave de acceso generada; debe reemitirse desde cero.',
            ], 422);
        }

        if (!empty($comprobante->xml_firmado)) {
            ConsultarAutorizacionSriJob::dispatch($comprobante->id)->afterCommit();

            return response()->json([
                'message' => 'Se reprogramo la consulta de autorizacion SRI.',
                'comprobante_id' => $comprobante->id,
            ], 202);
        }

        return response()->json([
            'message' => 'El comprobante aun no tiene XML firmado; vuelva a emitirlo para reiniciar el flujo.',
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

    public function listarComprobantes(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Comprobante::with(['cliente', 'company', 'establecimiento'])
            ->orderByDesc('fecha_emision')
            ->orderByDesc('id');

        // Filtrar por tipo si se envía
        if ($request->filled('tipo')) {
            $query->where('tipo_comprobante', strtoupper($request->input('tipo')));
        }

        // Filtrar por estado si se envía
        if ($request->filled('estado')) {
            $query->where('estado_sri', strtoupper($request->input('estado')));
        }

        // Filtrar por emisor: si el usuario no es admin, solo sus comprobantes
        if ($request->filled('emisor_id')) {
            $query->where('emisor_id', $request->input('emisor_id'));
        }

        $comprobantes = $query->limit(100)->get();

        return response()->json([
            'data' => $comprobantes->map(fn ($c) => [
                'id'                    => $c->id,
                'tipo_comprobante'      => $c->tipo_comprobante,
                'secuencial'            => $c->secuencial,
                'secuencial_formateado' => $c->secuencial_formateado,
                'codigo_establecimiento'=> $c->codigo_establecimiento,
                'punto_emision_codigo'  => $c->punto_emision_codigo,
                'numero_documento'      => sprintf(
                    '%s-%s-%s',
                    str_pad($c->codigo_establecimiento ?? '001', 3, '0', STR_PAD_LEFT),
                    str_pad($c->punto_emision_codigo   ?? '001', 3, '0', STR_PAD_LEFT),
                    str_pad($c->secuencial ?? '1',      9, '0', STR_PAD_LEFT)
                ),
                'fecha_emision'         => $c->fecha_emision?->format('Y-m-d'),
                'cliente_razon_social'  => $c->cliente?->razon_social ?? 'CONSUMIDOR FINAL',
                'cliente_identificacion'=> $c->cliente?->identificacion ?? '9999999999999',
                'total'                 => (float) $c->total,
                'subtotal_sin_impuestos'=> (float) $c->subtotal_sin_impuestos,
                'total_iva'             => (float) $c->total_iva,
                'estado_sri'            => $c->estado_sri,
                'clave_acceso'          => $c->clave_acceso,
                'ambiente'              => $c->ambiente,
                'emisor_id'             => $c->emisor_id,
                'comprobante_modificado_id' => $c->comprobante_modificado_id,
                'motivo_modificacion'   => $c->motivo_modificacion,
            ]),
        ]);
    }

    public function downloadPdf(Comprobante $comprobante, \App\Services\PdfRideService $pdfService): mixed
    {
        if ($comprobante->estado_sri !== 'AUTORIZADO') {
            return response()->json(['message' => 'El comprobante no está autorizado.'], 403);
        }

        $pdfContent = $pdfService->generateRide($comprobante);

        return response($pdfContent)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="' . $comprobante->clave_acceso . '.pdf"');
    }

    public function downloadXml(Comprobante $comprobante): mixed
    {
        if ($comprobante->estado_sri !== 'AUTORIZADO' || !$comprobante->xml_autorizado) {
            return response()->json(['message' => 'El comprobante no está autorizado o no tiene XML.'], 403);
        }

        return response($comprobante->xml_autorizado)
            ->header('Content-Type', 'application/xml')
            ->header('Content-Disposition', 'attachment; filename="' . $comprobante->clave_acceso . '.xml"');
    }
}

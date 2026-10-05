import re

filepath = r"C:\Users\CompuStore\Desktop\dos sistemas\tesis\FacturaElectronica-Backend\app\Http\Controllers\FacturacionController.php"

with open(filepath, 'r', encoding='utf-8') as f:
    content = f.read()

start_idx = content.find("public function emitirFactura(Request $request): JsonResponse")
end_idx = content.find("public function emitirNotaCredito(Request $request): JsonResponse")

if start_idx != -1 and end_idx != -1:
    # Use emitirFactura as base because it's a primary document
    emitir_base = content[start_idx:end_idx]
    
    # We will write the new emitirGuiaRemision method
    emitir_guia = """
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
"""

    new_content = content[:end_idx] + emitir_guia + "\n    " + content[end_idx:]
    with open(filepath, 'w', encoding='utf-8') as f:
        f.write(new_content)
    print("Method emitirGuiaRemision inserted.")
else:
    print("Method boundaries not found.")

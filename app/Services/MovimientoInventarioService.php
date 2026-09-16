<?php

namespace App\Services;

use App\Models\Bodega;
use App\Models\Producto;
use App\Models\RegistroOperativoMovimiento;
use App\Models\ProductoBodegaStock;
use App\Models\Kardex;
use App\Models\Lote;
use App\Models\ExistenciaLote;
use App\Models\Serie;
use App\Enums\TipoMovimientoInventario;
use App\Enums\EstadoRegistroOperativo;
use App\Enums\EstadoSerie;
use App\Enums\TipoBodega;
use App\Exceptions\InvalidWarehouseOperationException;
use Illuminate\Support\Facades\DB;

class MovimientoInventarioService
{
    private function generarNumero(TipoMovimientoInventario $tipo, int $emisorId): string
    {
        preg_match('/MOV_(\d+)/', $tipo->value, $matches);
        $prefijo = 'MOV' . ($matches[1] ?? 'XX');

        $count = RegistroOperativoMovimiento::where('emisor_id', $emisorId)
            ->where('tipo_movimiento', $tipo->value)
            ->whereYear('fecha', now()->year)
            ->count();
            
        return $prefijo . '-' . str_pad($count + 1, 6, '0', STR_PAD_LEFT);
    }

    public function transferir(Bodega $origen, Bodega $destino, array $detalles, ?string $justificacion, int $usuarioId): RegistroOperativoMovimiento
    {
        if ($origen->establecimiento_id !== $destino->establecimiento_id) {
            throw new InvalidWarehouseOperationException("Las transferencias internas (MOV-06) solo están permitidas entre bodegas del mismo establecimiento.");
        }
        
        if ($origen->id === $destino->id) {
            throw new InvalidWarehouseOperationException("La bodega origen y destino no pueden ser la misma.");
        }

        return DB::transaction(function () use ($origen, $destino, $detalles, $justificacion, $usuarioId) {
            $emisorId = $origen->establecimiento->emisor_id;
            $destinoTipo = $destino->tipo instanceof \BackedEnum ? $destino->tipo->value : $destino->tipo;
            $tipoEnum = ($destinoTipo === 'MERMAS') 
                ? TipoMovimientoInventario::MOV_11_ENVIO_MERMAS 
                : TipoMovimientoInventario::MOV_06_TRANSFERENCIA_INTERNA;
            
            $movimiento = RegistroOperativoMovimiento::create([
                'emisor_id' => $emisorId,
                'numero' => $this->generarNumero($tipoEnum, $emisorId),
                'fecha' => now(),
                'tipo_movimiento' => $tipoEnum,
                'estado' => EstadoRegistroOperativo::CONFIRMADO,
                'establecimiento_origen_id' => $origen->establecimiento_id,
                'bodega_origen_id' => $origen->id,
                'establecimiento_destino_id' => $destino->establecimiento_id,
                'bodega_destino_id' => $destino->id,
                'observacion' => $justificacion ?? 'Transferencia interna',
                'usuario_id' => $usuarioId,
            ]);

            foreach ($detalles as $detalle) {
                $producto = Producto::find($detalle['producto_id']);
                
                // VALIDACIONES DE CONSISTENCIA
                if ($producto->tipo_control_inventario->value === 'LOTE') {
                    $lotes = $detalle['lotes'] ?? [];
                    $sumaLotes = array_sum(array_column($lotes, 'cantidad'));
                    if (floatval($sumaLotes) !== floatval($detalle['cantidad'])) {
                        throw new InvalidWarehouseOperationException("La suma en los lotes ({$sumaLotes}) no coincide con la cantidad transferida ({$detalle['cantidad']}) para el producto {$producto->codigo}.");
                    }
                } elseif ($producto->tipo_control_inventario->value === 'SERIE') {
                    $series = $detalle['series'] ?? [];
                    $totalSeries = count($series);
                    if (floatval($totalSeries) !== floatval($detalle['cantidad'])) {
                        throw new InvalidWarehouseOperationException("El número de series ({$totalSeries}) no coincide con la cantidad transferida ({$detalle['cantidad']}) para el producto {$producto->codigo}.");
                    }
                }

                // 1. Stock General (Resta y Suma)
                $this->descontarStock($origen, $producto->id, $detalle['cantidad']);
                $this->incrementarStock($destino, $producto->id, $detalle['cantidad']);
                
                $registroDetalle = $movimiento->detalles()->create([
                    'producto_id' => $producto->id,
                    'cantidad' => $detalle['cantidad'],
                ]);

                // 2. Kardex (Doble asiento)
                $saldoOrigen = ProductoBodegaStock::where('producto_id', $producto->id)->where('bodega_id', $origen->id)->value('stock_fisico');
                $saldoDestino = ProductoBodegaStock::where('producto_id', $producto->id)->where('bodega_id', $destino->id)->value('stock_fisico');
                
                // Salida de origen
                Kardex::create([
                    'fecha_hora' => now(),
                    'producto_id' => $producto->id,
                    'bodega_id' => $origen->id,
                    'tipo_movimiento' => $tipoEnum,
                    'documento_origen_tipo' => 'RegistroOperativo',
                    'documento_origen_id' => $movimiento->id,
                    'numero_documento' => $movimiento->numero,
                    'entrada' => 0,
                    'salida' => $detalle['cantidad'],
                    'saldo' => $saldoOrigen,
                    'usuario_id' => $usuarioId,
                ]);

                // Entrada a destino
                Kardex::create([
                    'fecha_hora' => now(),
                    'producto_id' => $producto->id,
                    'bodega_id' => $destino->id,
                    'tipo_movimiento' => $tipoEnum,
                    'documento_origen_tipo' => 'RegistroOperativo',
                    'documento_origen_id' => $movimiento->id,
                    'numero_documento' => $movimiento->numero,
                    'entrada' => $detalle['cantidad'],
                    'salida' => 0,
                    'saldo' => $saldoDestino,
                    'usuario_id' => $usuarioId,
                ]);

                // 3. Control Existencias
                if ($producto->tipo_control_inventario->value === 'LOTE' && !empty($detalle['lotes'])) {
                    foreach ($detalle['lotes'] as $loteData) {
                        $lote = Lote::where('producto_id', $producto->id)->where('numero_lote', $loteData['numero_lote'])->first();
                        if (!$lote) {
                            throw new InvalidWarehouseOperationException("El lote {$loteData['numero_lote']} no existe en el sistema.");
                        }

                        // A. Descontar del Origen
                        $existenciaOrigen = ExistenciaLote::where(['producto_id' => $producto->id, 'bodega_id' => $origen->id, 'lote_id' => $lote->id])->lockForUpdate()->first();
                        if (!$existenciaOrigen || $existenciaOrigen->stock_disponible < $loteData['cantidad']) {
                            throw new InvalidWarehouseOperationException("Stock insuficiente para el lote {$loteData['numero_lote']} en la bodega origen.");
                        }
                        
                        $existenciaOrigen->stock_fisico -= $loteData['cantidad'];
                        $existenciaOrigen->stock_disponible -= $loteData['cantidad'];
                        $existenciaOrigen->save();

                        // B. Incrementar en el Destino
                        $existenciaDestino = ExistenciaLote::firstOrCreate(
                            ['producto_id' => $producto->id, 'bodega_id' => $destino->id, 'lote_id' => $lote->id],
                            ['stock_fisico' => 0, 'stock_reservado' => 0, 'stock_disponible' => 0]
                        );
                        $existenciaDestinoLock = ExistenciaLote::where('id', $existenciaDestino->id)->lockForUpdate()->first();
                        $existenciaDestinoLock->stock_fisico += $loteData['cantidad'];
                        $existenciaDestinoLock->stock_disponible += $loteData['cantidad'];
                        $existenciaDestinoLock->save();

                        $registroDetalle->lotes()->create([
                            'lote_id' => $lote->id,
                            'cantidad' => $loteData['cantidad']
                        ]);
                    }
                } elseif ($producto->tipo_control_inventario->value === 'SERIE' && !empty($detalle['series'])) {
                    foreach ($detalle['series'] as $serieData) {
                        $serie = Serie::where('producto_id', $producto->id)->where('numero_serie', $serieData['numero_serie'])->first();
                        
                        if (!$serie || $serie->bodega_actual_id !== $origen->id || $serie->estado !== EstadoSerie::DISPONIBLE) {
                            throw new InvalidWarehouseOperationException("La serie {$serieData['numero_serie']} no está disponible en la bodega de origen para transferir.");
                        }
                        
                        // Mover serie a la nueva bodega
                        $serie->update([
                            'bodega_actual_id' => $destino->id
                        ]);

                        $registroDetalle->series()->create(['serie_id' => $serie->id]);
                    }
                }
            }

            return $movimiento;
        });
    }
    public function transferirReacondicionado(Bodega $origen, Bodega $destino, array $detalles, string $justificacion, int $usuarioId): RegistroOperativoMovimiento
    {
        if (empty(trim($justificacion))) {
            throw new InvalidWarehouseOperationException("El reacondicionamiento (MOV-12) requiere obligatoriamente una justificación.");
        }
        
        // El modelo Bodega asume que el atributo está casteado al Enum
        $origenTipo = $origen->tipo instanceof TipoBodega ? $origen->tipo->value : $origen->tipo;
        $destinoTipo = $destino->tipo instanceof TipoBodega ? $destino->tipo->value : $destino->tipo;

        if ($origenTipo !== 'MERMAS') {
            throw new InvalidWarehouseOperationException("Para un reacondicionamiento, la bodega origen debe ser obligatoriamente de tipo MERMAS.");
        }
        
        if ($destinoTipo === 'MERMAS') {
            throw new InvalidWarehouseOperationException("Para un reacondicionamiento, la bodega destino NO puede ser de tipo MERMAS.");
        }

        return DB::transaction(function () use ($origen, $destino, $detalles, $justificacion, $usuarioId) {
            $emisorId = $origen->establecimiento->emisor_id;
            $tipoEnum = TipoMovimientoInventario::MOV_12_REACONDICIONAMIENTO_MERMAS;
            
            $movimiento = RegistroOperativoMovimiento::create([
                'emisor_id' => $emisorId,
                'numero' => $this->generarNumero($tipoEnum, $emisorId),
                'fecha' => now(),
                'tipo_movimiento' => $tipoEnum,
                'estado' => EstadoRegistroOperativo::CONFIRMADO,
                'establecimiento_origen_id' => $origen->establecimiento_id,
                'bodega_origen_id' => $origen->id,
                'establecimiento_destino_id' => $destino->establecimiento_id,
                'bodega_destino_id' => $destino->id,
                'observacion' => $justificacion,
                'usuario_id' => $usuarioId,
            ]);

            foreach ($detalles as $detalle) {
                $producto = Producto::find($detalle['producto_id']);
                
                // VALIDACIONES DE CONSISTENCIA
                if ($producto->tipo_control_inventario->value === 'LOTE') {
                    $lotes = $detalle['lotes'] ?? [];
                    $sumaLotes = array_sum(array_column($lotes, 'cantidad'));
                    if (floatval($sumaLotes) !== floatval($detalle['cantidad'])) {
                        throw new InvalidWarehouseOperationException("La suma en los lotes ({$sumaLotes}) no coincide con la cantidad a reacondicionar ({$detalle['cantidad']}) para el producto {$producto->codigo}.");
                    }
                } elseif ($producto->tipo_control_inventario->value === 'SERIE') {
                    $series = $detalle['series'] ?? [];
                    $totalSeries = count($series);
                    if (floatval($totalSeries) !== floatval($detalle['cantidad'])) {
                        throw new InvalidWarehouseOperationException("El número de series ({$totalSeries}) no coincide con la cantidad a reacondicionar ({$detalle['cantidad']}) para el producto {$producto->codigo}.");
                    }
                }

                // 1. Stock General
                $this->descontarStock($origen, $producto->id, $detalle['cantidad']);
                $this->incrementarStock($destino, $producto->id, $detalle['cantidad']);
                
                $registroDetalle = $movimiento->detalles()->create([
                    'producto_id' => $producto->id,
                    'cantidad' => $detalle['cantidad'],
                ]);

                // 2. Kardex (Doble asiento)
                $saldoOrigen = ProductoBodegaStock::where('producto_id', $producto->id)->where('bodega_id', $origen->id)->value('stock_fisico');
                $saldoDestino = ProductoBodegaStock::where('producto_id', $producto->id)->where('bodega_id', $destino->id)->value('stock_fisico');
                
                // Salida de Mermas
                Kardex::create([
                    'fecha_hora' => now(),
                    'producto_id' => $producto->id,
                    'bodega_id' => $origen->id,
                    'tipo_movimiento' => $tipoEnum,
                    'documento_origen_tipo' => 'RegistroOperativo',
                    'documento_origen_id' => $movimiento->id,
                    'numero_documento' => $movimiento->numero,
                    'entrada' => 0,
                    'salida' => $detalle['cantidad'],
                    'saldo' => $saldoOrigen,
                    'usuario_id' => $usuarioId,
                ]);

                // Entrada a Destino
                Kardex::create([
                    'fecha_hora' => now(),
                    'producto_id' => $producto->id,
                    'bodega_id' => $destino->id,
                    'tipo_movimiento' => $tipoEnum,
                    'documento_origen_tipo' => 'RegistroOperativo',
                    'documento_origen_id' => $movimiento->id,
                    'numero_documento' => $movimiento->numero,
                    'entrada' => $detalle['cantidad'],
                    'salida' => 0,
                    'saldo' => $saldoDestino,
                    'usuario_id' => $usuarioId,
                ]);

                // 3. Control Existencias
                if ($producto->tipo_control_inventario->value === 'LOTE' && !empty($detalle['lotes'])) {
                    foreach ($detalle['lotes'] as $loteData) {
                        $lote = Lote::where('producto_id', $producto->id)->where('numero_lote', $loteData['numero_lote'])->first();
                        if (!$lote) {
                            throw new InvalidWarehouseOperationException("El lote {$loteData['numero_lote']} no existe en el sistema.");
                        }

                        // Descontar Mermas
                        $existenciaOrigen = ExistenciaLote::where(['producto_id' => $producto->id, 'bodega_id' => $origen->id, 'lote_id' => $lote->id])->lockForUpdate()->first();
                        if (!$existenciaOrigen || $existenciaOrigen->stock_disponible < $loteData['cantidad']) {
                            throw new InvalidWarehouseOperationException("Stock insuficiente para el lote {$loteData['numero_lote']} en la bodega de mermas.");
                        }
                        $existenciaOrigen->stock_fisico -= $loteData['cantidad'];
                        $existenciaOrigen->stock_disponible -= $loteData['cantidad'];
                        $existenciaOrigen->save();

                        // Incrementar Destino
                        $existenciaDestino = ExistenciaLote::firstOrCreate(
                            ['producto_id' => $producto->id, 'bodega_id' => $destino->id, 'lote_id' => $lote->id],
                            ['stock_fisico' => 0, 'stock_reservado' => 0, 'stock_disponible' => 0]
                        );
                        $existenciaDestinoLock = ExistenciaLote::where('id', $existenciaDestino->id)->lockForUpdate()->first();
                        $existenciaDestinoLock->stock_fisico += $loteData['cantidad'];
                        $existenciaDestinoLock->stock_disponible += $loteData['cantidad'];
                        $existenciaDestinoLock->save();

                        $registroDetalle->lotes()->create([
                            'lote_id' => $lote->id,
                            'cantidad' => $loteData['cantidad']
                        ]);
                    }
                } elseif ($producto->tipo_control_inventario->value === 'SERIE' && !empty($detalle['series'])) {
                    foreach ($detalle['series'] as $serieData) {
                        $serie = Serie::where('producto_id', $producto->id)->where('numero_serie', $serieData['numero_serie'])->first();
                        
                        if (!$serie || $serie->bodega_actual_id !== $origen->id) {
                            throw new InvalidWarehouseOperationException("La serie {$serieData['numero_serie']} no se encuentra físicamente en la bodega de mermas de origen.");
                        }
                        
                        // Mover serie, marcar como reacondicionada y ponerla como disponible
                        $serie->update([
                            'bodega_actual_id' => $destino->id,
                            'estado' => EstadoSerie::DISPONIBLE,
                            'reacondicionado' => true,
                            'fecha_actualizacion_manual_estado' => now(),
                            'usuario_actualizacion_manual_id' => $usuarioId
                        ]);

                        $registroDetalle->series()->create(['serie_id' => $serie->id]);
                    }
                }
            }

            return $movimiento;
        });
    }

    public function inventarioInicial(Bodega $destino, array $detalles, string $justificacion, int $usuarioId): RegistroOperativoMovimiento
    {
        return $this->procesarEntrada(TipoMovimientoInventario::MOV_01_INVENTARIO_INICIAL, $destino, $detalles, $justificacion, $usuarioId);
    }

    public function ajustarPositivo(Bodega $destino, array $detalles, string $justificacion, int $usuarioId): RegistroOperativoMovimiento
    {
        return $this->procesarEntrada(TipoMovimientoInventario::MOV_09_AJUSTE_POSITIVO, $destino, $detalles, $justificacion, $usuarioId);
    }

    private function procesarEntrada(TipoMovimientoInventario $tipoEnum, Bodega $destino, array $detalles, string $justificacion, int $usuarioId): RegistroOperativoMovimiento
    {
        if (empty(trim($justificacion))) {
            throw new InvalidWarehouseOperationException("Las entradas (ajustes o inventario inicial) requieren justificación.");
        }

        return DB::transaction(function () use ($tipoEnum, $destino, $detalles, $justificacion, $usuarioId) {
            $emisorId = $destino->establecimiento->emisor_id;
            
            $movimiento = RegistroOperativoMovimiento::create([
                'emisor_id' => $emisorId,
                'numero' => $this->generarNumero($tipoEnum, $emisorId),
                'fecha' => now(),
                'tipo_movimiento' => $tipoEnum,
                'estado' => EstadoRegistroOperativo::CONFIRMADO,
                'establecimiento_destino_id' => $destino->establecimiento_id,
                'bodega_destino_id' => $destino->id,
                'observacion' => $justificacion,
                'usuario_id' => $usuarioId,
            ]);

            foreach ($detalles as $detalle) {
                $producto = Producto::find($detalle['producto_id']);
                
                // VALIDACIONES DE CONSISTENCIA
                if ($producto->tipo_control_inventario->value === 'LOTE') {
                    $lotes = $detalle['lotes'] ?? [];
                    $sumaLotes = array_sum(array_column($lotes, 'cantidad'));
                    if (floatval($sumaLotes) !== floatval($detalle['cantidad'])) {
                        throw new InvalidWarehouseOperationException("La suma de cantidades en los lotes ({$sumaLotes}) no coincide con la cantidad total del detalle ({$detalle['cantidad']}) para el producto {$producto->codigo}.");
                    }
                } elseif ($producto->tipo_control_inventario->value === 'SERIE') {
                    $series = $detalle['series'] ?? [];
                    $totalSeries = count($series);
                    if (floatval($totalSeries) !== floatval($detalle['cantidad'])) {
                        throw new InvalidWarehouseOperationException("El número de series proporcionadas ({$totalSeries}) no coincide con la cantidad total del detalle ({$detalle['cantidad']}) para el producto {$producto->codigo}.");
                    }
                }

                // 1. Stock General
                $this->incrementarStock($destino, $producto->id, $detalle['cantidad']);
                
                $registroDetalle = $movimiento->detalles()->create([
                    'producto_id' => $producto->id,
                    'cantidad' => $detalle['cantidad'],
                ]);

                // 2. Kardex
                $saldo = ProductoBodegaStock::where('producto_id', $producto->id)->where('bodega_id', $destino->id)->value('stock_fisico');
                
                Kardex::create([
                    'fecha_hora' => now(),
                    'producto_id' => $producto->id,
                    'bodega_id' => $destino->id,
                    'tipo_movimiento' => $tipoEnum,
                    'documento_origen_tipo' => 'RegistroOperativo',
                    'documento_origen_id' => $movimiento->id,
                    'numero_documento' => $movimiento->numero,
                    'entrada' => $detalle['cantidad'],
                    'salida' => 0,
                    'saldo' => $saldo,
                    'usuario_id' => $usuarioId,
                ]);

                // 3. Control de Existencias (Lotes/Series)
                if ($producto->tipo_control_inventario->value === 'LOTE' && !empty($detalle['lotes'])) {
                    foreach ($detalle['lotes'] as $loteData) {
                        $lote = Lote::firstOrCreate(
                            ['producto_id' => $producto->id, 'numero_lote' => $loteData['numero_lote']],
                            [
                                'fecha_fabricacion' => $loteData['fecha_fabricacion'] ?? null, 
                                'fecha_vencimiento' => $loteData['fecha_vencimiento'] ?? now()->addYear(), 
                                'usuario_registro_id' => $usuarioId
                            ]
                        );

                        $existenciaLote = ExistenciaLote::firstOrCreate(
                            ['producto_id' => $producto->id, 'bodega_id' => $destino->id, 'lote_id' => $lote->id],
                            ['stock_fisico' => 0, 'stock_reservado' => 0, 'stock_disponible' => 0]
                        );
                        
                        $existenciaLoteLock = ExistenciaLote::where('id', $existenciaLote->id)->lockForUpdate()->first();
                        $existenciaLoteLock->stock_fisico += $loteData['cantidad'];
                        $existenciaLoteLock->stock_disponible += $loteData['cantidad'];
                        $existenciaLoteLock->save();

                        $registroDetalle->lotes()->create([
                            'lote_id' => $lote->id,
                            'cantidad' => $loteData['cantidad']
                        ]);
                    }
                } elseif ($producto->tipo_control_inventario->value === 'SERIE' && !empty($detalle['series'])) {
                    foreach ($detalle['series'] as $serieData) {
                        $serie = Serie::firstOrCreate(
                            ['producto_id' => $producto->id, 'numero_serie' => $serieData['numero_serie']],
                            [
                                'bodega_actual_id' => $destino->id,
                                'estado' => EstadoSerie::DISPONIBLE,
                                'fecha_vencimiento' => now()->addYear(),
                                'usuario_registro_id' => $usuarioId
                            ]
                        );

                        // Si la serie ya existía pero estaba de baja, la revive
                        if ($serie->estado !== EstadoSerie::DISPONIBLE || $serie->bodega_actual_id !== $destino->id) {
                            $serie->update([
                                'bodega_actual_id' => $destino->id,
                                'estado' => EstadoSerie::DISPONIBLE
                            ]);
                        }

                        $registroDetalle->series()->create(['serie_id' => $serie->id]);
                    }
                }
            }

            return $movimiento;
        });
    }

    public function ajustarNegativo(Bodega $origen, array $detalles, string $justificacion, int $usuarioId): RegistroOperativoMovimiento
    {
        if (empty(trim($justificacion))) {
            throw new InvalidWarehouseOperationException("Los ajustes requieren justificación.");
        }

        return DB::transaction(function () use ($origen, $detalles, $justificacion, $usuarioId) {
            $emisorId = $origen->establecimiento->emisor_id;
            $tipoEnum = TipoMovimientoInventario::MOV_10_AJUSTE_NEGATIVO;
            
            $movimiento = RegistroOperativoMovimiento::create([
                'emisor_id' => $emisorId,
                'numero' => $this->generarNumero($tipoEnum, $emisorId),
                'fecha' => now(),
                'tipo_movimiento' => $tipoEnum,
                'estado' => EstadoRegistroOperativo::CONFIRMADO,
                'establecimiento_origen_id' => $origen->establecimiento_id,
                'bodega_origen_id' => $origen->id,
                'observacion' => $justificacion,
                'usuario_id' => $usuarioId,
            ]);

            foreach ($detalles as $detalle) {
                $producto = Producto::find($detalle['producto_id']);
                
                // VALIDACIONES DE CONSISTENCIA
                if ($producto->tipo_control_inventario->value === 'LOTE') {
                    $lotes = $detalle['lotes'] ?? [];
                    $sumaLotes = array_sum(array_column($lotes, 'cantidad'));
                    if (floatval($sumaLotes) !== floatval($detalle['cantidad'])) {
                        throw new InvalidWarehouseOperationException("La suma de cantidades en los lotes ({$sumaLotes}) no coincide con la cantidad total del detalle ({$detalle['cantidad']}) para el producto {$producto->codigo}.");
                    }
                } elseif ($producto->tipo_control_inventario->value === 'SERIE') {
                    $series = $detalle['series'] ?? [];
                    $totalSeries = count($series);
                    if (floatval($totalSeries) !== floatval($detalle['cantidad'])) {
                        throw new InvalidWarehouseOperationException("El número de series proporcionadas ({$totalSeries}) no coincide con la cantidad total del detalle ({$detalle['cantidad']}) para el producto {$producto->codigo}.");
                    }
                }

                // 1. Stock General
                $this->descontarStock($origen, $producto->id, $detalle['cantidad']);
                
                $registroDetalle = $movimiento->detalles()->create([
                    'producto_id' => $producto->id,
                    'cantidad' => $detalle['cantidad'],
                ]);

                // 2. Kardex
                $saldo = ProductoBodegaStock::where('producto_id', $producto->id)->where('bodega_id', $origen->id)->value('stock_fisico');
                
                Kardex::create([
                    'fecha_hora' => now(),
                    'producto_id' => $producto->id,
                    'bodega_id' => $origen->id,
                    'tipo_movimiento' => $tipoEnum,
                    'documento_origen_tipo' => 'RegistroOperativo',
                    'documento_origen_id' => $movimiento->id,
                    'numero_documento' => $movimiento->numero,
                    'entrada' => 0,
                    'salida' => $detalle['cantidad'],
                    'saldo' => $saldo,
                    'usuario_id' => $usuarioId,
                ]);

                // 3. Control Existencias
                if ($producto->tipo_control_inventario->value === 'LOTE' && !empty($detalle['lotes'])) {
                    foreach ($detalle['lotes'] as $loteData) {
                        $lote = Lote::where('producto_id', $producto->id)->where('numero_lote', $loteData['numero_lote'])->first();
                        if (!$lote) {
                            throw new InvalidWarehouseOperationException("El lote {$loteData['numero_lote']} no existe.");
                        }

                        $existenciaLote = ExistenciaLote::where([
                            'producto_id' => $producto->id, 'bodega_id' => $origen->id, 'lote_id' => $lote->id
                        ])->lockForUpdate()->first();

                        if (!$existenciaLote || $existenciaLote->stock_disponible < $loteData['cantidad']) {
                            throw new InvalidWarehouseOperationException("Stock insuficiente para el lote {$loteData['numero_lote']}");
                        }
                        
                        $existenciaLote->stock_fisico -= $loteData['cantidad'];
                        $existenciaLote->stock_disponible -= $loteData['cantidad'];
                        $existenciaLote->save();

                        $registroDetalle->lotes()->create([
                            'lote_id' => $lote->id,
                            'cantidad' => $loteData['cantidad']
                        ]);
                    }
                } elseif ($producto->tipo_control_inventario->value === 'SERIE' && !empty($detalle['series'])) {
                    foreach ($detalle['series'] as $serieData) {
                        $serie = Serie::where('producto_id', $producto->id)->where('numero_serie', $serieData['numero_serie'])->first();
                        
                        if (!$serie || $serie->bodega_actual_id !== $origen->id || $serie->estado !== EstadoSerie::DISPONIBLE) {
                            throw new InvalidWarehouseOperationException("La serie {$serieData['numero_serie']} no está disponible en esta bodega para dar de baja.");
                        }
                        
                        $serie->update([
                            'estado' => EstadoSerie::BAJA_POR_AJUSTE,
                            'fecha_actualizacion_manual_estado' => now(),
                            'usuario_actualizacion_manual_id' => $usuarioId
                        ]);

                        $registroDetalle->series()->create(['serie_id' => $serie->id]);
                    }
                }
            }

            return $movimiento;
        });
    }

    private function descontarStock(Bodega $bodega, int $productoId, float $cantidad): void
    {
        $stock = ProductoBodegaStock::where('producto_id', $productoId)
            ->where('bodega_id', $bodega->id)
            ->lockForUpdate()
            ->first();

        if (!$stock || $stock->stock_disponible < $cantidad) {
            throw new InvalidWarehouseOperationException("Stock disponible insuficiente.");
        }

        $stock->stock_fisico -= $cantidad;
        $stock->stock_disponible = max(0, $stock->stock_fisico - $stock->stock_reservado);
        $stock->save();
    }

    private function incrementarStock(Bodega $bodega, int $productoId, float $cantidad): void
    {
        $stockGenerico = ProductoBodegaStock::firstOrCreate(
            ['producto_id' => $productoId, 'bodega_id' => $bodega->id],
            ['stock_fisico' => 0, 'stock_disponible' => 0, 'stock_reservado' => 0, 'fecha_registro' => now()]
        );

        $stock = ProductoBodegaStock::where('id', $stockGenerico->id)
            ->lockForUpdate()
            ->first();

        $stock->stock_fisico += $cantidad;
        $stock->stock_disponible = max(0, $stock->stock_fisico - $stock->stock_reservado);
        $stock->save();
    }
    /**
     * SIEMPRE se adquieren locks por bodega_id ascendente, y dentro de una misma bodega 
     * por lote_id/producto_id ascendente, para prevenir deadlocks entre transferencias 
     * concurrentes en direcciones opuestas.
     */
    private function asegurarLocksOrdenados(array $productosStock, array $lotesStock): void
    {
        // 1. Unificar y ordenar ProductoBodegaStock
        $productosStock = array_map("unserialize", array_unique(array_map("serialize", $productosStock)));
        usort($productosStock, fn($a, $b) => 
            $a['bodega_id'] === $b['bodega_id'] 
                ? $a['producto_id'] <=> $b['producto_id'] 
                : $a['bodega_id'] <=> $b['bodega_id']
        );
        
        foreach ($productosStock as $item) {
            $stock = ProductoBodegaStock::firstOrCreate(
                ['producto_id' => $item['producto_id'], 'bodega_id' => $item['bodega_id']],
                ['stock_fisico' => 0, 'stock_disponible' => 0, 'stock_reservado' => 0, 'fecha_registro' => now()]
            );
            ProductoBodegaStock::where('id', $stock->id)->lockForUpdate()->first();
        }

        // 2. Unificar y ordenar ExistenciaLote
        $lotesStock = array_map("unserialize", array_unique(array_map("serialize", $lotesStock)));
        usort($lotesStock, fn($a, $b) => 
            $a['bodega_id'] === $b['bodega_id'] 
                ? $a['lote_id'] <=> $b['lote_id'] 
                : $a['bodega_id'] <=> $b['bodega_id']
        );

        foreach ($lotesStock as $item) {
            $exLote = ExistenciaLote::firstOrCreate(
                ['bodega_id' => $item['bodega_id'], 'lote_id' => $item['lote_id'], 'producto_id' => $item['producto_id']],
                ['stock_fisico' => 0, 'stock_reservado' => 0, 'stock_disponible' => 0]
            );
            ExistenciaLote::where('id', $exLote->id)->lockForUpdate()->first();
        }
    }

    // --- FASE 5: TRANSFERENCIAS POR SUCURSAL (MOV-07 y MOV-08) ---

    public function despacharTransferencia(Bodega $origen, Bodega $destino, array $detalles, string $justificacion, int $usuarioId): RegistroOperativoMovimiento
    {
        if ($origen->establecimiento_id === $destino->establecimiento_id) {
            throw new InvalidWarehouseOperationException("Para enviar entre la misma sucursal, use una transferencia interna (MOV-06).");
        }

        return DB::transaction(function () use ($origen, $destino, $detalles, $justificacion, $usuarioId) {
            $emisorId = $origen->establecimiento->emisor_id;
            
            // Buscar Bodega Tránsito del emisor
            $bodegaTransito = Bodega::whereHas('establecimiento', function($q) use ($emisorId) {
                $q->where('emisor_id', $emisorId);
            })->where('tipo', 'TRANSITO')->first();

            if (!$bodegaTransito) {
                throw new InvalidWarehouseOperationException("El administrador debe configurar una Bodega de Tránsito para el emisor antes de despachar mercancía (MOV-07).");
            }

            $tipoEnum = TipoMovimientoInventario::MOV_07_TRANSFERENCIA_SUCURSALES;
            
            $movimiento = RegistroOperativoMovimiento::create([
                'emisor_id' => $emisorId,
                'numero' => $this->generarNumero($tipoEnum, $emisorId),
                'fecha' => now(),
                'tipo_movimiento' => $tipoEnum,
                'estado' => \App\Enums\EstadoRegistroOperativo::EN_PROCESO,
                'estado_operativo_transferencia' => \App\Enums\EstadoOperativoTransferencia::EN_TRANSITO,
                'estado_operativo_recepcion' => \App\Enums\EstadoOperativoRecepcion::PENDIENTE_DE_DESCARGA,
                'establecimiento_origen_id' => $origen->establecimiento_id,
                'bodega_origen_id' => $origen->id,
                'establecimiento_destino_id' => $destino->establecimiento_id,
                'bodega_destino_id' => $destino->id,
                'observacion' => $justificacion,
                'usuario_id' => $usuarioId,
            ]);

            foreach ($detalles as $detalle) {
                $producto = Producto::find($detalle['producto_id']);
                
                // Consistencia básica
                if ($producto->tipo_control_inventario->value === 'LOTE' && !empty($detalle['lotes'])) {
                    $sumaLotes = array_sum(array_column($detalle['lotes'], 'cantidad'));
                    if (floatval($sumaLotes) !== floatval($detalle['cantidad'])) throw new InvalidWarehouseOperationException("Suma de lotes no coincide (MOV-07).");
                } elseif ($producto->tipo_control_inventario->value === 'SERIE' && !empty($detalle['series'])) {
                    if (count($detalle['series']) != $detalle['cantidad']) throw new InvalidWarehouseOperationException("Conteo de series no coincide (MOV-07).");
                }

                $this->descontarStock($origen, $producto->id, $detalle['cantidad']);
                $this->incrementarStock($bodegaTransito, $producto->id, $detalle['cantidad']);
                
                $registroDetalle = $movimiento->detalles()->create(['producto_id' => $producto->id, 'cantidad' => $detalle['cantidad']]);

                // Kardex Origen -> Transito
                $saldoOrigen = ProductoBodegaStock::where('producto_id', $producto->id)->where('bodega_id', $origen->id)->value('stock_fisico');
                $saldoTransito = ProductoBodegaStock::where('producto_id', $producto->id)->where('bodega_id', $bodegaTransito->id)->value('stock_fisico');
                
                Kardex::create(['fecha_hora' => now(), 'producto_id' => $producto->id, 'bodega_id' => $origen->id, 'tipo_movimiento' => $tipoEnum, 'documento_origen_tipo' => 'RegistroOperativo', 'documento_origen_id' => $movimiento->id, 'numero_documento' => $movimiento->numero, 'entrada' => 0, 'salida' => $detalle['cantidad'], 'saldo' => $saldoOrigen, 'usuario_id' => $usuarioId]);
                Kardex::create(['fecha_hora' => now(), 'producto_id' => $producto->id, 'bodega_id' => $bodegaTransito->id, 'tipo_movimiento' => $tipoEnum, 'documento_origen_tipo' => 'RegistroOperativo', 'documento_origen_id' => $movimiento->id, 'numero_documento' => $movimiento->numero, 'entrada' => $detalle['cantidad'], 'salida' => 0, 'saldo' => $saldoTransito, 'usuario_id' => $usuarioId]);

                if ($producto->tipo_control_inventario->value === 'LOTE' && !empty($detalle['lotes'])) {
                    foreach ($detalle['lotes'] as $loteData) {
                        $lote = Lote::where('producto_id', $producto->id)->where('numero_lote', $loteData['numero_lote'])->first();
                        $exOrig = ExistenciaLote::where(['producto_id' => $producto->id, 'bodega_id' => $origen->id, 'lote_id' => $lote->id])->lockForUpdate()->first();
                        $exOrig->stock_fisico -= $loteData['cantidad'];
                        $exOrig->stock_disponible -= $loteData['cantidad'];
                        $exOrig->save();

                        $exTransito = ExistenciaLote::firstOrCreate(['producto_id' => $producto->id, 'bodega_id' => $bodegaTransito->id, 'lote_id' => $lote->id], ['stock_fisico' => 0, 'stock_reservado' => 0, 'stock_disponible' => 0]);
                        $exTransitoLock = ExistenciaLote::where('id', $exTransito->id)->lockForUpdate()->first();
                        $exTransitoLock->stock_fisico += $loteData['cantidad'];
                        $exTransitoLock->stock_disponible += $loteData['cantidad'];
                        $exTransitoLock->save();

                        $registroDetalle->lotes()->create(['lote_id' => $lote->id, 'cantidad' => $loteData['cantidad']]);
                    }
                } elseif ($producto->tipo_control_inventario->value === 'SERIE' && !empty($detalle['series'])) {
                    foreach ($detalle['series'] as $serieData) {
                        $serie = Serie::where('producto_id', $producto->id)->where('numero_serie', $serieData['numero_serie'])->first();
                        $serie->update(['bodega_actual_id' => $bodegaTransito->id, 'estado' => \App\Enums\EstadoSerie::EN_TRANSITO]);
                        $registroDetalle->series()->create(['serie_id' => $serie->id]);
                    }
                }
            }

            return $movimiento;
        });
    }

    public function registrarDescarga(RegistroOperativoMovimiento $movimiento07): void
    {
        if ($movimiento07->tipo_movimiento->value !== 'MOV_07_TRANSFERENCIA_SUCURSALES' || $movimiento07->estado_operativo_transferencia->value !== 'EN_TRANSITO') {
            throw new InvalidWarehouseOperationException("Documento inválido para descarga.");
        }
        $movimiento07->update([
            'estado_operativo_recepcion' => \App\Enums\EstadoOperativoRecepcion::DESCARGA_REGISTRADA
        ]);
    }

    public function confirmarRecepcion(RegistroOperativoMovimiento $mov07, array $detallesReales, string $justificacion, int $usuarioId, ?Bodega $bodegaIncidencia = null): RegistroOperativoMovimiento
    {
        if ($mov07->estado_operativo_recepcion->value !== 'DESCARGA_REGISTRADA') {
            throw new InvalidWarehouseOperationException("El vehículo debe registrar su descarga antes de confirmar la recepción.");
        }

        return DB::transaction(function () use ($mov07, $detallesReales, $justificacion, $usuarioId, $bodegaIncidencia) {
            $emisorId = $mov07->emisor_id;
            $bodegaTransito = Bodega::whereHas('establecimiento', function($q) use ($emisorId) { $q->where('emisor_id', $emisorId); })->where('tipo', 'TRANSITO')->first();
            $bodegaDestino = Bodega::find($mov07->bodega_destino_id);
            $tipoEnum = TipoMovimientoInventario::MOV_08_RECEPCION_TRANSFERENCIA;
            
            $mov08 = RegistroOperativoMovimiento::create([
                'emisor_id' => $emisorId,
                'numero' => $this->generarNumero($tipoEnum, $emisorId),
                'fecha' => now(),
                'tipo_movimiento' => $tipoEnum,
                'estado' => \App\Enums\EstadoRegistroOperativo::CONFIRMADO,
                'movimiento_origen_id' => $mov07->id,
                'establecimiento_destino_id' => $bodegaDestino->establecimiento_id,
                'bodega_destino_id' => $bodegaDestino->id,
                'bodega_incidencia_id' => $bodegaIncidencia ? $bodegaIncidencia->id : null,
                'observacion' => $justificacion,
                'usuario_id' => $usuarioId,
            ]);

            $huboIncidencia = false;

            foreach ($detallesReales as $dr) {
                $detalleOriginal = $mov07->detalles->where('producto_id', $dr['producto_id'])->first();
                if (!$detalleOriginal) throw new InvalidWarehouseOperationException("El producto ID {$dr['producto_id']} no fue despachado en origen.");

                $cantEnviada = floatval($detalleOriginal->cantidad);
                $cantBuena = floatval($dr['cantidad_buena'] ?? 0);
                $cantFaltante = floatval($dr['cantidad_faltante'] ?? 0);
                $cantDanada = floatval($dr['cantidad_danada'] ?? 0);
                $cantSobrante = floatval($dr['cantidad_sobrante'] ?? 0);

                if (abs($cantEnviada - ($cantBuena + $cantFaltante + $cantDanada)) > 0.000001) {
                    throw new InvalidWarehouseOperationException("Inconsistencia matemática: La cantidad despachada ($cantEnviada) no cuadra con Buena+Faltante+Dañada para {$dr['producto_id']}.");
                }

                if ($cantFaltante > 0 || $cantDanada > 0 || $cantSobrante > 0) $huboIncidencia = true;
                if ($cantDanada > 0 && !$bodegaIncidencia) throw new InvalidWarehouseOperationException("Se requiere bodega de incidencia para procesar daños.");

                $producto = Producto::find($dr['producto_id']);
                
                $regDetalle = $mov08->detalles()->create([
                    'producto_id' => $producto->id,
                    'cantidad' => $cantBuena,
                    'cantidad_faltante' => $cantFaltante,
                    'cantidad_danada' => $cantDanada,
                    'cantidad_sobrante' => $cantSobrante,
                    'tipo_incidencia' => $huboIncidencia ? 'DISCREPANCIA_DETECTADA' : null
                ]);

                if ($cantBuena > 0) {
                    $this->descontarStock($bodegaTransito, $producto->id, $cantBuena);
                    $this->incrementarStock($bodegaDestino, $producto->id, $cantBuena);
                    $sTransito = ProductoBodegaStock::where('producto_id', $producto->id)->where('bodega_id', $bodegaTransito->id)->value('stock_fisico');
                    $sDestino = ProductoBodegaStock::where('producto_id', $producto->id)->where('bodega_id', $bodegaDestino->id)->value('stock_fisico');
                    Kardex::create(['fecha_hora' => now(), 'producto_id' => $producto->id, 'bodega_id' => $bodegaTransito->id, 'tipo_movimiento' => $tipoEnum, 'documento_origen_tipo' => 'RegistroOperativo', 'documento_origen_id' => $mov08->id, 'numero_documento' => $mov08->numero, 'entrada' => 0, 'salida' => $cantBuena, 'saldo' => $sTransito, 'usuario_id' => $usuarioId]);
                    Kardex::create(['fecha_hora' => now(), 'producto_id' => $producto->id, 'bodega_id' => $bodegaDestino->id, 'tipo_movimiento' => $tipoEnum, 'documento_origen_tipo' => 'RegistroOperativo', 'documento_origen_id' => $mov08->id, 'numero_documento' => $mov08->numero, 'entrada' => $cantBuena, 'salida' => 0, 'saldo' => $sDestino, 'usuario_id' => $usuarioId]);
                }

                if ($cantDanada > 0) {
                    $this->descontarStock($bodegaTransito, $producto->id, $cantDanada);
                    $this->incrementarStock($bodegaIncidencia, $producto->id, $cantDanada);
                    $sTransitoD = ProductoBodegaStock::where('producto_id', $producto->id)->where('bodega_id', $bodegaTransito->id)->value('stock_fisico');
                    $sInciD = ProductoBodegaStock::where('producto_id', $producto->id)->where('bodega_id', $bodegaIncidencia->id)->value('stock_fisico');
                    Kardex::create(['fecha_hora' => now(), 'producto_id' => $producto->id, 'bodega_id' => $bodegaTransito->id, 'tipo_movimiento' => $tipoEnum, 'documento_origen_tipo' => 'RegistroOperativo', 'documento_origen_id' => $mov08->id, 'numero_documento' => $mov08->numero, 'entrada' => 0, 'salida' => $cantDanada, 'saldo' => $sTransitoD, 'usuario_id' => $usuarioId]);
                    Kardex::create(['fecha_hora' => now(), 'producto_id' => $producto->id, 'bodega_id' => $bodegaIncidencia->id, 'tipo_movimiento' => $tipoEnum, 'documento_origen_tipo' => 'RegistroOperativo', 'documento_origen_id' => $mov08->id, 'numero_documento' => $mov08->numero, 'entrada' => $cantDanada, 'salida' => 0, 'saldo' => $sInciD, 'usuario_id' => $usuarioId]);
                }

                if ($producto->tipo_control_inventario->value === 'LOTE') {
                    $lotesReales = $dr['lotes'] ?? [];
                    $sumBuena = array_sum(array_column($lotesReales, 'cantidad_buena'));
                    $sumFaltante = array_sum(array_column($lotesReales, 'cantidad_faltante'));
                    $sumDanada = array_sum(array_column($lotesReales, 'cantidad_danada'));
                    $sumSobrante = array_sum(array_column($lotesReales, 'cantidad_sobrante'));

                    if (abs($sumBuena - $cantBuena) > 0.000001 || abs($sumFaltante - $cantFaltante) > 0.000001 || abs($sumDanada - $cantDanada) > 0.000001 || abs($sumSobrante - $cantSobrante) > 0.000001) {
                        throw new InvalidWarehouseOperationException("Descuadre: La distribución en lotes no coincide con los totales del producto {$producto->codigo}.");
                    }
                    
                    $lotesOriginales = $detalleOriginal->lotes()->with('lote')->get()->pluck('lote.numero_lote')->toArray();

                    foreach ($lotesReales as $lData) {
                        if (!in_array($lData['numero_lote'], $lotesOriginales) && floatval($lData['cantidad_sobrante'] ?? 0) == 0) {
                            throw new InvalidWarehouseOperationException("El lote {$lData['numero_lote']} no fue despachado en origen.");
                        }
                        
                        $loteModel = Lote::where('producto_id', $producto->id)->where('numero_lote', $lData['numero_lote'])->first();
                        $cBuenaL = floatval($lData['cantidad_buena'] ?? 0);
                        $cDanadaL = floatval($lData['cantidad_danada'] ?? 0);

                        if ($cBuenaL > 0) {
                            $exT = ExistenciaLote::where(['bodega_id' => $bodegaTransito->id, 'lote_id' => $loteModel->id])->lockForUpdate()->first();
                            $exT->stock_fisico -= $cBuenaL; $exT->stock_disponible -= $cBuenaL; $exT->save();
                            $exD = ExistenciaLote::firstOrCreate(['bodega_id' => $bodegaDestino->id, 'lote_id' => $loteModel->id, 'producto_id' => $producto->id], ['stock_fisico' => 0, 'stock_reservado' => 0, 'stock_disponible' => 0]);
                            $exDLock = ExistenciaLote::where('id', $exD->id)->lockForUpdate()->first();
                            $exDLock->stock_fisico += $cBuenaL; $exDLock->stock_disponible += $cBuenaL; $exDLock->save();
                        }

                        if ($cDanadaL > 0) {
                            $exT = ExistenciaLote::where(['bodega_id' => $bodegaTransito->id, 'lote_id' => $loteModel->id])->lockForUpdate()->first();
                            $exT->stock_fisico -= $cDanadaL; $exT->stock_disponible -= $cDanadaL; $exT->save();
                            $exI = ExistenciaLote::firstOrCreate(['bodega_id' => $bodegaIncidencia->id, 'lote_id' => $loteModel->id, 'producto_id' => $producto->id], ['stock_fisico' => 0, 'stock_reservado' => 0, 'stock_disponible' => 0]);
                            $exILock = ExistenciaLote::where('id', $exI->id)->lockForUpdate()->first();
                            $exILock->stock_fisico += $cDanadaL; $exILock->stock_disponible += $cDanadaL; $exILock->save();
                        }
                        
                        $regDetalle->lotes()->create(['lote_id' => $loteModel->id, 'cantidad' => $cBuenaL]);
                    }
                }
                
                elseif ($producto->tipo_control_inventario->value === 'SERIE') {
                    $seriesReales = $dr['series'] ?? [];
                    $numerosOriginales = $detalleOriginal->series()->with('serie')->get()->pluck('serie.numero_serie')->toArray();
                    
                    if (count($seriesReales) !== count($numerosOriginales)) {
                        throw new InvalidWarehouseOperationException("El conteo de series (" . count($seriesReales) . ") no coincide con el despachado (" . count($numerosOriginales) . ").");
                    }

                    $countB = 0; $countF = 0; $countD = 0;
                    foreach ($seriesReales as $sData) {
                        if (!in_array($sData['numero_serie'], $numerosOriginales)) {
                            throw new InvalidWarehouseOperationException("La serie {$sData['numero_serie']} no fue despachada en origen.");
                        }
                        
                        $serieModel = Serie::where('producto_id', $producto->id)->where('numero_serie', $sData['numero_serie'])->first();
                        $estadoRec = $sData['estado_recepcion'] ?? 'BUENA'; 
                        
                        if ($estadoRec === 'BUENA') {
                            $serieModel->update(['bodega_actual_id' => $bodegaDestino->id, 'estado' => \App\Enums\EstadoSerie::DISPONIBLE]);
                            $countB++;
                        } elseif ($estadoRec === 'FALTANTE') {
                            $serieModel->update(['estado' => \App\Enums\EstadoSerie::FALTANTE]); 
                            $countF++;
                        } elseif ($estadoRec === 'DANADA') {
                            $serieModel->update(['bodega_actual_id' => $bodegaIncidencia->id, 'estado' => \App\Enums\EstadoSerie::DANADA]);
                            $countD++;
                        }
                        $regDetalle->series()->create(['serie_id' => $serieModel->id]);
                    }

                    if (abs($countB - $cantBuena) > 0.000001 || abs($countF - $cantFaltante) > 0.000001 || abs($countD - $cantDanada) > 0.000001) {
                        throw new InvalidWarehouseOperationException("El resumen de estados de las series individuales no coincide con los totales reportados para el producto {$producto->codigo}.");
                    }
                }
            }

            $mov08->update(['estado_operativo_recepcion' => $huboIncidencia ? \App\Enums\EstadoOperativoRecepcion::RECEPCION_CON_INCIDENCIA : \App\Enums\EstadoOperativoRecepcion::RECEPCION_CORRECTA]);
            $mov07->update(['estado' => \App\Enums\EstadoRegistroOperativo::CONFIRMADO]);

            return $mov08;
        });
    }
}




<?php

namespace App\Services;

use App\Exceptions\SriFirmaException;
use App\Models\{Cliente, Comprobante, User};
use Illuminate\Support\Facades\{Cache, DB, Log};
use Illuminate\Validation\ValidationException;

class InvoiceCorrectionService
{
    public function correct(User $actor, Comprobante $invoice, array $clientData, string $reason, ?string $ip): array
    {
        abort_unless(PermissionService::tienePermiso($actor, 'comprobantes.correct'), 403, 'Tu rol no permite corregir datos fiscales.');
        BusinessAccess::invoice($actor, $invoice);
        $lock = Cache::lock('sri-comprobante-'.$invoice->id, 180);
        abort_unless($lock->get(), 409, 'El comprobante se está procesando. Consulta su estado antes de corregirlo.');

        try {
            $invoice = DB::transaction(function () use ($actor, $invoice, $clientData, $reason, $ip) {
                $invoice = Comprobante::whereKey($invoice->id)->lockForUpdate()->firstOrFail();
                BusinessAccess::invoice($actor, $invoice);
                abort_unless(in_array($invoice->estado_sri, ['DEVUELTA', 'NO_AUTORIZADO'], true), 422,
                    'Solo se corrigen comprobantes devueltos o no autorizados. Consulta primero los estados pendientes.');

                $clientData['tipo_identificacion'] = ClientIdentification::normalize($clientData['tipo_identificacion']);
                $clientData['identificacion'] = trim($clientData['identificacion']);
                if (!ClientIdentification::valid($clientData['tipo_identificacion'], $clientData['identificacion'])) {
                    throw ValidationException::withMessages(['cliente.identificacion' => ['Identificación inválida para el tipo seleccionado.']]);
                }
                if ((float) $invoice->total > 50 && ($clientData['tipo_identificacion'] === 'CONSUMIDOR_FINAL' || $clientData['identificacion'] === '9999999999999')) {
                    throw ValidationException::withMessages(['cliente.identificacion' => ['Una factura superior a $50.00 requiere un comprador identificado.']]);
                }

                $previous = [
                    'cliente_snapshot' => $invoice->cliente_snapshot ?: $invoice->cliente?->getAttributes(),
                    'xml_generado' => $invoice->xml_generado,
                    'xml_firmado' => $invoice->xml_firmado,
                    'ultima_respuesta_recepcion' => $invoice->ultima_respuesta_recepcion,
                    'ultima_respuesta_autorizacion' => $invoice->ultima_respuesta_autorizacion,
                ];
                // Correct this document without changing other invoices or the existing customer master.
                $client = Cliente::where('emisor_id', $invoice->emisor_id)->where('identificacion', $clientData['identificacion'])->first();
                if (!$client) {
                    $client = Cliente::create(array_merge($clientData, [
                        'emisor_id' => $invoice->emisor_id, 'estado' => 'ACTIVO',
                        'created_by' => $actor->id, 'updated_by' => $actor->id,
                    ]));
                }
                $invoice->cliente_id = $client->id;
                $invoice->cliente_snapshot = array_merge($client->getAttributes(), $clientData);
                $generated = app(SriXmlGeneratorService::class)->generarXmlFactura(
                    $invoice->loadMissing(['detalles.impuestos.tipoImpuesto', 'impuestos.tipoImpuesto'])
                );
                $validation = app(SriXmlValidatorService::class)->validateFactura($generated['xml']);
                if (!$validation['valid']) {
                    throw ValidationException::withMessages(['cliente' => $validation['errors']]);
                }

                $invoice->forceFill([
                    'xml_generado' => $generated['xml'], 'clave_acceso' => $generated['clave_acceso'],
                    'revision_fiscal' => (int) $invoice->revision_fiscal + 1,
                    'xml_firmado' => null, 'xml_autorizado' => null, 'numero_autorizacion' => null, 'fecha_autorizacion' => null,
                    'sri_intentos_envio' => 0, 'sri_intentos_autorizacion' => 0, 'ultimo_error_sri' => null,
                    'sri_estado_recepcion' => null, 'sri_estado_autorizacion' => null,
                    'ultima_peticion_recepcion' => null, 'ultima_respuesta_recepcion' => null,
                    'ultima_peticion_autorizacion' => null, 'ultima_respuesta_autorizacion' => null,
                    'firmado_en' => null, 'enviado_en' => null, 'recibido_en' => null, 'autorizado_en' => null,
                ])->save();

                return app(SriComprobanteLifecycleService::class)->transition($invoice, 'BORRADOR', [], [
                    'etapa' => 'correccion_fiscal', 'codigo' => 'CORRECCION_FISCAL_CONTROLADA',
                    'mensaje' => 'Corrección fiscal por '.$actor->username.': '.$reason,
                    'detalles' => ['anterior' => $previous, 'cliente_nuevo' => $invoice->cliente_snapshot,
                        'motivo' => $reason, 'usuario_id' => $actor->id, 'ip' => $ip],
                ]);
            }, 3);

            $dispatched = false;
            try {
                $dispatched = app(InvoiceProcessingService::class)->dispatch($invoice);
            } catch (ValidationException|SriFirmaException $e) {
                $invoice->update(['ultimo_error_sri' => 'Corrección guardada. Configura una firma válida y reintenta el procesamiento.']);
            } catch (\Throwable $e) {
                $invoice->update(['ultimo_error_sri' => 'Corrección guardada. No se pudo programar el procesamiento; reintenta este comprobante.']);
                Log::warning('No se pudo programar la corrección fiscal.', ['comprobante_id' => $invoice->id, 'exception' => get_class($e)]);
            }

            return [
                'success' => true, 'despachado' => $dispatched, 'comprobante_id' => $invoice->id,
                'clave_acceso' => $invoice->clave_acceso, 'estado_sri' => $invoice->fresh()->estado_sri,
                'message' => $dispatched
                    ? 'Comprobante corregido y programado. No se repitió el cobro ni el movimiento de inventario.'
                    : ($invoice->fresh()->ultimo_error_sri ?: 'La corrección está guardada y ya existe una tarea programada para este comprobante.'),
            ];
        } finally {
            $lock->release();
        }
    }
}

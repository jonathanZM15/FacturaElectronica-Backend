<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Models\{Comprobante, User};
use App\Services\{BusinessAccess, PermissionService};
use Closure;
use Illuminate\Http\Request;

/** Authorizes the operation before controller validation or database mutations. */
class AuthorizeBusinessAction
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        [$class, $action] = array_pad(explode('@', $request->route()->getActionName()), 2, '');
        $controller = class_basename($class);
        $read = $request->isMethodSafe();
        $permissions = [
            'BodegaController' => $read ? 'inventario.read' : 'catalogo.write',
            'ProductoController' => $read ? 'productos.read' : 'catalogo.write',
            'CategoriaController' => $read ? 'productos.read' : 'catalogo.write',
            'ExistenciaInventarioController' => 'inventario.read',
            'KardexController' => 'kardex.read',
            'StockParametroController' => $read ? 'inventario.read' : 'inventario.manage',
            'MovimientoInventarioController' => 'inventario.manage',
            'TransferenciaController' => 'inventario.manage',
            'TransferenciaSucursalController' => $read ? 'inventario.read' : 'inventario.manage',
            'MotivoMovimientoController' => 'inventario.read',
            'ClienteController' => $read ? 'clientes.read' : ($action === 'destroy' ? 'clientes.delete' : 'clientes.write'),
            'ProveedorController' => $read ? 'proveedores.read' : 'proveedores.write',
            'TransportistaController' => $read ? 'proveedores.read' : 'proveedores.write',
            'CompraController' => $read ? 'inventario.read' : 'catalogo.write',
            'PosTurnoController' => 'pos.operate',
            'EstablecimientoController' => $read ? 'establecimientos.read' : 'establecimientos.update',
            'PuntoEmisionController' => $read ? 'puntos_emision.read' : 'puntos_emision.update',
            'EmisorController' => $read ? 'emisores.read' : ($action === 'store' ? 'emisores.create' : ($action === 'update' ? 'emisores.update' : 'emisores.delete')),
            'LogoController' => 'emisores.update',
            'FacturacionController' => match (true) {
                str_starts_with($action, 'emitir') => 'comprobantes.create',
                $action === 'reintentarProcesamiento' => 'comprobantes.retry',
                $action === 'corregirFiscal' => 'comprobantes.correct',
                $action === 'reenviarEmail' => 'comprobantes.email',
                default => $read ? 'comprobantes.read' : 'comprobantes.update',
            },
        ];
        if (isset($permissions[$controller])) {
            abort_unless(PermissionService::tienePermiso($user, $permissions[$controller]), 403, 'Tu rol no permite esta operación.');
        }
        if ($controller === 'CompanyDeletionController') abort_unless($user->role === UserRole::ADMINISTRADOR, 403);
        if ($controller === 'LogoController') BusinessAccess::company($user, (int) $request->route('company'));
        if ($controller === 'UserController' && !in_array($action, ['requestEmailChange'], true)) {
            abort_unless(PermissionService::tienePermiso($user, $read ? 'users.read' : 'users.update'), 403, 'Tu rol no permite gestionar usuarios.');
        }

        $companyId = null;
        if (preg_match('#^api/emisores/\{([^}]+)\}#', $request->route()->uri(), $match)) {
            $companyId = (int) $request->route($match[1]);
            BusinessAccess::company($user, $companyId);
        }
        if ($companyId) {
            if ($request->filled('motivo_id')) {
                $reason = \App\Models\MotivoMovimiento::findOrFail($request->input('motivo_id'));
                abort_if(($reason->emisor_id && (int) $reason->emisor_id !== $companyId) || !$reason->activo, 422, 'Motivo no disponible para esta empresa.');
            }
            if ($est = $request->route('est')) {
                BusinessAccess::establishment($user, (int) $est, $companyId);
            }
            if ($point = $request->route('punto')) {
                $resolvedPoint = BusinessAccess::point($user, (int) $point, $companyId);
                abort_if($est && (int) $resolvedPoint->establecimiento_id !== (int) $est, 404);
            }
            foreach (['bodega_id', 'bodega_origen_id', 'bodega_destino_id'] as $field) {
                if ($request->filled($field) && is_numeric($request->input($field))) {
                    BusinessAccess::warehouse($user, (int) $request->input($field), $companyId);
                }
            }
            if ($controller === 'BodegaController' && $request->route('id')) {
                BusinessAccess::warehouse($user, (int) $request->route('id'), $companyId);
            }
            if ($request->filled('establecimiento_id') && is_numeric($request->input('establecimiento_id'))) {
                BusinessAccess::establishment($user, (int) $request->input('establecimiento_id'), $companyId);
            }
            if ($request->filled('punto_emision_id') && is_numeric($request->input('punto_emision_id'))) {
                BusinessAccess::point($user, (int) $request->input('punto_emision_id'), $companyId);
            }
        }
        if ($controller === 'FacturacionController' && ($invoice = $request->route('comprobante')) instanceof Comprobante) {
            BusinessAccess::invoice($user, $invoice);
        }
        if ($controller === 'UserController' && ($id = $request->route('usuario'))) {
            $target = User::findOrFail($id);
            $selfEmail = $action === 'requestEmailChange' && (int) $user->id === (int) $target->id;
            abort_unless($selfEmail || PermissionService::puedeGestionarUsuario($user, $target), 403, 'No puedes gestionar este usuario.');
            abort_if($companyId && (int) $target->emisor_id !== $companyId, 404);
            if ($request->has('emisor_id')) {
                abort_if((int) $request->input('emisor_id') !== (int) $target->emisor_id, 422, 'No se puede trasladar una cuenta a otra empresa desde esta operación.');
            }
        }
        $extendedInventory = in_array($controller, ['TransferenciaController', 'TransferenciaSucursalController'], true)
            || ($controller === 'MovimientoInventarioController' && in_array($action, ['enviarMermas', 'reacondicionar'], true));
        $otherDocument = $controller === 'FacturacionController' && str_starts_with($action, 'emitir') && $action !== 'emitirFactura';
        abort_if($extendedInventory && !config('features.v1_extended_inventory'), 501, 'Movimiento ampliado pendiente de una entrega posterior a V1.');
        abort_if($otherDocument && !config('features.v1_other_documents'), 501, 'Tipo de comprobante pendiente de una entrega posterior a V1.');
        abort_if($controller === 'CompraController' && !config('features.v1_purchases'), 501, 'Compras está pendiente de una entrega posterior a V1.');
        return $next($request);
    }
}

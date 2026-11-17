<?php

namespace App\Console\Commands;

use App\Models\{Bodega, Company, Producto};
use Illuminate\Console\Command;

class RevisarDatosPrimeraVersionCommand extends Command
{
    protected $signature = 'v1:revisar-datos {--emisor= : ID de la empresa a revisar} {--json : Mostrar resultados estructurados}';
    protected $description = 'Detecta datos históricos incompatibles con V1; no cambia, elimina ni emite registros';

    public function handle(): int
    {
        $id = $this->option('emisor');
        if ($id !== null && (!ctype_digit((string) $id) || !Company::whereKey($id)->exists())) {
            $this->error('Indica un ID de empresa existente.');
            return self::INVALID;
        }
        $products = Producto::query()->when($id !== null, fn ($q) => $q->where('emisor_id', $id));
        $warehouses = Bodega::query()->when($id !== null, fn ($q) => $q->whereHas('establecimiento', fn ($e) => $e->where('emisor_id', $id)));
        $checks = [];
        $add = function (string $control, $query, string $action) use (&$checks): void {
            $checks[] = ['control' => $control, 'cantidad' => (clone $query)->count(), 'ids_muestra' => (clone $query)->orderBy('id')->limit(20)->pluck('id')->all(), 'accion' => $action];
        };
        $add('Códigos fiscales de más de 25 caracteres', (clone $products)->where(fn ($q) => $q->whereRaw('length(codigo) > 25')->orWhereRaw('length(codigo_auxiliar) > 25')), 'Revisar y asignar códigos únicos válidos en el catálogo. No truncar automáticamente ni reescribir facturas anteriores.');
        $add('Precios negativos', (clone $products)->where(fn ($q) => $q->where('precio_1', '<', 0)->orWhere('precio_2', '<', 0)->orWhere('precio_3', '<', 0)), 'Corregir el catálogo con el responsable comercial.');
        $add('Productos vendibles con ICE o IRBPNR', (clone $products)->where('permite_venta', true)->where(fn ($q) => $q->where('impuesto_ice', true)->orWhere('irbpnr', '>', 0)), 'Estos impuestos requieren el flujo ampliado, fuera de V1. Confirmar el alcance real del emisor.');
        $add('Bodegas con permiso de venta incompatible', (clone $warehouses)->where('permite_venta', true)->whereNotIn('tipo', ['VENTA', 'ALMACEN']), 'Revisar con el responsable de inventario. No trasladar ni borrar existencias automáticamente.');
        if ($this->option('json')) $this->line(json_encode(['solo_lectura' => true, 'controles' => $checks], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        else $this->table(['Control', 'Cantidad', 'Acción'], array_map(fn ($c) => [$c['control'], $c['cantidad'], $c['accion']], $checks));
        return collect($checks)->every(fn ($c) => $c['cantidad'] === 0) ? self::SUCCESS : self::FAILURE;
    }
}

<?php

namespace Tests\Feature;

use App\Models\Bodega;
use App\Models\Producto;
use App\Models\ProductoBodegaStock;
use App\Services\ProductoStockService;
use App\Enums\TipoProducto;
use App\Enums\TipoControlInventario;
use App\Enums\TipoBodega;
use App\Exceptions\InvalidInventoryControlException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use App\Models\User;

class InventarioStockInicialTest extends TestCase
{
    use RefreshDatabase;

    protected ProductoStockService $servicio;
    protected int $emisorId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->servicio = app(ProductoStockService::class);

        $this->emisorId = DB::table('emisores')->insertGetId([
            'name' => 'Empresa Test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@test.com',
            'password' => bcrypt('password'),
        ]);

        $this->actingAs($user);
    }

    private function crearProducto(array $overrides = []): Producto
    {
        static $counter = 0;
        $counter++;

        return Producto::create(array_merge([
            'emisor_id' => $this->emisorId,
            'codigo' => 'PROD-' . str_pad($counter, 4, '0', STR_PAD_LEFT),
            'nombre' => 'Producto Test ' . $counter,
            'precio_1' => 10.000000,
            'tipo_iva' => '12%',
            'tipo' => TipoProducto::FISICO,
            'tipo_control_inventario' => TipoControlInventario::CANTIDAD,
            'permite_venta' => true,
            'permite_compra' => true,
            'uso_interno' => false,
            'permite_exhibicion' => true,
        ], $overrides));
    }

    private function crearBodega(string $nombre = 'Bodega Central', TipoBodega $tipo = TipoBodega::ALMACEN): Bodega
    {
        return Bodega::create([
            'emisor_id' => $this->emisorId,
            'nombre' => $nombre,
            'tipo' => $tipo,
        ]);
    }

    public function test_stock_inicial_cantidad_persists()
    {
        $bodega = $this->crearBodega();
        $producto = $this->crearProducto();

        $this->servicio->procesarStockInicial($producto, [
            'bodega_destino_id' => $bodega->id,
            'cantidad' => 10,
        ]);

        $this->assertDatabaseHas('producto_bodega_stock', [
            'producto_id' => $producto->id,
            'bodega_id' => $bodega->id,
        ]);

        $stock = ProductoBodegaStock::where('producto_id', $producto->id)
            ->where('bodega_id', $bodega->id)->first();

        $this->assertEquals(10.0, (float) $stock->stock_fisico);
        $this->assertEquals(10.0, (float) $stock->stock_disponible);
    }

    public function test_stock_inicial_lote_creates_details()
    {
        $bodega = $this->crearBodega();
        $producto = $this->crearProducto([
            'tipo_control_inventario' => TipoControlInventario::LOTE,
        ]);

        $this->servicio->procesarStockInicial($producto, [
            'bodega_destino_id' => $bodega->id,
            'lotes' => [
                ['codigo_lote' => 'LOTE01', 'cantidad_lote' => 5, 'fecha_vencimiento' => '2027-12-31'],
                ['codigo_lote' => 'LOTE02', 'cantidad_lote' => 3, 'fecha_vencimiento' => '2027-12-31'],
            ],
        ]);

        $this->assertDatabaseHas('movimiento_inventario_detalles', [
            'producto_id' => $producto->id,
            'codigo_lote' => 'LOTE01',
        ]);

        $this->assertDatabaseHas('movimiento_inventario_detalles', [
            'producto_id' => $producto->id,
            'codigo_lote' => 'LOTE02',
        ]);

        $stock = ProductoBodegaStock::where('producto_id', $producto->id)
            ->where('bodega_id', $bodega->id)->first();

        $this->assertEquals(8.0, (float) $stock->stock_fisico);
        $this->assertEquals(8.0, (float) $stock->stock_disponible);
    }

    public function test_stock_inicial_serie_creates_one_per_serie()
    {
        $bodega = $this->crearBodega();
        $producto = $this->crearProducto([
            'tipo_control_inventario' => TipoControlInventario::SERIE,
        ]);

        $this->servicio->procesarStockInicial($producto, [
            'bodega_destino_id' => $bodega->id,
            'cantidad' => 2,
            'series' => [
                ['numero_serie' => 'S001'],
                ['numero_serie' => 'S002'],
            ],
        ]);

        $this->assertDatabaseCount('movimiento_inventario_detalles', 2);

        $this->assertDatabaseHas('movimiento_inventario_detalles', [
            'producto_id' => $producto->id,
            'numero_serie' => 'S001',
        ]);

        $this->assertDatabaseHas('movimiento_inventario_detalles', [
            'producto_id' => $producto->id,
            'numero_serie' => 'S002',
        ]);
    }

    public function test_servicio_cannot_have_stock()
    {
        $producto = $this->crearProducto([
            'tipo' => TipoProducto::SERVICIO,
            'tipo_control_inventario' => TipoControlInventario::SIN_CONTROL,
        ]);

        $this->expectException(InvalidInventoryControlException::class);

        $this->servicio->procesarStockInicial($producto, [
            'bodega_destino_id' => 1,
            'cantidad' => 10,
        ]);
    }

    public function test_sin_control_cannot_have_stock()
    {
        $producto = $this->crearProducto([
            'tipo_control_inventario' => TipoControlInventario::SIN_CONTROL,
        ]);

        $this->expectException(InvalidInventoryControlException::class);

        $this->servicio->procesarStockInicial($producto, [
            'bodega_destino_id' => 1,
            'cantidad' => 10,
        ]);
    }
}

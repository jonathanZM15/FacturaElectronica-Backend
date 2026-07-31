<?php

namespace Tests\Feature;

use App\Models\Bodega;
use App\Models\Producto;
use App\Models\ProductoBodegaStock;
use App\Models\User;
use App\Services\MovimientoInventarioService;
use App\Enums\TipoProducto;
use App\Enums\TipoControlInventario;
use App\Enums\TipoBodega;
use App\Exceptions\InvalidWarehouseOperationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MovimientoInventarioTest extends TestCase
{
    use RefreshDatabase;

    protected MovimientoInventarioService $servicio;
    protected int $emisorId;
    protected int $usuarioId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->servicio = app(MovimientoInventarioService::class);

        $this->emisorId = DB::table('emisores')->insertGetId([
            'name' => 'Empresa Test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = User::create([
            'name' => 'Test User',
            'email' => 'movtest@test.com',
            'password' => bcrypt('password'),
        ]);

        $this->usuarioId = $user->id;
        $this->actingAs($user);
    }

    private function crearProducto(): Producto
    {
        static $counter = 0;
        $counter++;

        return Producto::create([
            'emisor_id' => $this->emisorId,
            'codigo' => 'MOV-' . str_pad($counter, 4, '0', STR_PAD_LEFT),
            'nombre' => 'Producto Mov ' . $counter,
            'precio_1' => 10.000000,
            'tipo_iva' => '12%',
            'tipo' => TipoProducto::FISICO,
            'tipo_control_inventario' => TipoControlInventario::CANTIDAD,
            'permite_venta' => true,
            'permite_compra' => true,
            'uso_interno' => false,
            'permite_exhibicion' => true,
        ]);
    }

    private function crearBodega(string $nombre, TipoBodega $tipo): Bodega
    {
        return Bodega::create([
            'emisor_id' => $this->emisorId,
            'nombre' => $nombre,
            'tipo' => $tipo,
        ]);
    }

    private function crearStock(int $productoId, int $bodegaId, float $fisico, float $disponible = null, float $reservado = 0): void
    {
        ProductoBodegaStock::create([
            'producto_id' => $productoId,
            'bodega_id' => $bodegaId,
            'stock_fisico' => $fisico,
            'stock_disponible' => $disponible ?? $fisico,
            'stock_reservado' => $reservado,
        ]);
    }

    public function test_transferencia_descuenta_origen_incrementa_destino()
    {
        $bodegaA = $this->crearBodega('Almacen A', TipoBodega::ALMACEN);
        $bodegaB = $this->crearBodega('Almacen B', TipoBodega::ALMACEN);
        $producto = $this->crearProducto();

        $this->crearStock($producto->id, $bodegaA->id, 10);

        $this->servicio->transferir($bodegaA, $bodegaB, [
            ['producto_id' => $producto->id, 'cantidad' => 5]
        ], 'Transferencia normal', $this->usuarioId);

        $stockA = ProductoBodegaStock::where('producto_id', $producto->id)
            ->where('bodega_id', $bodegaA->id)->first();
        $stockB = ProductoBodegaStock::where('producto_id', $producto->id)
            ->where('bodega_id', $bodegaB->id)->first();

        $this->assertEquals(5.0, (float) $stockA->stock_fisico);
        $this->assertEquals(5.0, (float) $stockB->stock_fisico);
    }

    public function test_transferencia_mermas_sin_observacion_falla()
    {
        $bodegaA = $this->crearBodega('Almacen', TipoBodega::ALMACEN);
        $bodegaMermas = $this->crearBodega('Mermas', TipoBodega::MERMAS);

        $this->expectException(InvalidWarehouseOperationException::class);

        $this->servicio->transferir($bodegaA, $bodegaMermas, [], '', $this->usuarioId);
    }

    public function test_salida_normal_desde_mermas_bloqueada()
    {
        $bodegaMermas = $this->crearBodega('Mermas', TipoBodega::MERMAS);
        $bodegaA = $this->crearBodega('Almacen', TipoBodega::ALMACEN);

        $this->expectException(InvalidWarehouseOperationException::class);

        $this->servicio->transferir($bodegaMermas, $bodegaA, [], 'Test', $this->usuarioId);
    }

    public function test_reacondicionado_mermas_a_venta_funciona()
    {
        $bodegaMermas = $this->crearBodega('Mermas', TipoBodega::MERMAS);
        $bodegaVenta = $this->crearBodega('Venta', TipoBodega::VENTA);
        $producto = $this->crearProducto();

        $this->crearStock($producto->id, $bodegaMermas->id, 5);

        $this->servicio->transferirReacondicionado($bodegaMermas, $bodegaVenta, [
            ['producto_id' => $producto->id, 'cantidad' => 5]
        ], 'Reparado', $this->usuarioId);

        $stockMermas = ProductoBodegaStock::where('producto_id', $producto->id)
            ->where('bodega_id', $bodegaMermas->id)->first();
        $stockVenta = ProductoBodegaStock::where('producto_id', $producto->id)
            ->where('bodega_id', $bodegaVenta->id)->first();

        $this->assertEquals(0.0, (float) $stockMermas->stock_fisico);
        $this->assertEquals(5.0, (float) $stockVenta->stock_fisico);
    }

    public function test_ajuste_positivo_sin_justificacion_falla()
    {
        $bodega = $this->crearBodega('Almacen', TipoBodega::ALMACEN);

        $this->expectException(InvalidWarehouseOperationException::class);

        $this->servicio->ajustarPositivo($bodega, [], '', $this->usuarioId);
    }

    public function test_ajuste_negativo_stock_insuficiente_falla()
    {
        $bodega = $this->crearBodega('Almacen', TipoBodega::ALMACEN);
        $producto = $this->crearProducto();

        $this->crearStock($producto->id, $bodega->id, 2);

        $this->expectException(InvalidWarehouseOperationException::class);

        $this->servicio->ajustarNegativo($bodega, [
            ['producto_id' => $producto->id, 'cantidad' => 5]
        ], 'Pérdida', $this->usuarioId);
    }

    public function test_consulta_stock_disponible()
    {
        $bodega = $this->crearBodega('Almacen', TipoBodega::ALMACEN);
        $producto = $this->crearProducto();

        $this->crearStock($producto->id, $bodega->id, 10);

        $response = $this->getJson("/api/emisores/{$this->emisorId}/productos/{$producto->id}/stock-disponible");
        $response->assertStatus(200);
    }
}

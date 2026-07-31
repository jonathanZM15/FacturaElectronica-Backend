<?php

namespace Tests\Feature;

use App\Models\Bodega;
use App\Models\Producto;
use App\Models\ProductoBodegaStock;
use App\Services\StockReservationService;
use App\Enums\TipoProducto;
use App\Enums\TipoControlInventario;
use App\Enums\TipoBodega;
use App\Exceptions\InvalidWarehouseOperationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StockReservationTest extends TestCase
{
    use RefreshDatabase;

    protected StockReservationService $servicio;
    protected int $emisorId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->servicio = app(StockReservationService::class);

        $this->emisorId = DB::table('emisores')->insertGetId([
            'name' => 'Empresa Test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function crearProducto(): Producto
    {
        static $counter = 0;
        $counter++;

        return Producto::create([
            'emisor_id' => $this->emisorId,
            'codigo' => 'RES-' . str_pad($counter, 4, '0', STR_PAD_LEFT),
            'nombre' => 'Producto Reserva ' . $counter,
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

    public function test_reservar_reduce_disponible_no_fisico()
    {
        $bodega = $this->crearBodega('Almacen', TipoBodega::ALMACEN);
        $producto = $this->crearProducto();

        ProductoBodegaStock::create([
            'producto_id' => $producto->id,
            'bodega_id' => $bodega->id,
            'stock_fisico' => 10,
            'stock_disponible' => 10,
            'stock_reservado' => 0,
        ]);

        $this->servicio->reservar($bodega, $producto->id, 3);

        $stock = ProductoBodegaStock::where('producto_id', $producto->id)
            ->where('bodega_id', $bodega->id)->first();

        $this->assertEquals(10.0, (float) $stock->stock_fisico);
        $this->assertEquals(7.0, (float) $stock->stock_disponible);
        $this->assertEquals(3.0, (float) $stock->stock_reservado);
    }

    public function test_confirmar_reserva_reduce_fisico_y_reservado()
    {
        $bodega = $this->crearBodega('Almacen', TipoBodega::ALMACEN);
        $producto = $this->crearProducto();

        ProductoBodegaStock::create([
            'producto_id' => $producto->id,
            'bodega_id' => $bodega->id,
            'stock_fisico' => 10,
            'stock_disponible' => 7,
            'stock_reservado' => 3,
        ]);

        $this->servicio->confirmarReserva($bodega, $producto->id, 3);

        $stock = ProductoBodegaStock::where('producto_id', $producto->id)
            ->where('bodega_id', $bodega->id)->first();

        $this->assertEquals(7.0, (float) $stock->stock_fisico);
        $this->assertEquals(7.0, (float) $stock->stock_disponible);
        $this->assertEquals(0.0, (float) $stock->stock_reservado);
    }

    public function test_liberar_reserva_restaura_disponible()
    {
        $bodega = $this->crearBodega('Almacen', TipoBodega::ALMACEN);
        $producto = $this->crearProducto();

        ProductoBodegaStock::create([
            'producto_id' => $producto->id,
            'bodega_id' => $bodega->id,
            'stock_fisico' => 10,
            'stock_disponible' => 7,
            'stock_reservado' => 3,
        ]);

        $this->servicio->liberarReserva($bodega, $producto->id, 3);

        $stock = ProductoBodegaStock::where('producto_id', $producto->id)
            ->where('bodega_id', $bodega->id)->first();

        $this->assertEquals(10.0, (float) $stock->stock_fisico);
        $this->assertEquals(10.0, (float) $stock->stock_disponible);
        $this->assertEquals(0.0, (float) $stock->stock_reservado);
    }

    public function test_reservar_mas_que_disponible_falla()
    {
        $bodega = $this->crearBodega('Almacen', TipoBodega::ALMACEN);
        $producto = $this->crearProducto();

        ProductoBodegaStock::create([
            'producto_id' => $producto->id,
            'bodega_id' => $bodega->id,
            'stock_fisico' => 5,
            'stock_disponible' => 5,
            'stock_reservado' => 0,
        ]);

        $this->expectException(InvalidWarehouseOperationException::class);

        $this->servicio->reservar($bodega, $producto->id, 8);
    }

    public function test_bodega_exhibicion_no_permite_reservas()
    {
        $bodega = $this->crearBodega('Exhibicion', TipoBodega::EXHIBICION);

        $this->expectException(InvalidWarehouseOperationException::class);

        $this->servicio->reservar($bodega, 1, 1);
    }
}

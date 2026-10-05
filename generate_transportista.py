import os
import glob

backend_path = r"C:\Users\CompuStore\Desktop\dos sistemas\tesis\FacturaElectronica-Backend"

# 1. MIGRATION
migrations = glob.glob(os.path.join(backend_path, "database", "migrations", "*_create_transportistas_table.php"))
if migrations:
    migration_file = migrations[0]
    migration_code = """<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transportistas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('emisor_id')->constrained('emisores');
            $table->string('tipo_identificacion', 10);
            $table->string('identificacion', 20);
            $table->string('razon_social', 300);
            $table->string('email')->nullable();
            $table->string('telefono', 50)->nullable();
            $table->string('placa_vehiculo', 20)->nullable();
            
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->unique(['emisor_id', 'identificacion']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transportistas');
    }
};
"""
    with open(migration_file, 'w', encoding='utf-8') as f:
        f.write(migration_code)

# 2. MODEL
model_code = """<?php

namespace App\\Models;

use Illuminate\\Database\\Eloquent\\Model;
use Illuminate\\Database\\Eloquent\\Factories\\HasFactory;

class Transportista extends Model
{
    use HasFactory;

    protected $table = 'transportistas';

    protected $fillable = [
        'emisor_id',
        'tipo_identificacion',
        'identificacion',
        'razon_social',
        'email',
        'telefono',
        'placa_vehiculo',
        'created_by',
        'updated_by',
    ];

    public function emisor()
    {
        return $this->belongsTo(Company::class, 'emisor_id');
    }
}
"""
with open(os.path.join(backend_path, "app", "Models", "Transportista.php"), 'w', encoding='utf-8') as f:
    f.write(model_code)

# 3. CONTROLLER
controller_code = """<?php

namespace App\\Http\\Controllers;

use App\\Models\\Transportista;
use Illuminate\\Http\\JsonResponse;
use Illuminate\\Http\\Request;
use Illuminate\\Support\\Facades\\Auth;

class TransportistaController extends Controller
{
    public function index(Request $request, $emisorId): JsonResponse
    {
        $q = Transportista::where('emisor_id', $emisorId);

        if ($request->has('search')) {
            $search = $request->get('search');
            $q->where(function ($query) use ($search) {
                $query->where('identificacion', 'like', "%{$search}%")
                      ->orWhere('razon_social', 'like', "%{$search}%");
            });
        }

        $transportistas = $q->orderBy('razon_social')
            ->paginate($request->get('per_page', 15));
            
        return response()->json($transportistas);
    }

    public function store(Request $request, $emisorId): JsonResponse
    {
        $data = $request->validate([
            'tipo_identificacion' => ['required', 'string', 'max:10'],
            'identificacion' => ['required', 'string', 'max:20'],
            'razon_social' => ['required', 'string', 'max:300'],
            'email' => ['nullable', 'email'],
            'telefono' => ['nullable', 'string', 'max:50'],
            'placa_vehiculo' => ['nullable', 'string', 'max:20'],
        ]);

        $data['emisor_id'] = $emisorId;
        $data['created_by'] = Auth::id();
        $data['updated_by'] = Auth::id();

        $transportista = Transportista::create($data);

        return response()->json($transportista, 201);
    }

    public function show($emisorId, $id): JsonResponse
    {
        $transportista = Transportista::where('emisor_id', $emisorId)->findOrFail($id);
        return response()->json($transportista);
    }

    public function update(Request $request, $emisorId, $id): JsonResponse
    {
        $transportista = Transportista::where('emisor_id', $emisorId)->findOrFail($id);

        $data = $request->validate([
            'tipo_identificacion' => ['required', 'string', 'max:10'],
            'identificacion' => ['required', 'string', 'max:20'],
            'razon_social' => ['required', 'string', 'max:300'],
            'email' => ['nullable', 'email'],
            'telefono' => ['nullable', 'string', 'max:50'],
            'placa_vehiculo' => ['nullable', 'string', 'max:20'],
        ]);

        $data['updated_by'] = Auth::id();

        $transportista->update($data);

        return response()->json($transportista);
    }

    public function destroy($emisorId, $id): JsonResponse
    {
        $transportista = Transportista::where('emisor_id', $emisorId)->findOrFail($id);
        $transportista->delete();
        return response()->json(null, 204);
    }
}
"""
with open(os.path.join(backend_path, "app", "Http", "Controllers", "TransportistaController.php"), 'w', encoding='utf-8') as f:
    f.write(controller_code)

# 4. APPEND ROUTE
routes_file = os.path.join(backend_path, "routes", "api.php")
with open(routes_file, 'r', encoding='utf-8') as f:
    routes = f.read()
if "Route::apiResource('emisores.transportistas'" not in routes:
    routes = routes.replace(
        "Route::apiResource('emisores.proveedores', App\Http\Controllers\ProveedorController::class);",
        "Route::apiResource('emisores.proveedores', App\Http\Controllers\ProveedorController::class);\n    Route::apiResource('emisores.transportistas', App\Http\Controllers\TransportistaController::class);"
    )
    with open(routes_file, 'w', encoding='utf-8') as f:
        f.write(routes)

print("Backend updated.")

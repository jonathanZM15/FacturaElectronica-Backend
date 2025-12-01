<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Crear usuario admin para testing
        User::create([
            'name' => 'Admin Factura',
            'email' => 'admin@factura.local',
            'password' => bcrypt('admin123'),
            'role' => 'administrador',
            'estado' => 'activo',
        ]);

        // Usuario de prueba - sin factory
        User::create([
            'name' => 'Test Cajero',
            'email' => 'cajero@test.local',
            'password' => bcrypt('test123'),
            'role' => 'cajero',
            'estado' => 'activo',
        ]);
    }
}

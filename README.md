# 🧾 Factura Electrónica - Backend

API REST para el sistema de facturación electrónica **Máximo Facturas**, desarrollado con Laravel 12.

## 📋 Descripción

Backend que gestiona la lógica de negocio para la emisión, gestión y administración de facturas electrónicas, incluyendo:

- 🔐 Autenticación y autorización de usuarios con Laravel Sanctum
- 👥 Gestión de usuarios con roles (Admin, Gerente, Cajero)
- 🏢 Administración de emisores y puntos de emisión
- 📄 Generación y gestión de facturas electrónicas
- 💰 Gestión de retenciones
- 📧 Sistema de verificación de email y recuperación de contraseña
- 🖼️ Manejo de logos e imágenes con Intervention Image

## 🛠️ Tecnologías

- **PHP** ^8.2
- **Laravel** ^12.0
- **Laravel Sanctum** ^4.2 (Autenticación API)
- **Intervention Image** ^3.11 (Procesamiento de imágenes)
- **MySQL/MariaDB** (Base de datos)

## 📦 Requisitos Previos

- PHP >= 8.2
- Composer
- MySQL o MariaDB
- Node.js y NPM (opcional, para assets)

## 🚀 Instalación

1. **Clonar el repositorio**
   ```bash
   git clone https://github.com/jonathanZM15/FacturaElectronica-Backend.git
   cd FacturaElectronica-Backend
   ```

2. **Instalar dependencias**
   ```bash
   composer install
   ```

3. **Configurar entorno**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. **Configurar base de datos** en `.env`
   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=factura_electronica
   DB_USERNAME=tu_usuario
   DB_PASSWORD=tu_contraseña
   ```

5. **Ejecutar migraciones**
   ```bash
   php artisan migrate
   ```

6. **Crear enlace simbólico para storage**
   ```bash
   php artisan storage:link
   ```

7. **Iniciar servidor de desarrollo**
   ```bash
   php artisan serve
   ```

El backend estará disponible en `http://localhost:8000`

## 📁 Estructura del Proyecto

```
app/
├── Enums/          # Enumeraciones (Estados, Tipos)
├── Http/
│   ├── Controllers/  # Controladores de la API
│   └── Middleware/   # Middleware personalizado
├── Mail/           # Clases de correo electrónico
├── Models/         # Modelos Eloquent
├── Providers/      # Proveedores de servicios
└── Services/       # Servicios de negocio

config/             # Archivos de configuración
database/
├── migrations/     # Migraciones de base de datos
└── seeders/        # Seeders para datos iniciales
routes/
├── api.php         # Rutas de la API REST
└── web.php         # Rutas web
```

## 🔗 API Endpoints Principales

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| POST | `/api/login` | Iniciar sesión |
| POST | `/api/logout` | Cerrar sesión |
| GET | `/api/usuarios` | Listar usuarios |
| POST | `/api/usuarios` | Crear usuario |
| GET | `/api/emisores` | Listar emisores |
| GET | `/api/facturas` | Listar facturas |
| POST | `/api/facturas` | Crear factura |
| GET | `/api/retenciones` | Listar retenciones |

## 🔐 Roles de Usuario

- **Administrador**: Acceso completo al sistema
- **Gerente**: Gestión de emisores y reportes
- **Cajero**: Emisión de facturas y retenciones

## 📧 Configuración de Email

Configurar las credenciales SMTP en `.env`:
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_SCHEME=null
MAIL_USERNAME=tu_email
MAIL_PASSWORD=tu_contraseña
MAIL_FROM_ADDRESS=noreply@tudominio.com
MAIL_FROM_NAME="Máximo Facturas"
```

Nota:
- Este proyecto usa `MAIL_SCHEME` / `MAIL_URL` (ver `config/mail.php`), no `MAIL_ENCRYPTION`.
- Si tu red bloquea el puerto 587 (STARTTLS), prueba con 465 (SMTPS): `MAIL_PORT=465` y `MAIL_SCHEME=smtps`.

## 🧪 Tests

```bash
php artisan test
```

## 📄 Licencia

Este proyecto está bajo la Licencia MIT.

## 👥 Autores

Desarrollado para **Máximo Facturas**

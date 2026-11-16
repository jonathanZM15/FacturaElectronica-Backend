# Optimización de rendimiento de la primera versión

Revisión local del 15 de noviembre de 2026. Se conservaron los cinco roles, sus permisos y las operaciones existentes. No hay cambios de esquema ni migraciones para esta mejora. Las pruebas y los certificados sintéticos se mantienen fuera de los repositorios.

## Cambios realizados

- Productos resuelve los impuestos activos por colección y precarga los establecimientos de las bodegas del listado completo. El resultado resuelto es interno y no añade campos al JSON. Se mantienen los casos de impuestos antiguos, ambiguos, inactivos y no válidos; no hay caché de impuestos compartida entre peticiones.
- El catálogo completo sin parámetros mantiene sus campos y relaciones. Se comparó el JSON de 1.000 productos antes y después: idéntico. Las consultas SQL bajaron de 2.008 a 10 en esa solicitud.
- La gestión web solicita diez productos por página desde el backend, con búsqueda en todo el catálogo por código, código auxiliar o nombre. Los datos del producto necesarios para editar se conservan. Se controlan respuestas atrasadas, cambios de empresa, errores temporales y páginas que dejan de existir después de una eliminación.
- Los selectores y el POS solicitan `vista=selector`: conservan todos los productos y sus atributos fiscales y comerciales, sin repetir las relaciones de categorías y bodegas que no utilizan. No se limitaron a la primera página.
- El frontend carga las pantallas por módulos. Durante la descarga o un error de módulo se mantiene el menú de la zona autenticada. Se ofrecen estados de carga y recuperación sin cambiar los permisos de las rutas.
- Se añadieron reglas Apache de compresión de JavaScript, CSS y JSON, y caché prolongada sólo para JS/CSS con huella en su nombre. El HTML y el manifiesto del frontend requieren revalidación.

## Contratos de la API de productos

Las rutas conservan autenticación, permisos y aislamiento por empresa y sucursal.

| Solicitud | Respuesta |
| --- | --- |
| `GET /api/emisores/{id}/productos` | Listado completo compatible con el formato anterior, incluidas sus relaciones. |
| `GET /api/emisores/{id}/productos?page=1&per_page=10` | Paginador con `data`, `current_page`, `last_page`, `total` y los demás campos de Laravel. Incluye categoría e impuesto; no carga bodegas que la tabla de gestión no usa. |
| `GET /api/emisores/{id}/productos?page=1&per_page=10&search=P1000` | Búsqueda en el catálogo completo del emisor antes de paginar. |
| `GET /api/emisores/{id}/productos?vista=selector` | Todos los productos con sus atributos e impuesto, sin las relaciones completas de categoría y bodegas. |

`page` debe ser al menos 1, `per_page` admite entre 1 y 100 y `search` hasta 150 caracteres. Los parámetros no válidos responden 422. El término `0` se busca correctamente. Una página fuera de rango devuelve el paginador vacío; la interfaz vuelve a la última página válida.

El listado completo sigue existiendo para no romper consumidores anteriores. Para catálogos grandes se recomienda usar paginación o selectores adecuados. Esta compatibilidad implica que una petición antigua todavía puede transferir un catálogo grande.

## Validación realizada

- Backend: 188 pruebas y 1.055 aserciones aprobadas sobre PostgreSQL aislado. Incluyen impuestos, permisos, ventas, inventario, lotes, series, recuperación y las nuevas comprobaciones de catálogo/paginación.
- Frontend: 40 pruebas aprobadas. Incluyen paginación, búsquedas fuera de la primera página, respuestas fuera de orden, cambio de empresa, reintentos y recuperación de operaciones POS.
- Menú: 17 comprobaciones de roles, navegación, teclado y móvil aprobadas.
- Interfaz con API real de ensayo: 17 comprobaciones aprobadas, con alta, edición y eliminación de un producto; selectores completos; restricciones por rol; apertura de caja y una venta local de USD 11,50 con USD 1,50 de IVA. El stock del producto vendido pasó de 100 a 99 y no hubo desbalances. Se recuperó después esa operación desde el POS sin repetir la venta ni volver a descontar stock.
- Servidor y recuperación: 8 comprobaciones aprobadas de compresión, caché, bloqueo de archivos privados en la API de ensayo, fallo de descarga de módulos y reintento de catálogo.
- TypeScript, sintaxis PHP y compilación de producción aprobados. Se conservan advertencias de lint anteriores; no se introdujeron errores de lint.

## Resultados de carga local

Equipo i5-12450H, 12 procesadores lógicos y unos 32 GB de RAM; Apache con 150 hilos, PHP 8.3.36 con OPcache y PostgreSQL 17.4 local. Aplicación en producción, configuración y rutas cacheadas y frontend compilado. Se mantuvieron 100 usuarios virtuales autenticados con credenciales diferentes y pausas de 2 a 2,4 segundos entre solicitudes. Se alternaron Productos, Clientes, Existencias, Facturas y Kardex.

| Escenario | Solicitudes | Errores | Media | p95 |
| --- | ---: | ---: | ---: | ---: |
| Antes, catálogo completo y gzip, 60 s | 1.151 | 0 | 3.199 ms | 8.778 ms |
| Después, mismo catálogo completo, 60 s | 2.421 | 0 | 306 ms | 1.312 ms |
| Después, gestión paginada, 180 s | 8.119 | 0 | 35 ms | 85 ms |

El tiempo de los últimos pedidos y sus pausas se dejó drenar al finalizar cada etapa. En el escenario paginado hubo como máximo ocho solicitudes HTTP simultáneamente en vuelo: los cien usuarios estuvieron activos, pero no enviaron solicitudes continuamente sin pausas. Las ráfagas separadas sí lanzaron cien solicitudes en paralelo.

La ráfaga inicial de cien consultas de clientes tuvo p95 de 2.705 ms y cero errores. Tres repeticiones posteriores tuvieron p95 de 835, 261 y 500 ms, también con cero errores. No debe prometerse un tiempo idéntico para un arranque o una ráfaga de usuarios. Las mediciones no son una certificación de capacidad del hosting definitivo.

Una página de diez productos transfirió unos 12 KB sin compresión, frente a los 2,2 MB del catálogo completo de 1.000 productos. El paquete principal del frontend bajó aproximadamente de 1,22 MB a 0,33 MB sin comprimir; las pantallas diferidas se descargan al abrirse. La reducción del paquete principal no equivale a eliminar esas pantallas.

El informe Word y los JSON/CSV completos se encuentran en la carpeta de documentación de rendimiento del workspace, fuera de estos repositorios. Las pruebas usaron sólo datos sintéticos y no modificaron la base habitual.

## Aplicación al despliegue

1. Compilar el frontend con la URL real de la API y `GENERATE_SOURCEMAP=false`. Publicar todos los recursos generados, incluidos los módulos y CSS separados. No copiar únicamente `main.js`.
2. Desplegar frontend y backend de forma coordinada. En Laravel, usar configuración/rutas cacheadas y OPcache según el servidor. No se requiere ejecutar migraciones para estos cambios.
3. En Apache, permitir las directivas necesarias de los `.htaccess` de los directorios publicados y habilitar los módulos correspondientes. Verificar `Content-Encoding: gzip`, los tipos MIME y `Cache-Control` mediante respuestas HTTP. En otro servidor hay que aplicar reglas equivalentes en su configuración; `.htaccess` no se aplica automáticamente.
4. Publicar únicamente `public` de Laravel y el `build` del frontend. No exponer `.env`, certificados, respaldos ni la raíz del backend.
5. Mantener recursos versionados del despliegue anterior mientras existan sesiones que puedan solicitarlos. La publicación debe evitar mezclar el HTML nuevo con módulos que aún no se han subido. El botón de recuperación no sustituye un despliegue coordinado.
6. Repetir la carga con el hardware, la red, el volumen de datos y las colas reales: al menos quince minutos con cien usuarios y una prueba prolongada de estabilidad. Revisar también el inicio simultáneo de sesiones desde una misma IP, porque el login conserva sus límites de seguridad.

## Alcance pendiente

No se midieron cien ventas ni cien autorizaciones SRI simultáneas. La prueba de venta confirmó el proceso local y dejó la tarea fiscal en cola, con la red SRI deshabilitada y correo en memoria. Tampoco se certifican la red pública, TLS, una base remota, otros volúmenes de datos ni estabilidad durante horas. La instancia Apache de ensayo mostró un aviso de carga de cURL; las rutas medidas no usan esa extensión y las integraciones externas quedaron fuera de esa instancia.

El refuerzo de seguridad existente y el dato real pendiente del proveedor de software para el SRI siguen sujetos a su documentación propia. Estas optimizaciones no alteran esas configuraciones.

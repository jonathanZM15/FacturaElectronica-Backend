# Correcciones y comprobación local de la primera versión

Se corrigieron los nueve hallazgos de código del informe del 16 de noviembre de 2026 y una inconsistencia adicional del formulario de acceso. La validación se realizó en bases aisladas. No se modificó la base habitual, no se ejecutaron migraciones sobre ella y no se utilizó `migrate:fresh`.

## Cambios comprobados

| Hallazgo | Corrección | Verificación |
| --- | --- | --- |
| H01 Redondeo | Decimal.js en el carrito y Brick Math en el cálculo del servidor, con base por línea y redondeo HalfUp. | Precio 10.075, cantidad 1 e IVA 15 % muestran y confirman 11.59. |
| H02 Identidad del cliente | Búsqueda por tipo y número; consulta ambigua sin tipo rechazada. Cambio de tipo limpia y vuelve a buscar. El servidor valida que el ID seleccionado coincida con la identidad y la empresa. | Pasaporte e identificación exterior con el mismo número conservan sus nombres y contactos. Respuestas tardías no reemplazan al cliente vigente. |
| H03 Precisión | Hasta 6 decimales representables para cantidades y precios; cantidades positivas de al menos 0.000001. No se redondea silenciosamente una cantidad inválida. | El inventario inicial rechaza 0.00000001 sin generar Kardex; 0.000001 se guarda exactamente. |
| H04 Código fiscal | Código principal y auxiliar limitados a 25 caracteres en catálogo y formulario. | La API rechaza 26 y acepta 25. No se truncan códigos históricos automáticamente. |
| H05 Bodegas | Tránsito reservado al sistema; tipos con stock o historial protegidos; permiso de venta consistente con el tipo. Comprobaciones dentro de transacción. | Se rechaza reclasificar una bodega con stock. Una bodega vacía sin historial admite cambios compatibles. |
| H06 Entradas inválidas | Estructuras anidadas comprobadas antes de convertir datos. Límites de capacidad y escala en movimientos, productos, pagos y caja; control del saldo acumulado. | Entradas malformadas, sobreprecisión y desbordes devuelven 422 sin escrituras parciales. |
| H07 Consultas | Validación de identificadores, fechas, estados, orden de fechas y paginación de clientes entre 1 y 100. | Fechas y filtros incorrectos devuelven 422. Se conserva `all=true` por compatibilidad con la pantalla de clientes; esa consulta completa todavía depende del volumen de datos. |
| H08 Dependencias | React Router 7.18.5; sustitución de Create React App por Vite; herramientas de desarrollo separadas de las dependencias de ejecución. | `npm audit` y `composer audit --locked` sin avisos conocidos en esta revisión. No significa ausencia absoluta de vulnerabilidades. |
| H09 Compilación | Dependencias de hooks corregidas, eliminación de declaraciones sin uso y botones accesibles para abrir diálogos. Build exige TypeScript y lint sin advertencias. | Compilación con `CI=true` correcta. |
| H10 Acceso | El formulario admite los límites de entrada de la API de login; conserva la política separada para establecer contraseñas. | Acceso real por navegador con correo de 58 caracteres y contraseña de 72; rechazo de clave incorrecta y cierre de sesión. |

Los importes de pago admiten centavos. Los campos de catálogo y stock `numeric(14,6)` tienen límite 99999999.999999; el saldo de caja `numeric(10,2)` tiene límite 99999999.99. El total de factura y los pagos se limitan a 999999999999.99 para mantener representaciones de centavos compatibles entre navegador y API. Estos límites deben tratarse como reglas del producto; no se reducen valores existentes para hacerlos encajar.

## Evidencia

- 200 pruebas de backend, 1140 aserciones; incluyen 188 regresiones y 12 pruebas nuevas de correcciones.
- 48 pruebas del frontend; incluyen las 40 regresiones anteriores adaptadas a Vitest y 8 nuevas.
- Tres escenarios con procesos concurrentes: mismo UUID sin duplicar venta, competencia por stock y numeración única de ajustes.
- Respaldo y restauración de la base sintética: coincidieron 127 facturas, 127 detalles, 127 movimientos y los saldos de sus cuatro productos. No sustituye el ensayo con datos reales.
- Navegador real: venta con redondeo, identidad de clientes, límites de catálogo, historial, acceso, cierre de sesión y menús de cinco roles en móvil. No se alteró la matriz de roles.
- Carga local con 100 cuentas durante aproximadamente 62 segundos: 2881 solicitudes, 2757 lecturas y 124 ventas aceptadas, sin errores. Pausas de 1.8 a 2.2 segundos; pico de 58 solicitudes en vuelo. No equivale a 100 ventas simultáneas sostenidas ni a un ensayo del hosting definitivo.
- Lecturas: media 84 ms y p95 187 ms. Ventas locales: media 411 ms y p95 1844 ms. Las 124 ventas produjeron 124 facturas, UUID únicos, 124 salidas y stock final 9876 desde 10000.

La carga utilizó datos pequeños de ensayo, una empresa y PostgreSQL local. Las ventas quedaron BORRADOR con trabajo en cola; no se contactó SRI ni SMTP. Las cifras no incluyen autorización fiscal ni entrega de correo. Los resultados y capturas se conservan fuera de ambos repositorios, junto al informe de cierre en `docs/correcciones-v1-2026-11-16` del espacio de trabajo. Los scripts de ensayo permanecen en el entorno temporal aislado.

La revisión visual final también comprobó el precio unitario 10.075, la base de línea 10.08 y el total 11.59, después de unificar su presentación decimal. El arranque de Vite mostró el formulario de login sin errores de JavaScript. El dashboard administrativo sigue usando la pantalla vacía preexistente `Blank`; verificar el menú no demuestra que existan indicadores. Ese panel no forma parte de los tres módulos iniciales y no se añadió en estas correcciones.

Los archivos, configuraciones y dependencias exclusivos de las pruebas no se incluyen en los repositorios de esta entrega. Los resultados anteriores documentan la validación realizada antes de esa limpieza. Se conservan las herramientas de compilación y análisis de código necesarias para mantener el proyecto.

## Revisión de datos anteriores

Ejecutar primero sobre una copia respaldada, con la configuración de esa copia:

```console
php artisan v1:revisar-datos --emisor=ID --json
php artisan v1:verificar --emisor=ID --json
```

Ambos comandos son de lectura. El primero informa códigos largos, precios negativos, productos vendibles con impuestos fuera de V1 y bodegas con permiso de venta incompatible. No puede reconstruir el nombre verdadero de un cliente que hubiera sido sobrescrito antes de H02: ese caso exige contrastar documentos, respaldos y datos del responsable. No borrar ni fusionar clientes automáticamente.

`v1:verificar` también comprueba extensiones PHP y avisa cuando la revisión tributaria vence en 30 días o ya venció. El límite `IVA_REVIEWED_UNTIL` es una revisión operativa, no el vencimiento legal de la tarifa. No extenderlo sin revisar la norma.

## Ejecución y puesta en marcha

1. Revisar los cambios de los dos repositorios y respaldar base y archivos privados antes del despliegue. Esta corrección no introduce nuevas migraciones. Si el destino está atrasado, ensayar las migraciones existentes en una copia de su base y después usar `migrate`; nunca `migrate:fresh` sobre datos reales.
2. Frontend: Node 22.12 o superior, `npm ci`, configurar `REACT_APP_API_URL`, `npm run build`. La salida sigue siendo `build/`, sin mapas de código fuente. Configurar fallback a `index.html`. `npm start` conserva `http://localhost:3000` para no romper el origen CORS local existente. Reiniciar cualquier proceso anterior de Create React App.
3. Backend: `composer install` según el lock, configuración real privada, `php artisan config:cache`, reinicio de workers y cachés según el procedimiento del destino. API y workers deben compartir APP_KEY, DB, almacenamiento privado y bloqueos.
4. Worker supervisado: `php artisan queue:work database --queue=default --timeout=60 --sleep=3`. `DB_QUEUE_RETRY_AFTER` al menos 180. Ejecutar `schedule:run` cada minuto; la reconciliación corre cada quince minutos. No ejecutar trabajos de ensayo contra una configuración real.
5. Publicar únicamente `public` de Laravel y `build` de la web, con HTTPS y protecciones del servidor. Verificar de nuevo `.env`, `.git`, logs, certificados, CORS, límites, respaldos y recuperación en el hosting.

El enlace local `Factura-Backend/public/storage` continúa apuntando a una carpeta antigua inexistente. Se comprobó el destino previsto y el contenido público, pero la revisión automática bloqueó la sustitución del enlace y solo devolvió `blocked by policy`. No se ejecutó su borrado. Debe revisarse y recrearse únicamente el enlace, conservando `storage/app/public` y su contenido. Este enlace no se versiona en Git. No se afirma que todos los logos fallen: existen rutas de lectura por controlador.

## Pendientes para aceptar el sistema completo

- Certificado real y empresa de pruebas configurada; RUC real del proveedor de software y su comprobación registral.
- Recepción y autorización reales en el ambiente de pruebas del SRI, conservación de respuestas, XML autorizado y RIDE; prueba de recuperación ante interrupciones sin duplicar MOV03.
- Revisar con SRI el caso de IVA por bases agrupadas frente a suma de IVA redondeado por detalle: dos bases de 0.03 con IVA 15 % dan IVA agrupado 0.01 y detalles 0.00. La política existente se conserva y ahora usa cálculo decimal; pasar XSD no prueba aceptación aritmética externa.
- SMTP real y recepción de los adjuntos en un buzón controlado.
- Perfil tributario real, requisitos de identificación del proveedor y plazos vigentes; mantener deshabilitado RIMPE Popular hasta completar su validación externa y no prometer ICE o IRBPNR en V1.
- Comprobar extensiones y conectividad en el PHP que atiende HTTP y en el worker, no solo en CLI. El Apache aislado mostró un aviso local de carga de cURL; debe resolverse en el runtime definitivo antes de conectar servicios externos.
- Ensayar restauración y migraciones sobre una copia de los datos reales, carga con volumen representativo en el hosting y aceptación de usuarios con los tres módulos.

El portal oficial del SRI se volvió a consultar y presenta la ficha 2.34. No se inventaron certificados, RUC del proveedor ni autorizaciones. Estos pendientes impiden declarar lista la operación completa en producción, aunque las correcciones locales pasen sus pruebas.

Fuentes: [SRI](https://www.sri.gob.ec/facturacion-electronica), [migración oficial de React Router](https://reactrouter.com/7.18.5/upgrading/v6), [retirada de Create React App](https://react.dev/blog/2025/02/14/sunsetting-create-react-app), [Vite](https://vite.dev/guide/).

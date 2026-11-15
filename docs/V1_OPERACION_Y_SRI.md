# Primera versión operación y validación

Esta guía corresponde a las correcciones locales del 15 de noviembre de 2026.
No acredita autorización del SRI, entrega SMTP ni aceptación de usuarios. La
primera versión comprende inventario, clientes y factura con venta inmediata.
Compras, otros comprobantes y movimientos ampliados permanecen fuera del alcance
habilitado. No incluye un libro mayor ni una contabilidad financiera completa.

## Roles operativos

La fuente de permisos es `PermissionService`; `BusinessAccess` limita los datos.
El frontend utiliza los permisos devueltos por el servidor y no concede permisos
cuando falta una entrada. Los botones no sustituyen la autorización del API.

| Acción | Administrador | Distribuidor | Emisor | Gerente | Cajero |
| --- | --- | --- | --- | --- | --- |
| Operar POS y abrir/cerrar caja propia | No | No | No | No | Sí |
| Catálogo de productos y bodegas | Lectura global | No operativo | Gestionar empresa | Lectura asignada | Lectura asignada |
| Ajustes de inventario | Lectura | No | Empresa | Sucursales asignadas | No |
| Kardex | Lectura global | No | Empresa | Sucursales asignadas | No |
| Clientes | Lectura global | No | Crear editar eliminar | Leer | Crear y editar sin eliminar |
| Consultar facturas | Global | No | Empresa | Sucursales asignadas | Puntos asignados |
| Reintentar procesamiento y correo | No | No | Sí | No | Sí dentro de su ámbito |
| Corrección fiscal de rechazo confirmado | No | No | Sí | No | No |
| Cargar certificado | No | No | Sí | No | No |

El distribuidor administra empresas de su red y los usuarios autorizados de esas
empresas. El Emisor crea Gerentes y Cajeros de su empresa. El Gerente crea y asigna
Cajeros dentro de sus sucursales. La jerarquía no habilita automáticamente el POS.
El Cajero puede consultar facturas de sus puntos asignados; recuperar y confirmar
una operación POS existente exige además ser el Cajero que la creó.

## Identidad de venta y corrección

Una operación incierta conserva su UUID en el navegador, incluso ante 401, 403 o
422. Antes de cambiarla se consulta si ya existe. Si existe se recupera; si devuelve
404 se permite revisar datos manteniendo el UUID. El servidor rechaza reutilizar
una operación confirmada con otro contenido. La venta existente se busca antes
de imponer las condiciones necesarias para una venta nueva.

La corrección fiscal sólo admite DEVUELTA o NO_AUTORIZADO, bajo bloqueo y
transacción. Preserva clave y secuencial, audita la versión anterior, actualiza el
snapshot y XML de ese documento y no modifica el maestro del cliente anterior.
No repite pagos ni MOV 03. Sin certificado válido queda BORRADOR recuperable.
No corregir automáticamente documentos con resultado externo incierto.

## Colas y recuperación

V1 requiere una cola `database` en la misma conexión que los comprobantes. La fila
de trabajo y la intención en `sri_tareas` se insertan en una transacción. Cada etapa
se identifica por comprobante, revisión y token. El ID numérico de `jobs` es sólo
una referencia actualizable: Laravel lo cambia al liberar un trabajo aplazado.

API y workers deben compartir APP_KEY, base, cache con bloqueos y disco privado de
certificados. Usar `DB_QUEUE_RETRY_AFTER=180` o mayor, timeout del worker de 60
segundos y bloqueo de 180 segundos. La espera predeterminada entre consultas es
300 segundos, con 300 intentos y plazo de 86400 segundos por ciclo. Estos son
parámetros operativos del sistema y no una garantía de disponibilidad del SRI.

```console
php artisan v1:verificar --emisor=ID
php artisan queue:work database --queue=default --timeout=60 --sleep=3
php artisan schedule:run
```

Mantener el worker supervisado y ejecutar el scheduler cada minuto. La ruta
programada de reconciliación corre cada quince minutos. Tras desplegar cambios,
reiniciar los workers para que no ejecuten código antiguo. `v1:verificar` es de
lectura y no demuestra que el worker esté vivo ni contacta servicios externos.

Los fallos agotados requieren reintento explícito desde Facturas, una vez corregida
la causa. No borrar `jobs`, `failed_jobs`, `sri_tareas` o certificados para limpiar
la pantalla. Conservar los temporales mientras una tarea o payload pendiente o
fallido los referencie. El reconciliador elimina únicamente huérfanos comprobados
con antigüedad suficiente. Resolver y respaldar la evidencia fallida antes de una
depuración administrativa; esta versión no incorpora un panel para esa depuración.

## Fuentes fiscales y límites

Fuentes consultadas el 15 de noviembre de 2026:

- [Portal oficial](https://www.sri.gob.ec/facturacion-electronica): ficha 2.34,
  actualizada a julio de 2026, y paquete XML/XSD de factura.
- [Ficha 2.34](https://www.sri.gob.ec/o/sri-portlet-biblioteca-alfresco-internet/descargar/f8d9bb36-5632-4f96-b463-b9265b55338c/FICHA%20TE%CC%81CNICA%20COMPROBANTES%20ELECTRO%CC%81NICOS%20ESQUEMA%20OFFLINE%20Versio%CC%81n%202.34.pdf).
- [Paquete oficial XML y XSD](https://www.sri.gob.ec/o/sri-portlet-biblioteca-alfresco-internet/descargar/05546998-6f29-4870-be3b-62650f312a6c/XML%20y%20XSD%20Factura.zip).
- [Circular IVA](https://www.sri.gob.ec/o/sri-portlet-biblioteca-alfresco-internet/descargar?id=236482f4-6125-42fd-b073-62c99d08233d&nombre=NAC-DGECCGC25-00000006.pdf).
- [Información oficial del proveedor](https://www.sri.gob.ec/detalle-noticias?idnoticia=1318&marquesina=1).

SHA256 del PDF: `7333aebfbdf2cb3ba83f9fc67a7a7f0346ca59506480a260cc42f96dbdfc13c9`.
SHA256 del ZIP: `ba1ff0c4e329fe759c3f88dc75f2975780b315b6eb3d0069071b77c1f26fec03`.
Los XSD locales 1.1.0 y 2.1.0 coinciden byte por byte con los descargados.
El generador de V1 utiliza factura 2.1.0. El verificador usa RSA-SHA1 conforme al
perfil contrastado; esto no sustituye la confianza del certificado ni su validación
con el SRI. La integridad de firma se contrastó también con lxml y cryptography.

Existe una discrepancia entre el patrón RIMPE del XSD publicado y la leyenda
Popular del Anexo 22. Los archivos oficiales se conservan intactos. Para el ensayo
local se deriva una restricción de dos valores exactos, sin retirar las demás
validaciones. Ese resultado no equivale a pasar el XSD oficial sin adaptación.
`SRI_RIMPE_POPULAR_HABILITADO=false` bloquea su emisión conectada por defecto.
Resolver con fuente oficial compatible y validación externa antes de habilitarlo.

`config/iva.php` separa código, tarifa, fecha y fuente. La fecha de revisión
operativa no es un vencimiento legal. Revisar la normativa antes de prolongarla.
Las tarifas especiales no están habilitadas por defecto. Para habilitarlas se
requieren periodos documentados y productos elegibles; turismo necesita fin de
ventana y construcción un código auxiliar admitido. La clasificación tributaria
real de cada producto y de la empresa requiere validación del responsable fiscal.
RUC de cliente: formato de 13 dígitos; esto no demuestra existencia registral.

## RUC del proveedor pendiente

El usuario dejó pendiente proporcionar el dato real. No se debe completar con un
RUC inventado, el del comprador o el del emisor por suposición. Identificar quién
presta el software; comprobar su RUC y la situación de registro exigible. Confirmar
el cómputo de plazos en la resolución publicada, ya que la noticia y la ficha no
emplean el mismo cómputo. No calcular una fecha límite con esa discrepancia abierta.

Configurar `SRI_PROVEEDOR_RUC` y, si se desea, `SRI_PROVEEDOR_NOMBRE` mediante el
mecanismo seguro del entorno. Mantener `SRI_PROVEEDOR_REQUERIDO=true` para software
de terceros. Desactivarlo exige sustentar que no aplica, no es una solución a la
ausencia del dato. Limpiar/reconstruir la configuración y reiniciar workers. Crear
una factura nueva de ensayo: debe haber un solo campo `RUC Proveedor`, sin que el
Cajero pueda sustituirlo. Comparar XML, descarga, correo y RIDE. No reescribir XML
autorizados históricos al cambiar la configuración. Probar el flujo real con SRI
y buzón controlado después de disponer de empresa y certificado utilizables.

## Actualización y salida

Antes de actualizar la base habitual, respaldar y demostrar restauración en una
copia de esa misma base. El ensayo realizado utilizó datos sintéticos históricos,
no la base habitual. Aplicar las dos migraciones de 2026_11_15 en la copia y
comparar totales, snapshots, XML, stock, secuenciales, usuarios, tareas y estados.
No reenviar masivamente documentos anteriores. Las migraciones no cambian estados
fiscales ni generan ventas. Sus reversiones tienen guardas para evitar truncar
identificaciones o perder el registro de tareas; restaurar un respaldo coherente
con el código es el procedimiento de recuperación.

Tras revisar el diff, instalar dependencias bloqueadas, compilar el frontend,
configurar la raíz del API sin duplicar `/api`, revisar los feature flags y los
procesos de cola/scheduler. El ejemplo de frontend usa `http://localhost:8000`.
No publicar ni afirmar funcionamiento integral hasta completar autorización SRI,
entrega real, ensayo de la base habitual y aceptación de usuarios responsables.

El expediente Word de ejecución y sus evidencias se entregan fuera del repositorio.
No se incorporaron tests ni certificados de ensayo a estas fuentes.

<?php

return [
    'environment' => env('SRI_ENVIRONMENT', 'PRUEBAS'),
    'certificate_path' => env('SRI_CERTIFICATE_PATH'),
    'certificate_password' => env('SRI_CERTIFICATE_PASSWORD'),
    'certificate_disk' => env('SRI_CERTIFICATE_DISK', 'local'),
    'recepcion_wsdl_pruebas' => env('SRI_RECEPCION_WSDL_PRUEBAS', 'https://celcer.sri.gob.ec/comprobantes-electronicos-ws/RecepcionComprobantesOffline?wsdl'),
    'autorizacion_wsdl_pruebas' => env('SRI_AUTORIZACION_WSDL_PRUEBAS', 'https://celcer.sri.gob.ec/comprobantes-electronicos-ws/AutorizacionComprobantesOffline?wsdl'),
    'recepcion_wsdl_produccion' => env('SRI_RECEPCION_WSDL_PRODUCCION', 'https://cel.sri.gob.ec/comprobantes-electronicos-ws/RecepcionComprobantesOffline?wsdl'),
    'autorizacion_wsdl_produccion' => env('SRI_AUTORIZACION_WSDL_PRODUCCION', 'https://cel.sri.gob.ec/comprobantes-electronicos-ws/AutorizacionComprobantesOffline?wsdl'),
    'retry_after_seconds' => (int) env('SRI_RETRY_AFTER_SECONDS', 300),
    'max_authorization_attempts' => (int) env('SRI_MAX_AUTHORIZATION_ATTEMPTS', 10),
];
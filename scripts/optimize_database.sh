#!/bin/bash
# ================================================================
# SCRIPT DE EJECUCIÓN - APLICAR OPTIMIZACIÓN DE BASE DE DATOS
# ================================================================
# Ejecuta este script para aplicar todas las optimizaciones

echo "🚀 INICIANDO OPTIMIZACIÓN DE BASE DE DATOS"
echo "==========================================="
echo ""

# PASO 1: Backup
echo "📦 PASO 1: Crear backup de base de datos..."
echo "⏳ Ejecuta manualmente si es necesario:"
echo "   mysqldump -u usuario -p basedatos > backup_$(date +%Y%m%d_%H%M%S).sql"
echo ""

# PASO 2: Aplicar migración
echo "📝 PASO 2: Aplicar migración con índices..."
php artisan migrate --path="database/migrations/2026_03_16_000001_optimize_database_performance.php"

if [ $? -eq 0 ]; then
    echo "✅ Migración aplicada exitosamente"
else
    echo "❌ Error en migración. Revisa logs."
    exit 1
fi
echo ""

# PASO 3: Verificar índices
echo "🔍 PASO 3: Verificar índices creados..."
echo "⏳ Ejecuta en cliente MySQL/PostgreSQL:"
echo ""
echo "   DIA: SELECT INDEX_NAME, COLUMN_NAME, TABLE_NAME"
echo "        FROM INFORMATION_SCHEMA.STATISTICS"
echo "        WHERE TABLE_SCHEMA = DATABASE()"
echo "        ORDER BY TABLE_NAME, INDEX_NAME;"
echo ""

# PASO 4: Limpiar cache
echo "🧹 PASO 4: Limpiar cache application..."
php artisan cache:clear
php artisan config:clear
php artisan view:clear

# PASO 5: Ejecutar tests
echo ""
echo "🧪 PASO 5: Ejecutar tests de validación..."
echo "⏳ Ejecuta:"
echo "   php artisan test --filter=\"User|Emisor|Suscripcion\""
echo ""

echo "✅ OPTIMIZACIÓN COMPLETADA"
echo ""
echo "📊 PRÓXIMOS PASOS:"
echo "  1. Ejecutar benchmark_queries.sql ANTES de migración"
echo "  2. Aplicar esta migración"
echo "  3. Ejecutar benchmark_queries.sql DESPUÉS"
echo "  4. Comparar tiempos (espera ~70-85% mejora)"
echo ""
echo "📖 Ver documentación completa en: OPTIMIZATION_GUIDE.md"

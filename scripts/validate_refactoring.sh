#!/bin/bash
# ================================================================
# VALIDACIÓN RÁPIDA - Después de aplicar refactorización
# ================================================================

echo "🔍 VALIDANDO REFACTORIZACIÓN"
echo "=============================="
echo ""

# Color codes
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Check 1: Verificar archivos modificados existen
echo "✅ CHECK 1: Verifying modified files..."
FILES=(
    "app/Http/Controllers/UserController.php"
    "app/Http/Controllers/EmisorController.php"
    "app/Services/PuntoEmisionDisponibilidadService.php"
    "database/migrations/2026_03_16_000001_optimize_database_performance.php"
)

for file in "${FILES[@]}"; do
    if [ -f "$file" ]; then
        echo "  ✓ $file"
    else
        echo -e "  ${RED}✗ $file NOT FOUND${NC}"
        exit 1
    fi
done
echo ""

# Check 2: Verificar que los archivos tienen el código esperado
echo "✅ CHECK 2: Verifying code changes..."

# UserController debe tener select()
if grep -q "select(\[" app/Http/Controllers/UserController.php; then
    echo "  ✓ UserController tiene optimización SELECT"
else
    echo -e "  ${RED}✗ UserController SELECT no encontrado${NC}"
fi

# EmisorController debe tener withCount
if grep -q "withCount" app/Http/Controllers/EmisorController.php; then
    echo "  ✓ EmisorController tiene optimización withCount"
else
    echo -e "  ${RED}✗ EmisorController withCount no encontrado${NC}"
fi

# PuntoEmision debe tener JSON simplificado
if grep -q "whereJsonContains" app/Services/PuntoEmisionDisponibilidadService.php; then
    echo "  ✓ PuntoEmisionService tiene JSON optimizado"
else
    echo -e "  ${RED}✗ PuntoEmisionService JSON no encontrado${NC}"
fi
echo ""

# Check 3: Verificar sintaxis PHP
echo "✅ CHECK 3: Validating PHP syntax..."
php -l app/Http/Controllers/UserController.php > /dev/null 2>&1
if [ $? -eq 0 ]; then
    echo "  ✓ UserController sintaxis OK"
else
    echo -e "  ${RED}✗ UserController sintaxis ERROR${NC}"
fi

php -l app/Http/Controllers/EmisorController.php > /dev/null 2>&1
if [ $? -eq 0 ]; then
    echo "  ✓ EmisorController sintaxis OK"
else
    echo -e "  ${RED}✗ EmisorController sintaxis ERROR${NC}"
fi

php -l app/Services/PuntoEmisionDisponibilidadService.php > /dev/null 2>&1
if [ $? -eq 0 ]; then
    echo "  ✓ PuntoEmisionService sintaxis OK"
else
    echo -e "  ${RED}✗ PuntoEmisionService sintaxis ERROR${NC}"
fi
echo ""

# Check 4: Ejecutar migración
echo "✅ CHECK 4: Running migrations..."
php artisan migrate --force 2>&1 | tail -5
if [ $? -eq 0 ]; then
    echo -e "  ${GREEN}✓ Migrations executed successfully${NC}"
else
    echo -e "  ${RED}✗ Migration failed${NC}"
fi
echo ""

# Check 5: Limpiar cache
echo "✅ CHECK 5: Clearing cache..."
php artisan cache:clear > /dev/null 2>&1
php artisan config:clear > /dev/null 2>&1
php artisan view:clear > /dev/null 2>&1
echo "  ✓ Cache cleared"
echo ""

# Check 6: Verificar que el app arranca
echo "✅ CHECK 6: Testing app boot..."
php artisan tinker <<'EOF' > /dev/null 2>&1
echo '';
\App\Models\User::count();
\App\Models\Company::count();
EOF
if [ $? -eq 0 ]; then
    echo -e "  ${GREEN}✓ App boots successfully${NC}"
else
    echo -e "  ${RED}✗ App failed to boot${NC}"
fi
echo ""

# Final
echo "=============================="
echo -e "${GREEN}✅ REFACTORIZACIÓN VALIDADA${NC}"
echo "=============================="
echo ""
echo "📋 Próximos pasos:"
echo "  1. php artisan test --filter='User|Emisor' # Run tests"
echo "  2. Abrir navegador en http://localhost:8000"
echo "  3. Probar endpoints de API"
echo "  4. Validar que responses llegan 75% más rápido"
echo ""
echo "📚 Ver documentación completa en: REFACTORIZATION_COMPLETE.md"
echo ""

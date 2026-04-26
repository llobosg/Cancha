#!/bin/bash
# remove_session_start.sh
# Elimina session_start() de todos los archivos en pages/ (macOS compatible)

set -e  # Detener si hay error

echo "🔍 Buscando archivos con session_start() en pages/..."
echo ""

# Contadores
MODIFIED=0
SKIPPED=0
ERRORS=0

# Patrones a eliminar (variantes comunes)
PATTERNS=(
    '^[[:space:]]*session_start\(\)[[:space:]]*;[[:space:]]*$'
    '^[[:space:]]*if[[:space:]]*\([[:space:]]*session_status\(\)[[:space:]]*===[[:space:]]*PHP_SESSION_NONE[[:space:]]*\)[[:space:]]*session_start\(\)[[:space:]]*;[[:space:]]*$'
    '^[[:space:]]*//.*session_start'
)

# Buscar archivos en pages/ que contengan session_start
find pages/ -type f -name "*.php" -exec grep -l "session_start" {} \; | while read -r file; do
    echo "📄 Procesando: $file"
    
    # Crear backup con timestamp
    cp "$file" "${file}.bak.$(date +%Y%m%d_%H%M%S)"
    
    # Eliminar líneas con session_start (variantes)
    # Usamos sed con múltiples expresiones
    sed -i '' \
        -e '/^[[:space:]]*session_start()[[:space:]]*;/d' \
        -e '/^[[:space:]]*if[[:space:]]*(session_status()[[:space:]]*===[[:space:]]*PHP_SESSION_NONE)[[:space:]]*session_start()[[:space:]]*;/d' \
        -e '/^[[:space:]]*\/\/.*session_start/d' \
        "$file"
    
    # Verificar si se eliminó algo
    if grep -q "session_start" "$file"; then
        echo "   ⚠️  Aún quedan session_start en $file (revisar manualmente)"
        ((ERRORS++)) || true
    else
        echo "   ✅ session_start() eliminado"
        ((MODIFIED++)) || true
    fi
    echo ""
done

# Resumen final
echo "================================"
echo "📊 RESUMEN:"
echo "   ✅ Archivos modificados: $MODIFIED"
echo "   ⚠️  Archivos con errores: $ERRORS"
echo ""
echo "📁 Backups creados en: pages/*.bak.*"
echo ""
echo "💡 Para revertir un archivo:"
echo "   cp pages/archivo.php.bak.YYYYMMDD_HHMMSS pages/archivo.php"
echo ""
echo "🚀 ¡Listo! Ahora solo config.php debe manejar la sesión."
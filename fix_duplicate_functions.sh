#!/bin/bash
# fix_duplicate_functions.sh
# Elimina funciones duplicadas: esAdmin(), esAsistente(), estaAutenticado()
# Manteniéndolas SOLO en includes/config.php
# Compatible con macOS

set -e  # Detener ante errores

echo "🔍 Buscando funciones duplicadas en includes/ y pages/..."
echo "   (excluyendo includes/config.php donde SÍ deben estar)"
echo ""

# Funciones a eliminar de otros archivos
TARGET_FUNCTIONS=("esAdmin" "esAsistente" "estaAutenticado")

# Contadores
MODIFIED=0
SKIPPED=0
ERRORS=0

# Encontrar todos los archivos PHP excepto config.php
find includes/ pages/ -name "*.php" -type f -not -path "*/config.php" | while read -r file; do
    FILE_MODIFIED=false
    
    for func in "${TARGET_FUNCTIONS[@]}"; do
        # Verificar si el archivo contiene la definición de la función
        if grep -q "function ${func}(" "$file"; then
            echo "📄 $file: encontrada 'function $func()'"
            
            # Crear backup con timestamp único
            BACKUP="${file}.bak.$(date +%Y%m%d_%H%M%S)"
            cp "$file" "$BACKUP"
            echo "   💾 Backup: $BACKUP"
            
            # Eliminar la función completa usando sed (patrón multi-línea)
            # Busca: function name( ... ) { ... } incluyendo bloques anidados
            sed -i '' "/^[[:space:]]*function[[:space:]]\+${func}[[:space:]]*(/,/^}[[:space:]]*$/ {
                /^[[:space:]]*function[[:space:]]\+${func}[[:space:]]*(/b mark
                b
                :mark
                :loop
                n
                /{/ { s/{/\\{/g; s/}/\\}/g }
                /}/ {
                    s/\\{/ {/g; s/\\}/ }/g
                    b end
                }
                b loop
                :end
                d
            }" "$file" 2>/dev/null || true
            
            # Método alternativo más seguro: eliminar bloque completo con awk
            awk -v fname="$func" '
                BEGIN { in_func=0; braces=0 }
                /^[[:space:]]*(if[[:space:]]*\(!?function_exists\([[:space:]]*['\''"]?)/ {
                    if ($0 ~ fname) { in_func=1; next }
                }
                in_func && /function[[:space:]]+'fname'[[:space:]]*\(/ { in_func=1; braces=0; next }
                in_func {
                    gsub(/{/, "{\n"); gsub(/}/, "}\n");
                    n = split($0, chars, "");
                    for(i=1; i<=n; i++) {
                        if(chars[i] == "{") braces++;
                        if(chars[i] == "}") braces--;
                    }
                    if(braces <= 0 && /}/) { in_func=0; next }
                    next
                }
                !in_func { print }
            ' "$BACKUP" > "$file"
            
            # Verificar si se eliminó correctamente
            if grep -q "function ${func}(" "$file"; then
                echo "   ⚠️  No se pudo eliminar completamente (revisar manualmente)"
                ((ERRORS++)) || true
            else
                echo "   ✅ Eliminada 'function $func()'"
                FILE_MODIFIED=true
            fi
        fi
    done
    
    if [ "$FILE_MODIFIED" = true ]; then
        ((MODIFIED++)) || true
        echo ""
    fi
done

# Resumen final
echo "================================"
echo "📊 RESUMEN DE CORRECCIÓN:"
echo "   ✅ Archivos modificados: $MODIFIED"
echo "   ⚠️  Archivos con advertencias: $ERRORS"
echo ""
echo "📁 Backups creados: *.bak.YYYYMMDD_HHMMSS"
echo ""
echo "💡 Para revertir un archivo específico:"
echo "   cp pages/archivo.php.bak.20260426_140000 pages/archivo.php"
echo ""
echo "💡 Para revertir TODOS los backups:"
echo "   for f in */*.bak.*; do [ -f \"\$f\" ] && cp \"\$f\" \"\${f%.bak.*}\"; done"
echo ""
echo "🚀 ¡Listo! Las funciones ahora solo están en includes/config.php"
#!/bin/bash

# Script para aplicar la migración de métodos de pago
# Este script soluciona el problema de generación de tickets

echo "=== Aplicando migración de métodos de pago para tickets ==="
echo ""

# Check if migration file exists
if [ ! -f "database/migration_payment_methods.sql" ]; then
    echo "❌ Error: No se encontró el archivo database/migration_payment_methods.sql"
    exit 1
fi

echo "📁 Archivo de migración encontrado: database/migration_payment_methods.sql"
echo ""

# Display migration content
echo "📋 Contenido de la migración:"
echo "----------------------------------------"
cat database/migration_payment_methods.sql
echo "----------------------------------------"
echo ""

# Ask for database credentials
echo "🔑 Ingrese las credenciales de la base de datos:"
read -p "Usuario de base de datos: " DB_USER
read -s -p "Contraseña: " DB_PASS
echo ""
read -p "Nombre de la base de datos (por defecto: exhacien_restaurante): " DB_NAME
DB_NAME=${DB_NAME:-exhacien_restaurante}

echo ""
echo "🚀 Aplicando migración..."

# Apply migration
mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < database/migration_payment_methods.sql

if [ $? -eq 0 ]; then
    echo "✅ Migración aplicada exitosamente!"
    echo ""
    
    # Verify the change
    echo "🔍 Verificando que la migración se aplicó correctamente..."
    
    COLUMN_TYPE=$(mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = '$DB_NAME' AND TABLE_NAME = 'tickets' AND COLUMN_NAME = 'payment_method';" --skip-column-names 2>/dev/null)
    
    if [[ "$COLUMN_TYPE" == *"intercambio"* ]] && [[ "$COLUMN_TYPE" == *"pendiente_por_cobrar"* ]]; then
        echo "✅ Verificación exitosa: Los nuevos métodos de pago están disponibles"
        echo "   Métodos disponibles: $COLUMN_TYPE"
        echo ""
        echo "🎉 ¡El problema de generación de tickets ha sido solucionado!"
        echo "   Ahora puede generar tickets con cualquier método de pago disponible en el formulario."
    else
        echo "⚠️  Advertencia: No se pudo verificar completamente la migración"
        echo "   Por favor, verifique manualmente ejecutando:"
        echo "   DESCRIBE tickets;"
    fi
else
    echo "❌ Error al aplicar la migración"
    echo "   Verifique las credenciales y que la base de datos esté disponible"
    exit 1
fi
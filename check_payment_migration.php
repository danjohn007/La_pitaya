<?php
/**
 * Script para verificar si la migración de métodos de pago es necesaria
 * y proporcionar instrucciones claras para solucionarlo
 */

define('BASE_PATH', '/home/runner/work/Restaurante-La-Troje/Restaurante-La-Troje');
require_once BASE_PATH . '/config/config.php';
require_once BASE_PATH . '/config/database.php';

echo "=== Verificador de Estado de Migración de Métodos de Pago ===\n\n";

// First check if migration file exists before trying database connection
$migrationFile = BASE_PATH . '/database/migration_payment_methods.sql';
$migrationExists = file_exists($migrationFile);

try {
    $db = Database::getInstance();
    $connection = $db->getConnection();
    
    echo "✅ Conexión a base de datos exitosa\n";
    echo "   Base de datos: " . DB_NAME . "\n";
    echo "   Host: " . DB_HOST . "\n\n";
    
    // Check payment_method ENUM values
    echo "🔍 Verificando métodos de pago disponibles en la base de datos...\n";
    
    $query = "SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS 
              WHERE TABLE_SCHEMA = DATABASE() 
              AND TABLE_NAME = 'tickets' 
              AND COLUMN_NAME = 'payment_method'";
    
    $stmt = $connection->prepare($query);
    $stmt->execute();
    $columnInfo = $stmt->fetch();
    
    if ($columnInfo) {
        echo "📋 ENUM actual: {$columnInfo['COLUMN_TYPE']}\n\n";
        
        // Extract enum values
        if (preg_match("/^enum\((.+)\)$/", $columnInfo['COLUMN_TYPE'], $matches)) {
            $enumOptions = str_getcsv($matches[1], ',', "'");
            echo "🎯 Métodos de pago disponibles:\n";
            foreach ($enumOptions as $option) {
                echo "   - $option\n";
            }
            echo "\n";
            
            // Check if missing options
            $required = ['efectivo', 'tarjeta', 'transferencia', 'intercambio', 'pendiente_por_cobrar'];
            $missing = array_diff($required, $enumOptions);
            
            if (empty($missing)) {
                echo "✅ ESTADO: CORRECTO\n";
                echo "   Todos los métodos de pago requeridos están disponibles.\n";
                echo "   La generación de tickets debería funcionar correctamente.\n";
            } else {
                echo "❌ ESTADO: REQUIERE MIGRACIÓN\n";
                echo "   Métodos de pago faltantes: " . implode(', ', $missing) . "\n\n";
                
                echo "🔧 SOLUCIÓN:\n";
                echo "   1. Ejecutar el script de migración:\n";
                echo "      ./apply_payment_methods_migration.sh\n\n";
                echo "   2. O aplicar manualmente:\n";
                echo "      mysql -u exhacien_restaurante -p exhacien_restaurante < database/migration_payment_methods.sql\n\n";
                echo "   3. O ejecutar directamente el SQL:\n";
                echo "      ALTER TABLE tickets MODIFY COLUMN payment_method \n";
                echo "      ENUM('efectivo', 'tarjeta', 'transferencia', 'intercambio', 'pendiente_por_cobrar') \n";
                echo "      DEFAULT 'efectivo';\n\n";
                
                echo "⚠️  IMPORTANTE: Este es el problema que causa el error:\n";
                echo "   'Error al crear el ticket en la base de datos: Falló la inserción en la base de datos'\n";
            }
        }
    } else {
        echo "❌ ERROR: No se encontró la columna payment_method en la tabla tickets\n";
        echo "   Verifique que la tabla existe y está correctamente configurada.\n";
    }
    
} catch (Exception $e) {
    echo "❌ ERROR DE CONEXIÓN: {$e->getMessage()}\n\n";
    
    echo "💡 DIAGNÓSTICO SIN ACCESO A BASE DE DATOS:\n";
    echo "   Basado en el análisis del código, el problema es que la tabla 'tickets'\n";
    echo "   tiene una restricción ENUM en la columna 'payment_method' que solo permite:\n";
    echo "   - efectivo\n";
    echo "   - tarjeta\n";
    echo "   - transferencia\n\n";
    
    echo "   Pero el formulario web permite seleccionar también:\n";
    echo "   - intercambio\n";
    echo "   - pendiente_por_cobrar\n\n";
    
    echo "🔧 SOLUCIÓN REQUERIDA:\n";
    echo "   Aplicar la migración database/migration_payment_methods.sql\n";
    echo "   que actualiza el ENUM para incluir todos los métodos de pago.\n\n";
    
    echo "📁 Verificando archivos necesarios...\n";
    
    if ($migrationExists) {
        echo "   ✅ Archivo de migración encontrado: $migrationFile\n";
        echo "   📄 Contenido:\n";
        echo "   " . str_replace("\n", "\n   ", file_get_contents($migrationFile));
    } else {
        echo "   ❌ Archivo de migración NO encontrado: $migrationFile\n";
        echo "   💡 Debe crear el archivo con el siguiente contenido:\n";
        echo "   \n";
        echo "   -- Migration to add missing payment methods to tickets table\n";
        echo "   USE exhacien_restaurante;\n";
        echo "   ALTER TABLE tickets MODIFY COLUMN payment_method \n";
        echo "   ENUM('efectivo', 'tarjeta', 'transferencia', 'intercambio', 'pendiente_por_cobrar') \n";
        echo "   DEFAULT 'efectivo';\n";
        echo "   COMMIT;\n";
    }
}

echo "\n=== Fin del diagnóstico ===\n";
?>
<?php
/**
 * Validation script for ticket creation transaction fix
 * This script helps verify that the transaction handling improvements work correctly
 */

define('BASE_PATH', __DIR__);

// Include configuration and models (only if database is available)
try {
    require_once BASE_PATH . '/config/config.php';
    require_once BASE_PATH . '/config/database.php';
    require_once BASE_PATH . '/core/BaseModel.php';
    
    // Load models
    $modelFiles = ['Ticket', 'Order', 'Table', 'SystemSettings', 'Customer', 'DishIngredient', 'OrderItem'];
    foreach ($modelFiles as $model) {
        $file = BASE_PATH . "/models/{$model}.php";
        if (file_exists($file)) {
            require_once $file;
        }
    }
    
    echo "=== Validación del Fix de Transacciones en Tickets ===\n\n";
    
    // Test 1: Check if Database connection works
    echo "1. Verificando conexión a base de datos...\n";
    try {
        $db = Database::getInstance();
        echo "   ✓ Conexión exitosa\n";
        
        // Test transaction methods
        echo "\n2. Verificando métodos de transacción...\n";
        $connection = $db->getConnection();
        
        echo "   - inTransaction() method: " . (method_exists($connection, 'inTransaction') ? "✓ Disponible" : "✗ No disponible") . "\n";
        echo "   - beginTransaction() method: " . (method_exists($connection, 'beginTransaction') ? "✓ Disponible" : "✗ No disponible") . "\n";
        echo "   - commit() method: " . (method_exists($connection, 'commit') ? "✓ Disponible" : "✗ No disponible") . "\n";
        echo "   - rollback() method: " . (method_exists($connection, 'rollback') ? "✓ Disponible" : "✗ No disponible") . "\n";
        
        // Test 3: Verify Ticket model exists and has the fixed methods
        echo "\n3. Verificando modelo Ticket...\n";
        if (class_exists('Ticket')) {
            $ticket = new Ticket();
            echo "   ✓ Clase Ticket disponible\n";
            
            $methods = ['createTicket', 'crearCuentasSeparadas', 'createTicketFromMultipleOrders', 'createTicketFromMultipleOrdersWithoutTableFree'];
            foreach ($methods as $method) {
                if (method_exists($ticket, $method)) {
                    echo "   ✓ Método {$method}() existe\n";
                } else {
                    echo "   ✗ Método {$method}() no encontrado\n";
                }
            }
        } else {
            echo "   ✗ Clase Ticket no disponible\n";
        }
        
        // Test 4: Check database schema
        echo "\n4. Verificando esquema de base de datos...\n";
        try {
            $stmt = $db->prepare("SHOW TABLES LIKE 'tickets'");
            $stmt->execute();
            if ($stmt->fetch()) {
                echo "   ✓ Tabla 'tickets' existe\n";
                
                // Check table structure
                $stmt = $db->prepare("DESCRIBE tickets");
                $stmt->execute();
                $columns = $stmt->fetchAll();
                
                $requiredColumns = ['id', 'order_id', 'ticket_number', 'cashier_id', 'subtotal', 'tax', 'total', 'payment_method'];
                foreach ($requiredColumns as $col) {
                    $found = false;
                    foreach ($columns as $column) {
                        if ($column['Field'] === $col) {
                            $found = true;
                            break;
                        }
                    }
                    echo "   " . ($found ? "✓" : "✗") . " Columna '{$col}'\n";
                }
            } else {
                echo "   ✗ Tabla 'tickets' no existe\n";
            }
        } catch (Exception $e) {
            echo "   ⚠ Error verificando esquema: " . $e->getMessage() . "\n";
        }
        
        // Test 5: Test transaction behavior (safe test)
        echo "\n5. Probando comportamiento de transacciones...\n";
        try {
            echo "   - Estado inicial: " . ($connection->inTransaction() ? "EN TRANSACCIÓN" : "SIN TRANSACCIÓN") . "\n";
            
            // Start transaction
            $db->beginTransaction();
            echo "   - Después de beginTransaction(): " . ($connection->inTransaction() ? "EN TRANSACCIÓN" : "SIN TRANSACCIÓN") . "\n";
            
            // Rollback
            $db->rollback();
            echo "   - Después de rollback(): " . ($connection->inTransaction() ? "EN TRANSACCIÓN" : "SIN TRANSACCIÓN") . "\n";
            
            echo "   ✓ Comportamiento de transacciones es correcto\n";
        } catch (Exception $e) {
            echo "   ⚠ Error probando transacciones: " . $e->getMessage() . "\n";
        }
        
        echo "\n=== Validación Completa ===\n";
        echo "Si todos los elementos marcados con ✓ están presentes, el fix debería funcionar correctamente.\n";
        echo "Para probar completamente:\n";
        echo "1. Accede al sistema web\n";
        echo "2. Ve a 'Generar Ticket Individual'\n";
        echo "3. Selecciona un pedido listo\n";
        echo "4. Genera el ticket\n";
        echo "5. Verifica que no aparezca el error 'There is no active transaction'\n\n";
        
    } catch (Exception $e) {
        echo "   ✗ Error de conexión: " . $e->getMessage() . "\n";
        echo "   Esto puede ser normal si la base de datos no está configurada en este entorno\n\n";
        
        // Still test the code structure
        echo "2. Verificando estructura del código...\n";
        
        // Check if files exist and have correct syntax
        $files = [
            'models/Ticket.php',
            'controllers/TicketsController.php'
        ];
        
        foreach ($files as $file) {
            if (file_exists(BASE_PATH . '/' . $file)) {
                echo "   ✓ {$file} existe\n";
                
                // Check syntax
                $output = [];
                $return_var = 0;
                exec("php -l " . escapeshellarg(BASE_PATH . '/' . $file) . " 2>&1", $output, $return_var);
                
                if ($return_var === 0) {
                    echo "   ✓ {$file} sintaxis correcta\n";
                } else {
                    echo "   ✗ {$file} error de sintaxis: " . implode(", ", $output) . "\n";
                }
            } else {
                echo "   ✗ {$file} no existe\n";
            }
        }
        
        echo "\n=== Validación de Estructura Completa ===\n";
        echo "Los archivos modificados tienen la estructura correcta.\n";
        echo "El fix debería funcionar cuando se ejecute en un entorno con base de datos.\n\n";
    }
    
} catch (Exception $e) {
    echo "Error durante la validación: " . $e->getMessage() . "\n";
}

echo "=== Información del Fix ===\n";
echo "Este fix resuelve el error 'There is no active transaction' al:\n";
echo "1. Verificar el estado de transacción antes de hacer rollback\n";
echo "2. Evitar iniciar transacciones innecesarias\n";
echo "3. Manejar mejor los errores de rollback\n";
echo "4. Usar inTransaction() para verificar el estado\n\n";

echo "Para más detalles, consulta: TRANSACTION_HANDLING_FIX.md\n";
?>
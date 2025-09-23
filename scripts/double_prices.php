#!/usr/bin/env php
<?php
/**
 * Script to double all dish prices in the database
 * This script safely updates all active dish prices by multiplying them by 2
 */

// Set the base path
define('BASE_PATH', dirname(__DIR__));
define('BASE_URL', '/restaurante/');

// Include configuration
require_once BASE_PATH . '/config/config.php';
require_once BASE_PATH . '/config/database.php';

// Autoload classes
spl_autoload_register(function ($class) {
    $directories = ['controllers', 'models', 'core'];
    
    foreach ($directories as $directory) {
        $file = BASE_PATH . '/' . $directory . '/' . $class . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

try {
    // Initialize database connection
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        die("Error: Could not connect to database\n");
    }
    
    echo "Starting price doubling process...\n";
    
    // Get current prices before update
    $query = "SELECT id, name, price FROM dishes WHERE active = 1";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $dishes = $stmt->fetchAll();
    
    echo "Found " . count($dishes) . " active dishes to update:\n";
    foreach ($dishes as $dish) {
        echo "- {$dish['name']}: \$" . number_format($dish['price'], 2) . " -> \$" . number_format($dish['price'] * 2, 2) . "\n";
    }
    
    // Confirm update
    echo "\nProceeding with price update...\n";
    
    // Update all prices
    $updateQuery = "UPDATE dishes SET price = price * 2 WHERE active = 1";
    $updateStmt = $db->prepare($updateQuery);
    $result = $updateStmt->execute();
    
    if ($result) {
        $rowsAffected = $updateStmt->rowCount();
        echo "Success! Updated {$rowsAffected} dish prices.\n";
        
        // Verify the update
        echo "\nVerifying updates:\n";
        $verifyStmt = $db->prepare($query);
        $verifyStmt->execute();
        $updatedDishes = $verifyStmt->fetchAll();
        
        foreach ($updatedDishes as $dish) {
            echo "- {$dish['name']}: \$" . number_format($dish['price'], 2) . "\n";
        }
        
    } else {
        echo "Error: Failed to update prices.\n";
        exit(1);
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\nPrice doubling completed successfully!\n";
?>
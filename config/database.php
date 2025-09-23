<?php
class Database {
    private static $instance = null;
    private $connection;
    
    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_AUTOCOMMIT => true, // Ensure autocommit is enabled
            ];
            
            $this->connection = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            die("Error de conexión: " . $e->getMessage());
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    public function prepare($query) {
        return $this->connection->prepare($query);
    }
    
    public function lastInsertId() {
        return $this->connection->lastInsertId();
    }
    
    public function beginTransaction() {
        return $this->connection->beginTransaction();
    }
    
    public function commit() {
        return $this->connection->commit();
    }
    
    public function rollback() {
        return $this->connection->rollback();
    }
    
    public function errorInfo() {
        return $this->connection->errorInfo();
    }
    
    /**
     * Check if there are any orphaned transactions and clean them up
     * This helps prevent "There is already an active transaction" errors
     */
    public function cleanupOrphanedTransactions() {
        try {
            if ($this->connection->inTransaction()) {
                error_log("Database: Found orphaned transaction, performing cleanup rollback");
                $this->connection->rollback();
                error_log("Database: Orphaned transaction cleaned up successfully");
                return true;
            }
            return false;
        } catch (\Throwable $e) {
            error_log("Database: Error during orphaned transaction cleanup (ignored): " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get detailed transaction state for debugging
     */
    public function getTransactionState() {
        return [
            'in_transaction' => $this->connection->inTransaction(),
            'autocommit' => $this->connection->getAttribute(PDO::ATTR_AUTOCOMMIT),
            'connection_status' => $this->connection->getAttribute(PDO::ATTR_CONNECTION_STATUS)
        ];
    }
}

// Global function to get database instance
function db() {
    return Database::getInstance();
}
?>
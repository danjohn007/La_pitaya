<?php
abstract class BaseModel {
    protected $db;
    protected $table;
    protected $primaryKey = 'id';
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    public function find($id) {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE {$this->primaryKey} = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
    
    public function findBy($field, $value) {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE {$field} = ?");
        $stmt->execute([$value]);
        return $stmt->fetch();
    }
    
    public function findAll($conditions = [], $orderBy = null, $limit = null) {
        $query = "SELECT * FROM {$this->table}";
        $params = [];
        
        if (!empty($conditions)) {
            $whereClauses = [];
            foreach ($conditions as $field => $value) {
                $whereClauses[] = "{$field} = ?";
                $params[] = $value;
            }
            $query .= " WHERE " . implode(' AND ', $whereClauses);
        }
        
        if ($orderBy) {
            $query .= " ORDER BY {$orderBy}";
        }
        
        if ($limit) {
            $query .= " LIMIT {$limit}";
        }
        
        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    public function create($data) {
        $fields = array_keys($data);
        $placeholders = str_repeat('?,', count($fields) - 1) . '?';
        
        $query = "INSERT INTO {$this->table} (" . implode(',', $fields) . ") VALUES ({$placeholders})";
        
        try {
            $stmt = $this->db->prepare($query);
            
            if (!$stmt) {
                $errorInfo = $this->db->getConnection()->errorInfo();
                error_log("BaseModel::create PREPARE FAILED - Table: {$this->table}, Error: " . json_encode($errorInfo) . ", Query: $query");
                return false;
            }
            
            $success = $stmt->execute(array_values($data));
            
            if ($success) {
                $insertId = $this->db->lastInsertId();
                
                // Validate that we got a valid ID
                if ($insertId && $insertId > 0) {
                    error_log("BaseModel::create SUCCESS - Table: {$this->table}, ID: $insertId, Data: " . json_encode($data));
                    return $insertId;
                } else {
                    // Check if the query actually succeeded by trying to find the record that matches our data
                    // This is safer than just getting the last record, in case of concurrent inserts
                    
                    // Build a WHERE clause based on some unique or identifying fields
                    $whereConditions = [];
                    $whereParams = [];
                    
                    // For tickets table, we can use ticket_number which should be unique
                    if ($this->table === 'tickets' && isset($data['ticket_number'])) {
                        $whereConditions[] = "ticket_number = ?";
                        $whereParams[] = $data['ticket_number'];
                    } else {
                        // Fallback: use all fields except id and timestamps for matching
                        foreach ($data as $field => $value) {
                            if (!in_array($field, ['id', 'created_at', 'updated_at']) && $value !== null) {
                                $whereConditions[] = "{$field} = ?";
                                $whereParams[] = $value;
                            }
                        }
                        // Limit to most recent records to avoid conflicts
                        $whereConditions[] = "created_at >= DATE_SUB(NOW(), INTERVAL 1 MINUTE)";
                    }
                    
                    if (!empty($whereConditions)) {
                        $checkQuery = "SELECT id FROM {$this->table} WHERE " . implode(' AND ', $whereConditions) . " ORDER BY id DESC LIMIT 1";
                        $checkStmt = $this->db->prepare($checkQuery);
                        $checkStmt->execute($whereParams);
                        $lastRecord = $checkStmt->fetch();
                        
                        if ($lastRecord && isset($lastRecord['id'])) {
                            $insertId = $lastRecord['id'];
                            error_log("BaseModel::create SUCCESS (recovered ID via WHERE match) - Table: {$this->table}, ID: $insertId, Data: " . json_encode($data));
                            return $insertId;
                        }
                    }
                    
                    // Last resort: get the most recent record (less safe but better than failing)
                    $checkQuery = "SELECT id FROM {$this->table} ORDER BY id DESC LIMIT 1";
                    $checkStmt = $this->db->prepare($checkQuery);
                    $checkStmt->execute();
                    $lastRecord = $checkStmt->fetch();
                    
                    if ($lastRecord && isset($lastRecord['id'])) {
                        $insertId = $lastRecord['id'];
                        error_log("BaseModel::create SUCCESS (recovered ID via last record fallback) - Table: {$this->table}, ID: $insertId, Data: " . json_encode($data));
                        return $insertId;
                    } else {
                        error_log("BaseModel::create COMPLETE FAILURE - Table: {$this->table}, LastInsertId: " . var_export($insertId, true) . ", Could not recover ID, Data: " . json_encode($data));
                        return false;
                    }
                }
            } else {
                // Log execution failure with detailed error information
                $errorInfo = $stmt->errorInfo();
                $connectionError = $this->db->getConnection()->errorInfo();
                error_log("BaseModel::create EXECUTION FAILED - Table: {$this->table}, Stmt Error: " . json_encode($errorInfo) . ", Connection Error: " . json_encode($connectionError) . ", Query: $query, Data: " . json_encode($data));
                return false;
            }
        } catch (PDOException $e) {
            // Specific PDO exception handling
            error_log("BaseModel::create PDO EXCEPTION - Table: {$this->table}, Error: " . $e->getMessage() . ", Code: " . $e->getCode() . ", Query: $query, Data: " . json_encode($data));
            return false;
        } catch (Exception $e) {
            // General exception handling
            error_log("BaseModel::create GENERAL EXCEPTION - Table: {$this->table}, Error: " . $e->getMessage() . ", Query: $query, Data: " . json_encode($data));
            return false;
        }
    }
    
    public function update($id, $data) {
        $fields = array_keys($data);
        $setClauses = [];
        
        foreach ($fields as $field) {
            $setClauses[] = "{$field} = ?";
        }
        
        $query = "UPDATE {$this->table} SET " . implode(',', $setClauses) . " WHERE {$this->primaryKey} = ?";
        $params = array_values($data);
        $params[] = $id;
        
        $stmt = $this->db->prepare($query);
        return $stmt->execute($params);
    }
    
    public function delete($id) {
        $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE {$this->primaryKey} = ?");
        return $stmt->execute([$id]);
    }
    
    public function softDelete($id) {
        return $this->update($id, ['active' => 0]);
    }
    
    public function count($conditions = []) {
        $query = "SELECT COUNT(*) as total FROM {$this->table}";
        $params = [];
        
        if (!empty($conditions)) {
            $whereClauses = [];
            foreach ($conditions as $field => $value) {
                $whereClauses[] = "{$field} = ?";
                $params[] = $value;
            }
            $query .= " WHERE " . implode(' AND ', $whereClauses);
        }
        
        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        $result = $stmt->fetch();
        
        return $result['total'] ?? 0;
    }
    
    public function paginate($page = 1, $perPage = 10, $conditions = [], $orderBy = null) {
        $offset = ($page - 1) * $perPage;
        
        // Get total count
        $total = $this->count($conditions);
        $totalPages = ceil($total / $perPage);
        
        // Get data
        $query = "SELECT * FROM {$this->table}";
        $params = [];
        
        if (!empty($conditions)) {
            $whereClauses = [];
            foreach ($conditions as $field => $value) {
                $whereClauses[] = "{$field} = ?";
                $params[] = $value;
            }
            $query .= " WHERE " . implode(' AND ', $whereClauses);
        }
        
        if ($orderBy) {
            $query .= " ORDER BY {$orderBy}";
        }
        
        $query .= " LIMIT {$perPage} OFFSET {$offset}";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        $data = $stmt->fetchAll();
        
        return [
            'data' => $data,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $totalPages,
                'has_next' => $page < $totalPages,
                'has_prev' => $page > 1
            ]
        ];
    }
}
?>
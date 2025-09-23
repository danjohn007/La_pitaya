<?php
class Ticket extends BaseModel {
    /**
     * Actualiza todos los tickets con el mismo ticket_number (para multiticket)
     */
    public function updateByTicketNumber($ticketNumber, $data) {
        $fields = array_keys($data);
        $setClauses = [];
        foreach ($fields as $field) {
            $setClauses[] = "{$field} = ?";
        }
        $query = "UPDATE {$this->table} SET " . implode(',', $setClauses) . " WHERE ticket_number = ?";
        $params = array_values($data);
        $params[] = $ticketNumber;
        $stmt = $this->db->prepare($query);
        return $stmt->execute($params);
    }
    protected $table = 'tickets';
    
    public function generateTicketNumber() {
        $date = date('Ymd');
        $query = "SELECT COUNT(*) as count FROM {$this->table} WHERE DATE(created_at) = CURDATE()";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        $result = $stmt->fetch();
        
        $sequential = str_pad($result['count'] + 1, 4, '0', STR_PAD_LEFT);
        return "T{$date}{$sequential}";
    }
    
    public function createTicket($orderId, $cashierId, $paymentMethod = 'efectivo', $manejaTransaccion = true) {
        $transaccionIniciada = false;
        $commitRealizado = false;
        try {
            // Log initial transaction state for debugging
            $initialTransactionState = $this->db->getConnection()->inTransaction() ? 'ACTIVE' : 'NONE';
            error_log("Transaction state at start: $initialTransactionState");
            
            // Clean up any orphaned transactions from previous errors
            $this->cleanupOrphanedTransactions();
            
            if ($manejaTransaccion) {
                // Only start transaction if not already in one
                if (!$this->db->getConnection()->inTransaction()) {
                    error_log("Starting new transaction for ticket creation");
                    $this->db->beginTransaction();
                    $transaccionIniciada = true; // Marca que sí se inició
                    error_log("New transaction started successfully");
                } else {
                    // Already in transaction, don't manage it
                    error_log("Already in transaction, will not manage transaction lifecycle");
                    $manejaTransaccion = false;
                }
            }
            
            // Validate database state before proceeding
            $this->validateDatabaseState();
            
            // Get order details
            $orderModel = new Order();
            $order = $orderModel->find($orderId);
            
            if (!$order) {
                throw new Exception('Orden no encontrada');
            }
            
            if ($order['status'] !== ORDER_READY) {
                throw new Exception('El pedido debe estar en estado "Listo" para generar el ticket');
            }
            
            // Check if order already has a ticket
            $existingTicket = $this->findBy('order_id', $orderId);
            if ($existingTicket) {
                throw new Exception('Este pedido ya tiene un ticket generado');
            }
            
            // Validate order is from today (cannot close orders from previous days without proper process)
            $orderDate = date('Y-m-d', strtotime($order['created_at']));
            $today = date('Y-m-d');
            if ($orderDate !== $today) {
                throw new Exception('Solo se pueden cerrar pedidos del día actual');
            }
            
            // Calculate totals with proper rounding
            // Prices already include 16% IVA, so we need to separate it
            $totalWithTax = floatval($order['total']);
            $subtotal = round($totalWithTax / 1.16, 2); // Remove 16% IVA to get subtotal
            $tax = round($totalWithTax - $subtotal, 2); // Calculate the IVA amount
            $total = $totalWithTax; // Total remains the same (NO incluye propina)
            
            // Validate data before insertion
            if ($subtotal <= 0) {
                throw new Exception('El subtotal debe ser mayor a cero');
            }
            
            if (!in_array($paymentMethod, ['efectivo', 'tarjeta', 'transferencia', 'intercambio', 'pendiente_por_cobrar'])) {
                throw new Exception('Método de pago inválido');
            }
            
            // Create ticket
            $ticketData = [
                'order_id' => intval($orderId),
                'ticket_number' => $this->generateTicketNumber(),
                'cashier_id' => intval($cashierId),
                'subtotal' => $subtotal,
                'tax' => $tax,
                'total' => $total, // NO incluye propina
                'payment_method' => $paymentMethod
                // tip_amount y tip_percentage se agregan por separado
            ];
            
            // Log ticket creation attempt for debugging
            error_log("Ticket creation attempt: " . json_encode($ticketData));
            
            if (empty($ticketData['ticket_number'])) {
                throw new Exception('No se pudo generar el número de ticket');
            }
            
            if ($ticketData['cashier_id'] <= 0) {
                throw new Exception('ID de cajero inválido');
            }
            
            if ($ticketData['order_id'] <= 0) {
                throw new Exception('ID de pedido inválido');
            }

            $this->validateTicketDataForSchema($ticketData);
            
            $ticketId = $this->create($ticketData);
            
            if (!$ticketId || $ticketId === false || $ticketId <= 0) {
                $errorInfo = $this->db->getConnection()->errorInfo();
                $connectionError = $this->db->errorInfo();
                error_log("Database error during ticket creation: Connection Error: " . json_encode($connectionError) . ", Error Info: " . json_encode($errorInfo));
                
                $errorMessage = 'Error desconocido';
                if (!empty($errorInfo[2])) {
                    $errorMessage = $errorInfo[2];
                } elseif (!empty($connectionError[2])) {
                    $errorMessage = $connectionError[2];
                } elseif ($ticketId === false) {
                    $errorMessage = 'Falló la inserción en la base de datos';
                } elseif ($ticketId <= 0) {
                    $errorMessage = 'ID de ticket inválido: ' . var_export($ticketId, true);
                }
                
                throw new Exception('Error al crear el ticket en la base de datos: ' . $errorMessage);
            }
            
            // Update order status (without starting new transaction)
            error_log("Antes de updateOrderStatus");
            $orderModel->updateOrderStatus($orderId, ORDER_DELIVERED);
            error_log("Después de updateOrderStatus");

            // Update customer statistics if applicable
            if ($order['customer_id']) {
                error_log("Antes de updateStats");
                $customerModel = new Customer();
                $customerModel->updateStats($order['customer_id'], $order['total']);
                error_log("Después de updateStats");
            }

            // Deduct inventory for the ticket
            error_log("Antes de deductInventoryForTicket");
            $this->deductInventoryForTicket($ticketId, $orderId, $cashierId);
            error_log("Después de deductInventoryForTicket");

            // Free the table
            error_log("Antes de freeTable");
            $tableModel = new Table();
            $tableModel->freeTable($order['table_id']);
            error_log("Después de freeTable");
            
            if ($manejaTransaccion && $transaccionIniciada) {
                error_log("Preparing to commit transaction");
                if ($this->db->getConnection()->inTransaction()) {
                    $this->db->commit();
                    $commitRealizado = true;
                    error_log("Transaction committed successfully");
                } else {
                    error_log("Warning: No active transaction found during commit attempt");
                }
            }
            error_log("Ticket created successfully with ID: $ticketId");
            return $ticketId;
            
        } catch (Exception $e) {
            error_log("Exception occurred during ticket creation: " . $e->getMessage());
            // Solo intenta rollback si la transacción fue iniciada aquí, no has hecho commit, y hay una transacción activa
            if ($manejaTransaccion && $transaccionIniciada && !$commitRealizado && $this->db->getConnection()->inTransaction()) {
                try {
                    error_log("Attempting rollback due to error");
                    $this->db->rollback();
                    error_log("Rollback completed successfully");
                } catch (\Throwable $rollbackError) {
                    // Log rollback error but don't throw it, focus on original error
                    error_log("Rollback error in createTicket (ignored): " . $rollbackError->getMessage());
                }
            } else {
                error_log("Rollback not attempted - Transaction state: initiated=$transaccionIniciada, committed=$commitRealizado, active=" . ($this->db->getConnection()->inTransaction() ? 'YES' : 'NO'));
            }
            error_log("Ticket creation failed: " . $e->getMessage());
            throw $e;
        }
    }

    public function getTipsByDate($dateFrom, $dateTo) {
        $query = "SELECT 
                    t.ticket_number,
                    MAX(t.tip_amount) as tip_amount,
                    MAX(t.tip_percentage) as tip_percentage,
                    MAX(t.tip_date) as tip_date,
                    MAX(u.name) as cashier_name
                FROM {$this->table} t
                JOIN users u ON t.cashier_id = u.id
                WHERE t.tip_amount IS NOT NULL AND t.tip_amount > 0
                    AND t.tip_date BETWEEN ? AND ?
                GROUP BY t.ticket_number
                ORDER BY MAX(t.tip_date) DESC, t.ticket_number DESC";
        $stmt = $this->db->prepare($query);
        $stmt->execute([$dateFrom, $dateTo]);
        return $stmt->fetchAll();
        }
    
    /**
     * Clean up any orphaned transactions from previous errors
     * This ensures we start with a clean transaction state
     */
    private function cleanupOrphanedTransactions() {
        try {
            $connection = $this->db->getConnection();
            if ($connection->inTransaction()) {
                error_log("Warning: Found orphaned transaction, cleaning up");
                $connection->rollback();
                error_log("Orphaned transaction rolled back successfully");
            }
        } catch (\Throwable $e) {
            error_log("Error cleaning up orphaned transactions (ignored): " . $e->getMessage());
            // If we can't clean up, log but continue - the transaction might not be in a rollback-able state
        }
    }
    
    public function crearCuentasSeparadas($orders, $cashierId, $paymentMethod) {
        $ticketIds = [];
        try {
            // Clean up any orphaned transactions
            $this->cleanupOrphanedTransactions();
            
            error_log("Starting transaction for separated accounts");
            $this->db->beginTransaction();

            foreach ($orders as $order) {
                error_log("Creating separated ticket for order: " . $order['id']);
                $ticketIds[] = $this->createTicket($order['id'], $cashierId, $paymentMethod, false); // OJO: false aquí
            }

            error_log("Committing separated accounts transaction");
            $this->db->commit();
            error_log("Separated accounts transaction committed successfully");
            return $ticketIds;
        } catch (Exception $e) {
            error_log("Error in crearCuentasSeparadas: " . $e->getMessage());
            // Only rollback if transaction is still active
            if ($this->db->getConnection()->inTransaction()) {
                try {
                    error_log("Rolling back separated accounts transaction");
                    $this->db->rollback();
                    error_log("Separated accounts rollback completed");
                } catch (\Throwable $rollbackError) {
                    // Log rollback error but don't throw it
                    error_log("Rollback error in crearCuentasSeparadas (ignored): " . $rollbackError->getMessage());
                }
            }
            throw $e;
        }
    }
    
    public function createTicketFromMultipleOrders($orderIds, $cashierId, $paymentMethod = 'efectivo', $groupBy = 'customer', $manejaTransaccion = true) {
        $transaccionIniciada = false;
        $commitRealizado = false;
        try {
            // Log initial transaction state
            $initialTransactionState = $this->db->getConnection()->inTransaction() ? 'ACTIVE' : 'NONE';
            error_log("Multiple orders ticket - Transaction state at start: $initialTransactionState");
            
            if ($manejaTransaccion) {
                // Clean up orphaned transactions
                $this->cleanupOrphanedTransactions();
                
                // Only start transaction if not already in one
                if (!$this->db->getConnection()->inTransaction()) {
                    error_log("Starting new transaction for multiple orders ticket");
                    $this->db->beginTransaction();
                    $transaccionIniciada = true;
                    error_log("Multiple orders transaction started successfully");
                } else {
                    // Already in transaction, don't manage it
                    error_log("Already in transaction, will not manage transaction lifecycle for multiple orders");
                    $manejaTransaccion = false;
                }
            }
            
            // Validate database state before proceeding
            $this->validateDatabaseState();
            
            // Get order details and validate based on grouping mode
            $orderModel = new Order();
            $orders = [];
            $tableId = null;
            $waiterId = null;
            $customerId = null;
            $customerName = null;
            $orderDate = null;
            $totalSubtotal = 0;
            
            foreach ($orderIds as $orderId) {
                $order = $orderModel->find($orderId);
                if (!$order) {
                    throw new Exception("Orden {$orderId} no encontrada");
                }
                
                if ($order['status'] !== ORDER_READY) {
                    throw new Exception("La orden {$orderId} no está en estado 'Listo'");
                }
                
                // Check if order already has a ticket
                $existingTicket = $this->findBy('order_id', $orderId);
                if ($existingTicket) {
                    throw new Exception("La orden {$orderId} ya tiene un ticket generado");
                }
                
                // Validate all orders are from the same table (always required - can't unite different tables)
                if ($tableId === null) {
                    $tableId = $order['table_id'];
                } elseif ($tableId !== $order['table_id']) {
                    throw new Exception('No se pueden unir pedidos de mesas diferentes');
                }
                
                // Group validation based on mode
                if ($groupBy === 'table') {
                    // Original behavior: validate same table (already done above), same waiter
                    if ($waiterId === null) {
                        $waiterId = $order['waiter_id'];
                    } elseif ($waiterId !== $order['waiter_id']) {
                        throw new Exception('Solo se pueden unir pedidos del mismo mesero');
                    }
                } else {
                    // New behavior: group by customer
                    $orderCustomerName = !empty($order['customer_name']) ? trim($order['customer_name']) : 'Sin cliente asignado';
                    
                    if ($customerName === null) {
                        $customerName = $orderCustomerName;
                        $customerId = $order['customer_id']; // May be null for walk-ins
                    } elseif ($customerName !== $orderCustomerName) {
                        throw new Exception('Solo se pueden unir pedidos del mismo cliente');
                    }
                }
                
                // Validate all orders are from the same day
                $currentOrderDate = date('Y-m-d', strtotime($order['created_at']));
                if ($orderDate === null) {
                    $orderDate = $currentOrderDate;
                } elseif ($orderDate !== $currentOrderDate) {
                    throw new Exception('Solo se pueden unir pedidos del mismo día');
                }
                
                $orders[] = $order;
                $totalSubtotal += $order['total'];
            }
            
            if (empty($orders)) {
                throw new Exception('No se encontraron órdenes válidas');
            }
            
            // Calculate totals with proper rounding
            // Prices already include 16% IVA, so we need to separate it
            $totalWithTax = $totalSubtotal;
            $subtotal = round($totalWithTax / 1.16, 2); // Remove 16% IVA to get subtotal
            $tax = round($totalWithTax - $subtotal, 2); // Calculate the IVA amount
            $total = $totalWithTax; // Total remains the same
            
            // Validate data before insertion
            if ($subtotal <= 0) {
                throw new Exception('El subtotal debe ser mayor a cero');
            }
            
            if (!in_array($paymentMethod, ['efectivo', 'tarjeta', 'transferencia', 'intercambio', 'pendiente_por_cobrar'])) {
                throw new Exception('Método de pago inválido');
            }
            

            // Crear un ticket_number único para todos los pedidos
            $ticket_number = $this->generateTicketNumber();

            // Crear un ticket para cada pedido con el mismo ticket_number
            $ticketIds = [];
            foreach ($orders as $order) {
                $ticketData = [
                    'order_id' => intval($order['id']),
                    'ticket_number' => $ticket_number,
                    'cashier_id' => intval($cashierId),
                    'subtotal' => round($order['total'] / 1.16, 2),
                    'tax' => round($order['total'] - ($order['total'] / 1.16), 2),
                    'total' => $order['total'],
                    'payment_method' => $paymentMethod
                ];
                // Validar datos antes de insertar
                $this->validateTicketDataForSchema($ticketData);
                $ticketId = $this->create($ticketData);
                if (!$ticketId || $ticketId === false || $ticketId <= 0) {
                    $errorInfo = $this->db->getConnection()->errorInfo();
                    $errorMessage = $this->getDatabaseErrorMessage($ticketId);
                    throw new Exception("Error al crear el ticket en la base de datos: " . $errorMessage);
                }
                $ticketIds[] = $ticketId;
            }
            
            // Update all order statuses to delivered and customer stats
            $customerModel = new Customer();
            foreach ($orders as $order) {
                $orderModel->updateOrderStatus($order['id'], ORDER_DELIVERED);
                
                // Update customer statistics if order has a customer
                if ($order['customer_id']) {
                    $customerModel->updateStats($order['customer_id'], $order['total']);
                }
            }
            
            // Deduct inventory for all orders
            $this->deductInventoryForMultipleOrders($ticketId, $orderIds, $cashierId);
            
            // Free the table (set to available and clear waiter assignment) since the ticket has been generated
            $tableModel = new Table();
            $tableModel->freeTable($tableId);
            
            if ($manejaTransaccion && $transaccionIniciada) {
                error_log("Preparing to commit multiple orders transaction");
                if ($this->db->getConnection()->inTransaction()) {
                    $this->db->commit();
                    $commitRealizado = true;
                    error_log("Multiple orders transaction committed successfully");
                } else {
                    error_log("Warning: No active transaction found during multiple orders commit attempt");
                }
            }
            error_log("Multiple orders ticket created successfully with ID: $ticketId");
            return $ticketId;
            
        } catch (Exception $e) {
            error_log("Exception occurred during multiple orders ticket creation: " . $e->getMessage());
            if ($manejaTransaccion && $transaccionIniciada && !$commitRealizado && $this->db->getConnection()->inTransaction()) {
                try {
                    error_log("Attempting rollback for multiple orders due to error");
                    $this->db->rollback();
                    error_log("Multiple orders rollback completed successfully");
                } catch (\Throwable $rollbackError) {
                    // Log rollback error but don't throw it
                    error_log("Rollback error in createTicketFromMultipleOrders (ignored): " . $rollbackError->getMessage());
                }
            } else {
                error_log("Multiple orders rollback not attempted - Transaction state: initiated=$transaccionIniciada, committed=$commitRealizado, active=" . ($this->db->getConnection()->inTransaction() ? 'YES' : 'NO'));
            }
            error_log("Multiple orders ticket creation failed: " . $e->getMessage());
            throw $e;
        }
    }
    
    public function createTicketFromMultipleOrdersWithoutTableFree($orderIds, $cashierId, $paymentMethod = 'efectivo', $manejaTransaccion = true) {
        $transaccionIniciada = false;
        $commitRealizado = false;
        try {
            // Log initial transaction state
            $initialTransactionState = $this->db->getConnection()->inTransaction() ? 'ACTIVE' : 'NONE';
            error_log("Multiple orders without table free - Transaction state at start: $initialTransactionState");
            
            if ($manejaTransaccion) {
                // Clean up orphaned transactions
                $this->cleanupOrphanedTransactions();
                
                // Only start transaction if not already in one
                if (!$this->db->getConnection()->inTransaction()) {
                    error_log("Starting new transaction for multiple orders without table free");
                    $this->db->beginTransaction();
                    $transaccionIniciada = true;
                    error_log("Multiple orders without table free transaction started successfully");
                } else {
                    // Already in transaction, don't manage it
                    error_log("Already in transaction, will not manage transaction lifecycle for multiple orders without table free");
                    $manejaTransaccion = false;
                }
            }
            
            // Validate database state before proceeding
            $this->validateDatabaseState();
            
            // Get order details and validate they're all from the same table, same day, and same waiter
            $orderModel = new Order();
            $orders = [];
            $tableId = null;
            $waiterId = null;
            $orderDate = null;
            $totalSubtotal = 0;
            
            foreach ($orderIds as $orderId) {
                $order = $orderModel->find($orderId);
                if (!$order) {
                    throw new Exception("Orden {$orderId} no encontrada");
                }
                
                if ($order['status'] !== ORDER_READY) {
                    throw new Exception("La orden {$orderId} no está en estado 'Listo'");
                }
                
                // Check if order already has a ticket
                $existingTicket = $this->findBy('order_id', $orderId);
                if ($existingTicket) {
                    throw new Exception("La orden {$orderId} ya tiene un ticket generado");
                }
                
                // Validate all orders are from the same table (always required - can't unite different tables)
                if ($tableId === null) {
                    $tableId = $order['table_id'];
                } elseif ($tableId !== $order['table_id']) {
                    throw new Exception('No se pueden unir pedidos de mesas diferentes');
                }
                
                // For separated customer tickets, we only validate same table since orders are pre-filtered by customer
                // No need for waiter validation as customer separation handles this differently
                
                // Validate all orders are from the same day
                $currentOrderDate = date('Y-m-d', strtotime($order['created_at']));
                if ($orderDate === null) {
                    $orderDate = $currentOrderDate;
                } elseif ($orderDate !== $currentOrderDate) {
                    throw new Exception('Solo se pueden unir pedidos del mismo día');
                }
                
                $orders[] = $order;
                $totalSubtotal += $order['total'];
            }
            
            if (empty($orders)) {
                throw new Exception('No se encontraron órdenes válidas');
            }
            
            // Calculate totals with proper rounding
            // Prices already include 16% IVA, so we need to separate it
            $totalWithTax = $totalSubtotal;
            $subtotal = round($totalWithTax / 1.16, 2); // Remove 16% IVA to get subtotal
            $tax = round($totalWithTax - $subtotal, 2); // Calculate the IVA amount
            $total = $totalWithTax; // Total remains the same
            
            // Validate data before insertion
            if ($subtotal <= 0) {
                throw new Exception('El subtotal debe ser mayor a cero');
            }
            
            if (!in_array($paymentMethod, ['efectivo', 'tarjeta', 'transferencia', 'intercambio', 'pendiente_por_cobrar'])) {
                throw new Exception('Método de pago inválido');
            }
            
            // Create ticket for the first order (as main order)
            $mainOrder = $orders[0];
            $ticketData = [
                'order_id' => intval($mainOrder['id']),
                'ticket_number' => $this->generateTicketNumber(),
                'cashier_id' => intval($cashierId),
                'subtotal' => $subtotal,
                'tax' => $tax,
                'total' => $total,
                'payment_method' => $paymentMethod
            ];
            
            // Log ticket creation attempt for debugging
            error_log("Multiple orders ticket creation attempt (without table free): " . json_encode($ticketData));
            
            // Validate required fields before database insertion
            if (empty($ticketData['ticket_number'])) {
                throw new Exception('No se pudo generar el número de ticket');
            }
            
            if ($ticketData['cashier_id'] <= 0) {
                throw new Exception('ID de cajero inválido');
            }
            
            if ($ticketData['order_id'] <= 0) {
                throw new Exception('ID de pedido principal inválido');
            }
            
            // Additional validation: Check if database schema supports the data we're trying to insert
            $this->validateTicketDataForSchema($ticketData);
            
            $ticketId = $this->create($ticketData);
            
            if (!$ticketId || $ticketId === false || $ticketId <= 0) {
                $errorMessage = $this->getDatabaseErrorMessage($ticketId);
                throw new Exception("Error al crear el ticket en la base de datos: " . $errorMessage);
            }
            
            // Update all order statuses to delivered and customer stats
            $customerModel = new Customer();
            foreach ($orders as $order) {
                $orderModel->updateOrderStatus($order['id'], ORDER_DELIVERED);
                
                // Update customer statistics if order has a customer
                if ($order['customer_id']) {
                    $customerModel->updateStats($order['customer_id'], $order['total']);
                }
            }
            
            // Deduct inventory for all orders
            $this->deductInventoryForMultipleOrders($ticketId, $orderIds, $cashierId);
            
            // DO NOT free the table here - let the calling method handle it
            
            if ($manejaTransaccion && $transaccionIniciada) {
                error_log("Preparing to commit multiple orders without table free transaction");
                if ($this->db->getConnection()->inTransaction()) {
                    $this->db->commit();
                    $commitRealizado = true;
                    error_log("Multiple orders without table free transaction committed successfully");
                } else {
                    error_log("Warning: No active transaction found during multiple orders without table free commit attempt");
                }
            }
            error_log("Multiple orders ticket created successfully (without table free) with ID: $ticketId");
            return $ticketId;
            
        } catch (Exception $e) {
            error_log("Exception occurred during multiple orders without table free ticket creation: " . $e->getMessage());
            if ($manejaTransaccion && $transaccionIniciada && !$commitRealizado && $this->db->getConnection()->inTransaction()) {
                try {
                    error_log("Attempting rollback for multiple orders without table free due to error");
                    $this->db->rollback();
                    error_log("Multiple orders without table free rollback completed successfully");
                } catch (\Throwable $rollbackError) {
                    // Log rollback error but don't throw it
                    error_log("Rollback error in createTicketFromMultipleOrdersWithoutTableFree (ignored): " . $rollbackError->getMessage());
                }
            } else {
                error_log("Multiple orders without table free rollback not attempted - Transaction state: initiated=$transaccionIniciada, committed=$commitRealizado, active=" . ($this->db->getConnection()->inTransaction() ? 'YES' : 'NO'));
            }
            error_log("Multiple orders ticket creation (without table free) failed: " . $e->getMessage());
            throw $e;
        }
    }
    
    public function getTicketWithDetails($ticketId) {
        // 1. Obtener el ticket principal
        $query = "SELECT t.*, o.table_id, o.waiter_id, o.notes as order_notes,
                         tb.number as table_number,
                         w.employee_code,
                         u_waiter.name as waiter_name,
                         u_cashier.name as cashier_name
                  FROM {$this->table} t
                  JOIN orders o ON t.order_id = o.id
                  JOIN tables tb ON o.table_id = tb.id
                  JOIN waiters w ON o.waiter_id = w.id
                  JOIN users u_waiter ON w.user_id = u_waiter.id
                  JOIN users u_cashier ON t.cashier_id = u_cashier.id
                  WHERE t.id = ?";

        $stmt = $this->db->prepare($query);
        $stmt->execute([$ticketId]);
        $ticket = $stmt->fetch();

        if (!$ticket) {
            return null;
        }

        // 2. Buscar el ticket_number
        $ticket_number = $ticket['ticket_number'];

        // 3. Obtener todos los pedidos asociados a ese ticket_number
        $stmt_orders = $this->db->prepare("SELECT o.* FROM orders o JOIN tickets t ON t.order_id = o.id WHERE t.ticket_number = ?");
        $stmt_orders->execute([$ticket_number]);
        $orders = $stmt_orders->fetchAll(PDO::FETCH_ASSOC);

        // 4. Obtener todos los productos de todos los pedidos agrupados por order_id
        $orders_items = [];
        $all_items = [];
        foreach ($orders as $order) {
            $stmt_items = $this->db->prepare("SELECT oi.*, d.name as dish_name, d.category, o.id as order_id FROM order_items oi JOIN dishes d ON oi.dish_id = d.id JOIN orders o ON oi.order_id = o.id WHERE o.id = ? ORDER BY oi.created_at ASC");
            $stmt_items->execute([$order['id']]);
            $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
            $orders_items[$order['id']] = $items;
            foreach ($items as $item) {
                $all_items[] = $item;
            }
        }

        // 5. Marcar todos los pedidos como entregados si no lo están
        foreach ($orders as $order) {
            if ($order['status'] !== 'entregado') {
                $stmt_update = $this->db->prepare("UPDATE orders SET status = 'entregado' WHERE id = ?");
                $stmt_update->execute([$order['id']]);
            }
        }

    // 6. Retornar los datos agregados
    $ticket['orders'] = $orders;
    $ticket['orders_items'] = $orders_items;
    $ticket['items'] = $all_items; // respaldo para la vista
    $ticket['order_ids'] = array_column($orders, 'id');
    return $ticket;
    }
    
    public function getTicketsByDate($date = null, $cashierId = null, $filters = []) {
        $date = $date ?: date('Y-m-d');
        $query = "SELECT 
                    t.ticket_number,
                    MIN(t.id) as id,
                    MAX(tb.number) as table_number,
                    MAX(u.name) as cashier_name,
                    GROUP_CONCAT(DISTINCT c.name SEPARATOR ', ') as customer_name,
                    GROUP_CONCAT(DISTINCT c.phone SEPARATOR ', ') as customer_phone,
                    GROUP_CONCAT(DISTINCT c.email SEPARATOR ', ') as customer_email,
                    GROUP_CONCAT(DISTINCT o.customer_name SEPARATOR ', ') as order_customer_name,
                    SUM(t.subtotal) as subtotal,
                    SUM(t.tax) as tax,
                    SUM(t.total) as total,
                    SUM(t.tip_amount) as tip_amount, -- Mostrar suma de propinas
                    MAX(t.tip_percentage) as tip_percentage, -- Mostrar porcentaje si existe
                    MAX(t.payment_method) as payment_method,
                    MAX(t.created_at) as created_at,
                    MAX(t.status) as status
                FROM {$this->table} t
                JOIN orders o ON t.order_id = o.id
                LEFT JOIN tables tb ON o.table_id = tb.id
                JOIN users u ON t.cashier_id = u.id
                LEFT JOIN customers c ON o.customer_id = c.id
                WHERE DATE(t.created_at) = ?";
        $params = [$date];
        if ($cashierId) {
            $query .= " AND t.cashier_id = ?";
            $params[] = $cashierId;
        }
        if (isset($filters['search']) && !empty($filters['search'])) {
            $searchTerm = '%' . $filters['search'] . '%';
            $query .= " AND (o.customer_name LIKE ? OR c.name LIKE ? OR c.phone LIKE ? OR c.email LIKE ? OR tb.number LIKE ?)";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        $query .= " GROUP BY t.ticket_number ORDER BY MAX(t.created_at) DESC";
        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    public function getDailySalesReport($date = null) {
        $date = $date ?: date('Y-m-d');
        
        $query = "SELECT 
                    COUNT(*) as total_tickets,
                    SUM(subtotal) as total_subtotal,
                    SUM(tax) as total_tax,
                    SUM(total) as total_amount,
                    payment_method,
                    COUNT(*) as method_count
                  FROM {$this->table}
                  WHERE DATE(created_at) = ? AND (status IS NULL OR status != 'cancelled')
                  GROUP BY payment_method";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$date]);
        
        $results = $stmt->fetchAll();
        
        // Get overall totals
        $totalQuery = "SELECT 
                        COUNT(*) as total_tickets,
                        SUM(subtotal) as total_subtotal,
                        SUM(tax) as total_tax,
                        SUM(total) as total_amount
                       FROM {$this->table}
                       WHERE DATE(created_at) = ? AND (status IS NULL OR status != 'cancelled')";
        
        $stmt = $this->db->prepare($totalQuery);
        $stmt->execute([$date]);
        $totals = $stmt->fetch();
        
        return [
            'by_payment_method' => $results,
            'totals' => $totals
        ];
    }
    
    public function getSalesReportData($startDate, $endDate) {
        $query = "SELECT 
                    DATE(t.created_at) as date,
                    COUNT(*) as total_tickets,
                    SUM(t.subtotal) as total_subtotal,
                    SUM(t.tax) as total_tax,
                    SUM(t.total) as total_amount,
                    t.payment_method,
                    COUNT(*) as method_count
                  FROM {$this->table} t
                  WHERE DATE(t.created_at) BETWEEN ? AND ?
                  GROUP BY DATE(t.created_at), t.payment_method
                  ORDER BY DATE(t.created_at) DESC, t.payment_method";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$startDate, $endDate]);
        
        return $stmt->fetchAll();
    }
    
    // ============= INCOME REPORTING METHODS =============
    
    public function getTotalIncome($dateFrom = null, $dateTo = null) {
        $dateFrom = $dateFrom ?: date('Y-m-01');
        $dateTo = $dateTo ?: date('Y-m-d');
        
        $query = "SELECT 
                    COUNT(*) as total_tickets,
                    SUM(subtotal) as total_subtotal,
                    SUM(tax) as total_tax,
                    SUM(total) as total_income
                  FROM {$this->table} 
                  WHERE DATE(created_at) BETWEEN ? AND ?
                    AND payment_method != 'pendiente_por_cobrar'
                    AND (status IS NULL OR status != 'cancelled')";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$dateFrom, $dateTo]);
        
        return $stmt->fetch() ?: [
            'total_tickets' => 0,
            'total_subtotal' => 0,
            'total_tax' => 0,
            'total_income' => 0
        ];
    }
    
    public function getIncomeByDate($dateFrom = null, $dateTo = null) {
        $dateFrom = $dateFrom ?: date('Y-m-01');
        $dateTo = $dateTo ?: date('Y-m-d');
        
        $query = "SELECT 
                    DATE(created_at) as date,
                    COUNT(*) as tickets_count,
                    SUM(subtotal) as subtotal,
                    SUM(tax) as tax,
                    SUM(total) as total_income
                  FROM {$this->table} 
                  WHERE DATE(created_at) BETWEEN ? AND ?
                    AND payment_method != 'pendiente_por_cobrar'
                    AND (status IS NULL OR status != 'cancelled')
                  GROUP BY DATE(created_at)
                  ORDER BY DATE(created_at) ASC";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$dateFrom, $dateTo]);
        
        return $stmt->fetchAll();
    }
    
    public function getIncomeByPaymentMethod($dateFrom = null, $dateTo = null) {
        $dateFrom = $dateFrom ?: date('Y-m-01');
        $dateTo = $dateTo ?: date('Y-m-d');
        
        $query = "SELECT 
                    payment_method,
                    COUNT(*) as tickets_count,
                    SUM(total) as total_income
                  FROM {$this->table} 
                  WHERE DATE(created_at) BETWEEN ? AND ?
                    AND (status IS NULL OR status != 'cancelled')
                  GROUP BY payment_method
                  ORDER BY total_income DESC";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$dateFrom, $dateTo]);
        
        return $stmt->fetchAll();
    }
    
    public function getIncomeVsExpensesData($dateFrom = null, $dateTo = null) {
        $dateFrom = $dateFrom ?: date('Y-m-01');
        $dateTo = $dateTo ?: date('Y-m-d');
        
        // Get income by date
        $incomeData = $this->getIncomeByDate($dateFrom, $dateTo);
        
        // Get expenses by date
        $expenseModel = new Expense();
        $expenseQuery = "SELECT 
                            DATE(expense_date) as date,
                            SUM(amount) as total_expenses
                         FROM expenses 
                         WHERE DATE(expense_date) BETWEEN ? AND ?
                         GROUP BY DATE(expense_date)
                         ORDER BY DATE(expense_date) ASC";
        
        $stmt = $this->db->prepare($expenseQuery);
        $stmt->execute([$dateFrom, $dateTo]);
        $expenseData = $stmt->fetchAll();
        
        // Get withdrawals by date
        $withdrawalModel = new CashWithdrawal();
        $withdrawalQuery = "SELECT 
                               DATE(withdrawal_date) as date,
                               SUM(amount) as total_withdrawals
                            FROM cash_withdrawals 
                            WHERE DATE(withdrawal_date) BETWEEN ? AND ?
                            GROUP BY DATE(withdrawal_date)
                            ORDER BY DATE(withdrawal_date) ASC";
        
        $stmt = $this->db->prepare($withdrawalQuery);
        $stmt->execute([$dateFrom, $dateTo]);
        $withdrawalData = $stmt->fetchAll();
        
        // Combine income, expense and withdrawal data by date
        $combinedData = [];
        $expenseByDate = [];
        $withdrawalByDate = [];
        
        foreach ($expenseData as $expense) {
            $expenseByDate[$expense['date']] = (float)$expense['total_expenses'];
        }
        
        foreach ($withdrawalData as $withdrawal) {
            $withdrawalByDate[$withdrawal['date']] = (float)$withdrawal['total_withdrawals'];
        }
        
        foreach ($incomeData as $income) {
            $date = $income['date'];
            $totalExpenses = ($expenseByDate[$date] ?? 0) + ($withdrawalByDate[$date] ?? 0);
            $combinedData[] = [
                'date' => $date,
                'income' => (float)$income['total_income'],
                'expenses' => $expenseByDate[$date] ?? 0,
                'withdrawals' => $withdrawalByDate[$date] ?? 0,
                'total_expenses' => $totalExpenses,
                'net_profit' => (float)$income['total_income'] - $totalExpenses
            ];
        }
        
        return $combinedData;
    }
    
    public function getPendingPayments($filters = []) {
        $query = "SELECT t.*, 
                         tn.number as table_number,
                         u.name as cashier_name,
                         u_waiter.name as waiter_name,
                         w.employee_code,
                         c.name as customer_name, c.phone as customer_phone, c.email as customer_email,
                         o.customer_name as order_customer_name
                  FROM tickets t
                  LEFT JOIN orders o ON t.order_id = o.id
                  LEFT JOIN tables tn ON o.table_id = tn.id
                  LEFT JOIN users u ON t.cashier_id = u.id
                  LEFT JOIN waiters w ON o.waiter_id = w.id
                  LEFT JOIN users u_waiter ON w.user_id = u_waiter.id
                  LEFT JOIN customers c ON o.customer_id = c.id
                  WHERE t.payment_method = 'pendiente_por_cobrar'";
        
        $params = [];
        
        // Add search functionality
        if (isset($filters['search']) && !empty($filters['search'])) {
            $searchTerm = '%' . $filters['search'] . '%';
            $query .= " AND (o.customer_name LIKE ? OR c.name LIKE ? OR c.phone LIKE ? OR c.email LIKE ? OR tn.number LIKE ?)";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        $query .= " ORDER BY t.created_at DESC";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    public function updatePaymentMethod($ticketId, $paymentMethod) {
        $validMethods = ['efectivo', 'tarjeta', 'transferencia', 'intercambio', 'pendiente_por_cobrar'];
        if (!in_array($paymentMethod, $validMethods)) {
            return false;
        }
        
        $query = "UPDATE tickets SET payment_method = ? WHERE id = ?";
        $stmt = $this->db->prepare($query);
        return $stmt->execute([$paymentMethod, $ticketId]);
    }
    
    public function createExpiredOrderTicket($orderId, $cashierId, $paymentMethod = 'efectivo') {
        try {
            // Clean up orphaned transactions
            $this->cleanupOrphanedTransactions();
            
            error_log("Starting transaction for expired order ticket: Order ID $orderId");
            $this->db->beginTransaction();
            
            $orderModel = new Order();
            $tableModel = new Table();
            
            // Get order details
            $order = $orderModel->find($orderId);
            if (!$order) {
                throw new Exception('Pedido no encontrado');
            }
            
            if ($order['status'] !== ORDER_READY) {
                throw new Exception('El pedido debe estar en estado "Listo" para generar el ticket');
            }
            
            // Check if order already has a ticket
            $existingTicket = $this->findBy('order_id', $orderId);
            if ($existingTicket) {
                throw new Exception('Este pedido ya tiene un ticket generado');
            }
            
            // For expired orders, we allow ticket generation with today's date
            // This ensures the income is recorded for today's date in reports
            
            // Calculate totals with proper rounding
            // Prices already include 16% IVA, so we need to separate it
            $totalWithTax = floatval($order['total']);
            $subtotal = round($totalWithTax / 1.16, 2); // Remove 16% IVA to get subtotal
            $tax = round($totalWithTax - $subtotal, 2); // Calculate the IVA amount
            $total = $totalWithTax; // Total remains the same
            
            // Validate data before insertion
            if ($subtotal <= 0) {
                throw new Exception('El subtotal debe ser mayor a cero');
            }
            
            if (!in_array($paymentMethod, ['efectivo', 'tarjeta', 'transferencia', 'intercambio', 'pendiente_por_cobrar'])) {
                throw new Exception('Método de pago inválido');
            }
            
            // Create ticket with today's date for proper reporting
            $ticketData = [
                'order_id' => intval($orderId),
                'ticket_number' => $this->generateTicketNumber(),
                'cashier_id' => intval($cashierId),
                'subtotal' => $subtotal,
                'tax' => $tax,
                'total' => $total,
                'payment_method' => $paymentMethod,
                'created_at' => date('Y-m-d H:i:s') // Force today's date for reporting
            ];
            
            // Log ticket creation attempt for debugging
            error_log("Expired order ticket creation attempt: " . json_encode($ticketData));
            
            $ticketId = $this->create($ticketData);
            
            if (!$ticketId) {
                throw new Exception('Error al crear el ticket en la base de datos');
            }
            
            // Update order status (without starting new transaction)
            $orderModel->updateOrderStatus($orderId, ORDER_DELIVERED);
            
            // Update customer statistics if order has a customer
            if ($order['customer_id']) {
                $customerModel = new Customer();
                $customerModel->updateStats($order['customer_id'], $order['total']);
            }
            
            // Free the table (set to available) since the ticket has been generated
            if ($order['table_id']) {
                $tableModel->updateTableStatus($order['table_id'], TABLE_AVAILABLE);
            }
            
            error_log("Committing expired order transaction");
            $this->db->commit();
            error_log("Expired order ticket created successfully with ID: $ticketId");
            return $ticketId;
            
        } catch (Exception $e) {
            error_log("Exception occurred during expired order ticket creation: " . $e->getMessage());
            // Only rollback if transaction is still active
            if ($this->db->getConnection()->inTransaction()) {
                try {
                    error_log("Attempting rollback for expired order due to error");
                    $this->db->rollback();
                    error_log("Expired order rollback completed successfully");
                } catch (\Throwable $rollbackError) {
                    // Log rollback error but don't throw it
                    error_log("Rollback error in createExpiredOrderTicket (ignored): " . $rollbackError->getMessage());
                }
            }
            error_log("Expired order ticket creation failed: " . $e->getMessage());
            throw $e;
        }
    }
    
    public function getPaymentMethodStats($dateFrom = null, $dateTo = null) {
        $dateFrom = $dateFrom ?: date('Y-m-01');
        $dateTo = $dateTo ?: date('Y-m-d');
        
        $query = "SELECT 
                     payment_method,
                     COUNT(*) as ticket_count,
                     SUM(total) as total_amount
                  FROM tickets 
                  WHERE DATE(created_at) BETWEEN ? AND ?
                    AND (status IS NULL OR status != 'cancelled')
                  GROUP BY payment_method
                  ORDER BY total_amount DESC";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$dateFrom, $dateTo]);
        return $stmt->fetchAll();
    }
    
    public function getIntercambioTotal($dateFrom = null, $dateTo = null) {
        $dateFrom = $dateFrom ?: date('Y-m-01');
        $dateTo = $dateTo ?: date('Y-m-d');
        
        $query = "SELECT 
                     COUNT(*) as count,
                     COALESCE(SUM(total), 0) as total_amount
                  FROM tickets 
                  WHERE payment_method = 'intercambio'
                  AND DATE(created_at) BETWEEN ? AND ?
                  AND (status IS NULL OR status != 'cancelled')";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$dateFrom, $dateTo]);
        return $stmt->fetch();
    }
    
    public function getTicketsByPaymentMethod($paymentMethod, $dateFrom = null, $dateTo = null) {
        $dateFrom = $dateFrom ?: date('Y-m-01');
        $dateTo = $dateTo ?: date('Y-m-d');
        
        $query = "SELECT t.*, 
                         o.table_id,
                         tn.number as table_number,
                         u.name as cashier_name,
                         u_waiter.name as waiter_name,
                         w.employee_code
                  FROM tickets t
                  LEFT JOIN orders o ON t.order_id = o.id
                  LEFT JOIN tables tn ON o.table_id = tn.id
                  LEFT JOIN users u ON t.cashier_id = u.id
                  LEFT JOIN waiters w ON o.waiter_id = w.id
                  LEFT JOIN users u_waiter ON w.user_id = u_waiter.id
                  WHERE t.payment_method = ?
                    AND DATE(t.created_at) BETWEEN ? AND ?
                    AND (t.status IS NULL OR t.status != 'cancelled')
                  ORDER BY t.created_at DESC";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$paymentMethod, $dateFrom, $dateTo]);
        return $stmt->fetchAll();
    }
    
    public function getPendingPaymentTotal($dateFrom = null, $dateTo = null) {
        $dateFrom = $dateFrom ?: date('Y-m-01');
        $dateTo = $dateTo ?: date('Y-m-d');
        
        $query = "SELECT 
                     COUNT(*) as count,
                     COALESCE(SUM(total), 0) as total_amount
                  FROM tickets 
                  WHERE payment_method = 'pendiente_por_cobrar'
                  AND DATE(created_at) BETWEEN ? AND ?
                  AND (status IS NULL OR status != 'cancelled')";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$dateFrom, $dateTo]);
        return $stmt->fetch();
    }
    
    // ============= MÉTODOS DE INTEGRACIÓN CON INVENTARIO =============
    
    private function deductInventoryForTicket($ticketId, $orderId, $userId) {
        // Verificar si el inventario y la deducción automática están habilitados
        $systemSettingsModel = new SystemSettings();
        
        if (!$systemSettingsModel->isInventoryEnabled() || !$systemSettingsModel->isAutoDeductInventoryEnabled()) {
            return; // No hacer nada si no está habilitado
        }
        
        try {
            // Obtener los items del pedido
            $orderItemModel = new OrderItem();
            $orderItems = $orderItemModel->getItemsByOrder($orderId);
            
            $dishIngredientModel = new DishIngredient();
            
            foreach ($orderItems as $item) {
                // Descontar ingredientes por cada platillo vendido
                $dishIngredientModel->deductIngredientsForDish(
                    $item['dish_id'], 
                    $item['quantity'], 
                    $userId, 
                    $ticketId
                );
            }
            
        } catch (Exception $e) {
            // Log the error but don't fail the ticket creation
            error_log("Error deducting inventory for ticket {$ticketId}: " . $e->getMessage());
            // En producción podrías querer mostrar una advertencia al usuario
        }
    }
    
    private function deductInventoryForMultipleOrders($ticketId, $orderIds, $userId) {
        // Verificar si el inventario y la deducción automática están habilitados
        $systemSettingsModel = new SystemSettings();
        
        if (!$systemSettingsModel->isInventoryEnabled() || !$systemSettingsModel->isAutoDeductInventoryEnabled()) {
            return; // No hacer nada si no está habilitado
        }
        
        try {
            $orderItemModel = new OrderItem();
            $dishIngredientModel = new DishIngredient();
            
            foreach ($orderIds as $orderId) {
                // Obtener los items de cada pedido
                $orderItems = $orderItemModel->getItemsByOrder($orderId);
                
                foreach ($orderItems as $item) {
                    // Descontar ingredientes por cada platillo vendido
                    $dishIngredientModel->deductIngredientsForDish(
                        $item['dish_id'], 
                        $item['quantity'], 
                        $userId, 
                        $ticketId
                    );
                }
            }
            
        } catch (Exception $e) {
            // Log the error but don't fail the ticket creation
            error_log("Error deducting inventory for multiple orders ticket {$ticketId}: " . $e->getMessage());
        }
    }
    
    /**
     * Cancel a ticket and deduct from system income
     */
    public function cancelTicket($ticketId, $userId, $reason) {
        try {
            // Clean up orphaned transactions
            $this->cleanupOrphanedTransactions();
            
            error_log("Starting transaction for ticket cancellation: Ticket ID $ticketId");
            $this->db->beginTransaction();
            
            // Get ticket details before cancellation
            $ticket = $this->find($ticketId);
            if (!$ticket) {
                throw new Exception('Ticket no encontrado');
            }
            
            // Check if already cancelled
            if (isset($ticket['status']) && $ticket['status'] === 'cancelled') {
                throw new Exception('Este ticket ya está cancelado');
            }
            
            // Update ticket status
            $this->update($ticketId, [
                'status' => 'cancelled',
                'cancelled_at' => date('Y-m-d H:i:s'),
                'cancelled_by' => $userId,
                'cancellation_reason' => $reason
            ]);
            
            // Reverse order status back to ready (so it can be re-processed if needed)
            $orderModel = new Order();
            $orderModel->updateOrderStatus($ticket['order_id'], ORDER_READY);
            
            // Reverse customer statistics if applicable
            $order = $orderModel->find($ticket['order_id']);
            if ($order && $order['customer_id']) {
                $customerModel = new Customer();
                $customerModel->reverseStats($order['customer_id'], $ticket['total']);
            }
            
            // Log the cancellation for audit trail
            $this->logCancellation($ticketId, $userId, $reason, $ticket['total']);
            
            error_log("Committing ticket cancellation transaction");
            $this->db->commit();
            error_log("Ticket cancellation completed successfully");
            
            return true;
            
        } catch (Exception $e) {
            error_log("Exception occurred during ticket cancellation: " . $e->getMessage());
            // Only rollback if transaction is still active
            if ($this->db->getConnection()->inTransaction()) {
                try {
                    error_log("Attempting rollback for ticket cancellation due to error");
                    $this->db->rollback();
                    error_log("Ticket cancellation rollback completed successfully");
                } catch (\Throwable $rollbackError) {
                    // Log rollback error but don't throw it
                    error_log("Rollback error in cancelTicket (ignored): " . $rollbackError->getMessage());
                }
            }
            error_log("Ticket cancellation failed: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Log ticket cancellation for audit trail
     */
    private function logCancellation($ticketId, $userId, $reason, $amount) {
        // Create an audit log entry (you could create a separate audit table if needed)
        $query = "INSERT INTO ticket_cancellations (ticket_id, cancelled_by, reason, amount, cancelled_at) 
                  VALUES (?, ?, ?, ?, NOW())";
        
        try {
            $stmt = $this->db->prepare($query);
            $stmt->execute([$ticketId, $userId, $reason, $amount]);
        } catch (Exception $e) {
            // If audit table doesn't exist, just log to error log
            error_log("Ticket cancellation audit log: Ticket ID: $ticketId, User ID: $userId, Amount: $amount, Reason: $reason");
        }
    }
    
    /**
     * Get cancelled tickets for reporting
     */
    public function getCancelledTickets($filters = []) {
        $query = "SELECT t.*, 
                         u_cashier.name as cashier_name,
                         u_cancelled.name as cancelled_by_name,
                         o.table_id,
                         tb.number as table_number
                  FROM {$this->table} t
                  JOIN orders o ON t.order_id = o.id
                  LEFT JOIN tables tb ON o.table_id = tb.id
                  JOIN users u_cashier ON t.cashier_id = u_cashier.id
                  LEFT JOIN users u_cancelled ON t.cancelled_by = u_cancelled.id
                  WHERE t.status = 'cancelled'";
        
        $params = [];
        
        if (isset($filters['date_from'])) {
            $query .= " AND DATE(t.cancelled_at) >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (isset($filters['date_to'])) {
            $query .= " AND DATE(t.cancelled_at) <= ?";
            $params[] = $filters['date_to'];
        }
        
        $query .= " ORDER BY t.cancelled_at DESC";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Get detailed database error message for ticket creation failures
     */
    protected function getDatabaseErrorMessage($ticketId) {
        $errorInfo = $this->db->getConnection()->errorInfo();
        $connectionError = $this->db->errorInfo();
        
        // Log both error sources for debugging
        error_log("Ticket creation database error details: Connection Error: " . json_encode($connectionError) . ", PDO Error: " . json_encode($errorInfo));
        
        // Provide more specific error message based on common issues
        $errorMessage = 'Error desconocido';
        
        if (!empty($errorInfo[2])) {
            $errorMessage = $errorInfo[2];
            
            // Check for specific ENUM constraint violation
            if (strpos($errorInfo[2], 'Data too long for column') !== false || 
                strpos($errorInfo[2], 'ENUM') !== false ||
                strpos($errorInfo[2], 'payment_method') !== false) {
                $errorMessage = "Error de método de pago: El método seleccionado no está permitido en la base de datos. " . 
                               "Ejecute: mysql -u usuario -p base_datos < database/migration_payment_methods.sql";
            }
        } elseif (!empty($connectionError[2])) {
            $errorMessage = $connectionError[2];
        } elseif ($ticketId === false) {
            $errorMessage = 'Falló la inserción en la base de datos - Verifique la configuración de métodos de pago';
        } elseif ($ticketId <= 0) {
            $errorMessage = 'ID de ticket inválido: ' . var_export($ticketId, true);
        }
        
        return $errorMessage;
    }
    
    /**
     * Validate database connection and transaction state before ticket creation
     */
    protected function validateDatabaseState() {
        try {
            // Test database connection
            $testQuery = "SELECT 1 as test";
            $stmt = $this->db->prepare($testQuery);
            $result = $stmt->execute();
            
            if (!$result) {
                throw new Exception('La conexión a la base de datos no está funcionando correctamente');
            }
            
            // Check if we're in a transaction (we should be when creating tickets)
            $connection = $this->db->getConnection();
            if (!$connection->inTransaction()) {
                error_log("Warning: No transaction active when creating ticket");
            }
            
            return true;
        } catch (PDOException $e) {
            error_log("Database validation failed: " . $e->getMessage());
            throw new Exception('Error de conexión a la base de datos: ' . $e->getMessage());
        }
    }

    /**
     * Validate that the ticket data is compatible with the current database schema
     */
    protected function validateTicketDataForSchema($ticketData) {
        try {
            // Check if payment method is valid for current database schema
            $checkPaymentMethodQuery = "SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS 
                                       WHERE TABLE_SCHEMA = DATABASE() 
                                       AND TABLE_NAME = 'tickets' 
                                       AND COLUMN_NAME = 'payment_method'";
            
            $stmt = $this->db->prepare($checkPaymentMethodQuery);
            $stmt->execute();
            $columnInfo = $stmt->fetch();
            
            if ($columnInfo) {
                $enumValues = $columnInfo['COLUMN_TYPE'];
                error_log("Current payment_method ENUM values: " . $enumValues);
                
                // Extract enum values from the column type definition
                if (preg_match("/^enum\((.+)\)$/", $enumValues, $matches)) {
                    $enumOptions = str_getcsv($matches[1], ',', "'");
                    
                    if (!in_array($ticketData['payment_method'], $enumOptions)) {
                        $validOptions = implode(', ', $enumOptions);
                        
                        // Provide specific guidance based on missing payment method
                        $errorMessage = "Método de pago '{$ticketData['payment_method']}' no es válido para la base de datos actual.\n";
                        $errorMessage .= "Métodos válidos: {$validOptions}.\n";
                        
                        // Check if this is a known missing payment method that needs migration
                        $requiredMethods = ['efectivo', 'tarjeta', 'transferencia', 'intercambio', 'pendiente_por_cobrar'];
                        $missingMethods = array_diff($requiredMethods, $enumOptions);
                        
                        if (!empty($missingMethods)) {
                            $errorMessage .= "SOLUCIÓN: Debe ejecutar la migración de métodos de pago:\n";
                            $errorMessage .= "mysql -u usuario -p base_datos < database/migration_payment_methods.sql\n";
                            $errorMessage .= "Métodos faltantes: " . implode(', ', $missingMethods);
                        }
                        
                        throw new Exception($errorMessage);
                    }
                }
            }
            
            return true;
        } catch (PDOException $e) {
            error_log("Schema validation failed: " . $e->getMessage());
            throw new Exception('Error al validar el esquema de la base de datos: ' . $e->getMessage());
        }
    }

     /**
     * Agrega una propina manual (no asociada a ticket)
     */
    public function addManualTip($tipAmount, $userId) {
        $query = "INSERT INTO tickets (tip_amount, tip_date, tip_added_by, payment_method, subtotal, tax, total, created_at) VALUES (?, ?, ?, 'propina_manual', 0, 0, 0, NOW())";
        $stmt = $this->db->prepare($query);
        return $stmt->execute([$tipAmount, date('Y-m-d'), $userId]);
    }
}
?>
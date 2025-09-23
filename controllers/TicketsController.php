<?php
class TicketsController extends BaseController {
    private $ticketModel;
    private $orderModel;
    private $tableModel;
    private $systemSettingsModel;
    
    public function __construct() {
        parent::__construct();
        $this->requireAuth();
        $this->ticketModel = new Ticket();
        $this->orderModel = new Order();
        $this->tableModel = new Table();
        $this->systemSettingsModel = new SystemSettings();
    }
    
    public function index() {
        $user = $this->getCurrentUser();
        $filters = [];
        
        // Filter by cashier for non-admin users
        if ($user['role'] === ROLE_CASHIER) {
            $filters['cashier_id'] = $user['id'];
        }
        
        // Get date filter from request
        $date = $_GET['date'] ?? date('Y-m-d');
        
        // Get search filters
        $searchFilters = [];
        if (isset($_GET['search']) && !empty($_GET['search'])) {
            $searchFilters['search'] = $_GET['search'];
        }
        
        $tickets = $this->ticketModel->getTicketsByDate($date, $filters['cashier_id'] ?? null, $searchFilters);
        $salesReport = $this->ticketModel->getDailySalesReport($date);
        
        $this->view('tickets/index', [
            'tickets' => $tickets,
            'salesReport' => $salesReport,
            'selectedDate' => $date,
            'user' => $user
        ]);
    }
    
    public function create() {
        $user = $this->getCurrentUser();
        
        // Only cashiers and admins can create tickets
        if (!in_array($user['role'], [ROLE_CASHIER, ROLE_ADMIN])) {
            $this->redirect('tickets', 'error', 'No tienes permisos para generar tickets');
            return;
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->processCreate();
        } else {
            // Get ready orders grouped by table
            $tablesWithReadyOrders = $this->orderModel->getReadyOrdersGroupedByTable();
            
            $this->view('tickets/create', [
                'tables' => $tablesWithReadyOrders,
                'user' => $user
            ]);
        }
    }
    
    public function show($id) {
        $ticket = $this->ticketModel->getTicketWithDetails($id);
        if (!$ticket) {
            $this->redirect('tickets', 'error', 'Ticket no encontrado');
            return;
        }
        
        $user = $this->getCurrentUser();
        
        // Check permissions for cashiers
        if ($user['role'] === ROLE_CASHIER && $ticket['cashier_id'] != $user['id']) {
            $this->redirect('tickets', 'error', 'No tienes permisos para ver este ticket');
            return;
        }
        
        $this->view('tickets/view', [
            'ticket' => $ticket
        ]);
    }
    
    public function print($id) {
        $ticket = $this->ticketModel->getTicketWithDetails($id);
        if (!$ticket) {
            $this->redirect('tickets', 'error', 'Ticket no encontrado');
            return;
        }
        
        $user = $this->getCurrentUser();
        
        // Check permissions for cashiers
        if ($user['role'] === ROLE_CASHIER && $ticket['cashier_id'] != $user['id']) {
            $this->redirect('tickets', 'error', 'No tienes permisos para imprimir este ticket');
            return;
        }
        
        $this->view('tickets/print', [
            'ticket' => $ticket
        ]);
    }
    
    public function delete($id) {
        $ticket = $this->ticketModel->find($id);
        if (!$ticket) {
            $this->redirect('tickets', 'error', 'Ticket no encontrado');
            return;
        }
        
        $user = $this->getCurrentUser();
        
        // Only admins can delete tickets
        if ($user['role'] !== ROLE_ADMIN) {
            $this->redirect('tickets', 'error', 'No tienes permisos para eliminar tickets');
            return;
        }
        
        try {
            $this->ticketModel->delete($id);
            $this->redirect('tickets', 'success', 'Ticket eliminado correctamente');
        } catch (Exception $e) {
            $this->redirect('tickets', 'error', 'Error al eliminar el ticket: ' . $e->getMessage());
        }
    }
    
    public function report() {
        $user = $this->getCurrentUser();
        
        // Only admins and cashiers can view reports
        if (!in_array($user['role'], [ROLE_ADMIN, ROLE_CASHIER])) {
            $this->redirect('tickets', 'error', 'No tienes permisos para ver reportes');
            return;
        }
        
        $startDate = $_GET['start_date'] ?? date('Y-m-d');
        $endDate = $_GET['end_date'] ?? date('Y-m-d');
        
        // Get sales data for the date range
        $salesData = $this->getSalesReportData($startDate, $endDate);
        
        $this->view('tickets/report', [
            'salesData' => $salesData,
            'startDate' => $startDate,
            'endDate' => $endDate
        ]);
    }
    
    private function processCreate() {
        $errors = $this->validateTicketInput($_POST);
        
        if (!empty($errors)) {
            $tablesWithReadyOrders = $this->orderModel->getReadyOrdersGroupedByTable();
            $this->view('tickets/create', [
                'errors' => $errors,
                'old' => $_POST,
                'tables' => $tablesWithReadyOrders
            ]);
            return;
        }
        
        $user = $this->getCurrentUser();
        $paymentMethod = $_POST['payment_method'] ?? 'efectivo';
        
        try {
            // Check if we're creating a ticket for multiple orders or single order
            if (isset($_POST['table_id'])) {
                // Multiple orders from a table
                $tableId = $_POST['table_id'];
                
                // Get all ready orders for this table
                $readyOrders = $this->orderModel->getOrdersReadyForTicket();
                $tableOrders = array_filter($readyOrders, function($order) use ($tableId) {
                    return $order['table_id'] == $tableId;
                });
                
                if (empty($tableOrders)) {
                    throw new Exception('No hay pedidos listos para esta mesa');
                }
                
                // Check if customer separation is requested
                if (isset($_POST['separation_mode']) && $_POST['separation_mode'] === 'by_customer' && 
                    isset($_POST['separate_customers']) && is_array($_POST['separate_customers'])) {
                    
                    $customerPaymentMethods = $_POST['customer_payment_methods'] ?? [];
                    $ticketIds = $this->createSeparatedTicketsByCustomer($tableOrders, $_POST['separate_customers'], $user['id'], $paymentMethod, $customerPaymentMethods);
                    
                    if (count($ticketIds) > 1) {
                        // Get ticket numbers for the success message
                        $ticketNumbers = [];
                        foreach ($ticketIds as $ticketId) {
                            $ticket = $this->ticketModel->find($ticketId);
                            if ($ticket) {
                                $ticketNumbers[] = $ticket['ticket_number'];
                            }
                        }
                        $ticketNumbersList = implode(', ', $ticketNumbers);
                        $this->redirect('tickets', 'success', 'Se generaron ' . count($ticketIds) . ' tickets separados correctamente: ' . $ticketNumbersList . '. Puede buscar e imprimir cada ticket individualmente.');
                    } else {
                        $this->redirect('tickets/show/' . $ticketIds[0], 'success', 'Ticket generado correctamente');
                    }
                } else {
                    // Regular multiple order ticket - now grouped by customer by default
                    // Check if explicit table grouping is requested, otherwise group by customer
                    $groupBy = isset($_POST['group_by']) && $_POST['group_by'] === 'table' ? 'table' : 'customer';
                    
                    $orderIds = array_map(function($order) { return $order['id']; }, $tableOrders);
                    $ticketId = $this->ticketModel->createTicketFromMultipleOrders($orderIds, $user['id'], $paymentMethod, $groupBy);
                    
                    $this->redirect('tickets/show/' . $ticketId, 'success', 'Ticket generado correctamente');
                }
            } else {
                // Single order (backward compatibility)
                $orderId = $_POST['order_id'];
                $ticketId = $this->ticketModel->createTicket($orderId, $user['id'], $paymentMethod);
                
                $this->redirect('tickets/show/' . $ticketId, 'success', 'Ticket generado correctamente');
            }
        } catch (Exception $e) {
            $tablesWithReadyOrders = $this->orderModel->getReadyOrdersGroupedByTable();
            $this->view('tickets/create', [
                'error' => 'Error al generar el ticket: ' . $e->getMessage(),
                'old' => $_POST,
                'tables' => $tablesWithReadyOrders
            ]);
        }
    }
    
    private function validateTicketInput($data) {
        $errors = $this->validateInput($data, [
            'payment_method' => ['required' => true]
        ]);
        
        // Get available payment methods based on system settings
        $systemSettingsModel = new SystemSettings();
        $validMethods = ['efectivo', 'tarjeta', 'transferencia', 'intercambio'];
        
        // Add collections method only if enabled
        if ($systemSettingsModel->isCollectionsEnabled()) {
            $validMethods[] = 'pendiente_por_cobrar';
        }
        
        // Validate payment method
        if (!in_array($data['payment_method'] ?? '', $validMethods)) {
            $errors['payment_method'] = 'Método de pago inválido o no disponible';
        }
        
        // Special validation for collections
        if ($data['payment_method'] === 'pendiente_por_cobrar' && !$systemSettingsModel->isCollectionsEnabled()) {
            $errors['payment_method'] = 'Las cuentas por cobrar están deshabilitadas';
        }
        
        // Validate that either table_id or order_id is provided
        if (empty($data['table_id']) && empty($data['order_id'])) {
            $errors['selection'] = 'Debe seleccionar una mesa o un pedido para generar el ticket';
        }
        
        // Validate table selection (for multiple orders)
        if (!empty($data['table_id'])) {
            $tableId = $data['table_id'];
            
            // Check that the table has ready orders
            $readyOrders = $this->orderModel->getOrdersReadyForTicket();
            $tableOrders = array_filter($readyOrders, function($order) use ($tableId) {
                return $order['table_id'] == $tableId;
            });
            
            if (empty($tableOrders)) {
                $errors['table_id'] = 'La mesa seleccionada no tiene pedidos listos para generar ticket';
            }
        }
        
        // Validate single order selection (backward compatibility)
        if (!empty($data['order_id'])) {
            $order = $this->orderModel->find($data['order_id']);
            if (!$order) {
                $errors['order_id'] = 'El pedido seleccionado no existe';
            } elseif ($order['status'] !== ORDER_READY) {
                $errors['order_id'] = 'El pedido debe estar en estado "Listo" para generar el ticket';
            } else {
                // Check if order already has a ticket
                $existingTicket = $this->ticketModel->findBy('order_id', $data['order_id']);
                if ($existingTicket) {
                    $errors['order_id'] = 'Este pedido ya tiene un ticket generado';
                }
            }
        }
        
        // Validate customer separation and payment methods
        if (isset($data['separation_mode']) && $data['separation_mode'] === 'by_customer' && 
            isset($data['separate_customers']) && is_array($data['separate_customers'])) {
            
            $customerPaymentMethods = $data['customer_payment_methods'] ?? [];
            
            // Check that each selected customer has a payment method
            foreach ($data['separate_customers'] as $customerName) {
                $customerName = trim($customerName);
                if (empty($customerPaymentMethods[$customerName])) {
                    $errors['customer_payment_methods'] = "Debe seleccionar un método de pago para el cliente: {$customerName}";
                    break;
                } elseif (!in_array($customerPaymentMethods[$customerName], $validMethods)) {
                    $errors['customer_payment_methods'] = "Método de pago inválido para el cliente {$customerName}: {$customerPaymentMethods[$customerName]}";
                    break;
                }
            }
        }
        
        return $errors;
    }
    
    private function getOrdersReadyForTicket() {
        return $this->orderModel->getOrdersReadyForTicket();
    }
    
    private function getSalesReportData($startDate, $endDate) {
        return $this->ticketModel->getSalesReportData($startDate, $endDate);
    }
    
    public function pendingPayments() {
        $this->requireRole([ROLE_ADMIN, ROLE_CASHIER]);
        
        // Get search filters
        $searchFilters = [];
        if (isset($_GET['search']) && !empty($_GET['search'])) {
            $searchFilters['search'] = $_GET['search'];
        }
        
        // Get all tickets with payment method 'pendiente_por_cobrar'
        $pendingTickets = $this->ticketModel->getPendingPayments($searchFilters);
        
        $this->view('tickets/pending_payments', [
            'tickets' => $pendingTickets,
            'user' => $this->getCurrentUser()
        ]);
    }
    
    public function markAsPaid($ticketId) {
        $this->requireRole([ROLE_ADMIN, ROLE_CASHIER]);
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $paymentMethod = $_POST['payment_method'] ?? 'efectivo';
            
            if ($this->ticketModel->updatePaymentMethod($ticketId, $paymentMethod)) {
                $this->redirect('tickets/pendingPayments', 'success', 'Pago marcado como cobrado');
            } else {
                $this->redirect('tickets/pendingPayments', 'error', 'Error al actualizar el pago');
            }
        }
    }
    
    public function updatePaymentMethod($ticketId) {
        $this->requireRole([ROLE_ADMIN, ROLE_CASHIER]);
        
        $ticket = $this->ticketModel->find($ticketId);
        if (!$ticket) {
            $this->redirect('tickets', 'error', 'Ticket no encontrado');
            return;
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->processPaymentMethodUpdate($ticketId);
        } else {
            // Show update form
            $this->view('tickets/update_payment', [
                'ticket' => $ticket,
                'user' => $this->getCurrentUser()
            ]);
        }
    }
    
    private function processPaymentMethodUpdate($ticketId) {
        $errors = $this->validateInput($_POST, [
            'payment_method' => ['required' => true]
        ]);
        
        // Validate payment method
        $validMethods = ['efectivo', 'tarjeta', 'transferencia', 'intercambio', 'pendiente_por_cobrar'];
        if (!in_array($_POST['payment_method'] ?? '', $validMethods)) {
            $errors['payment_method'] = 'Método de pago inválido';
        }
        
        if (!empty($errors)) {
            $ticket = $this->ticketModel->find($ticketId);
            $this->view('tickets/update_payment', [
                'ticket' => $ticket,
                'errors' => $errors,
                'old' => $_POST,
                'user' => $this->getCurrentUser()
            ]);
            return;
        }
        
        try {
            $paymentMethod = $_POST['payment_method'];
            $user = $this->getCurrentUser();
            
            // Handle evidence file upload
            $evidenceFile = null;
            if (isset($_FILES['evidence_file']) && $_FILES['evidence_file']['error'] === UPLOAD_ERR_OK) {
                $evidenceFile = $this->handleEvidenceUpload($_FILES['evidence_file']);
            }
            
            $updateData = [
                'payment_method' => $paymentMethod
            ];
            
            if ($evidenceFile) {
                $updateData['evidence_file'] = $evidenceFile;
                $updateData['evidence_uploaded_at'] = date('Y-m-d H:i:s');
                $updateData['evidence_uploaded_by'] = $user['id'];
            }
            
            if ($this->ticketModel->update($ticketId, $updateData)) {
                $this->redirect('tickets', 'success', 'Método de pago actualizado correctamente');
            } else {
                throw new Exception('Error al actualizar en la base de datos');
            }
            
        } catch (Exception $e) {
            $ticket = $this->ticketModel->find($ticketId);
            $this->view('tickets/update_payment', [
                'ticket' => $ticket,
                'error' => 'Error al actualizar el método de pago: ' . $e->getMessage(),
                'old' => $_POST,
                'user' => $this->getCurrentUser()
            ]);
        }
    }
    
    private function handleEvidenceUpload($file) {
        // Create evidence directory if it doesn't exist
        $evidenceDir = UPLOAD_EVIDENCE_PATH;
        if (!is_dir($evidenceDir)) {
            mkdir($evidenceDir, 0755, true);
        }
        
        // Validate file type
        $allowedExtensions = ALLOWED_EVIDENCE_EXTENSIONS;
        $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (!in_array($fileExtension, $allowedExtensions)) {
            throw new Exception('Tipo de archivo no permitido. Extensiones permitidas: ' . implode(', ', $allowedExtensions));
        }
        
        // Validate file size (max 5MB)
        $maxSize = 5 * 1024 * 1024; // 5MB
        if ($file['size'] > $maxSize) {
            throw new Exception('El archivo es demasiado grande. Tamaño máximo: 5MB');
        }
        
        // Generate unique filename
        $fileName = 'evidence_' . date('Y-m-d_H-i-s') . '_' . uniqid() . '.' . $fileExtension;
        $targetPath = $evidenceDir . $fileName;
        
        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            throw new Exception('Error al subir el archivo de evidencia');
        }
        
        return $fileName;
    }
    
    public function createExpiredTicket() {
        $this->requireRole([ROLE_ADMIN, ROLE_CASHIER]);
        
        $user = $this->getCurrentUser();
        
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            // Get expired orders that are ready for ticket generation
            $expiredOrders = $this->orderModel->getExpiredOrdersReadyForTicket();
            
            $this->view('tickets/create_expired', [
                'orders' => $expiredOrders,
                'user' => $user
            ]);
        } else {
            // Handle form submission
            $errors = $this->validateExpiredTicketInput($_POST);
            
            if (!empty($errors)) {
                $expiredOrders = $this->orderModel->getExpiredOrdersReadyForTicket();
                $this->view('tickets/create_expired', [
                    'errors' => $errors,
                    'old' => $_POST,
                    'orders' => $expiredOrders,
                    'user' => $user
                ]);
                return;
            }
            
            try {
                $orderId = $_POST['order_id'];
                $paymentMethod = $_POST['payment_method'] ?? 'efectivo';
                
                $ticketId = $this->ticketModel->createExpiredOrderTicket($orderId, $user['id'], $paymentMethod);
                
                $this->redirect('tickets/show/' . $ticketId, 'success', 'Ticket de pedido vencido generado correctamente');
            } catch (Exception $e) {
                $expiredOrders = $this->orderModel->getExpiredOrdersReadyForTicket();
                $this->view('tickets/create_expired', [
                    'error' => 'Error al generar el ticket: ' . $e->getMessage(),
                    'old' => $_POST,
                    'orders' => $expiredOrders,
                    'user' => $user
                ]);
            }
        }
    }
    
    private function validateExpiredTicketInput($data) {
        $errors = $this->validateInput($data, [
            'order_id' => ['required' => true],
            'payment_method' => ['required' => true]
        ]);
        
        // Get available payment methods based on system settings
        $validMethods = ['efectivo', 'tarjeta', 'transferencia', 'intercambio'];
        
        // Add collections method only if enabled
        if ($this->systemSettingsModel->isCollectionsEnabled()) {
            $validMethods[] = 'pendiente_por_cobrar';
        }
        
        // Validate payment method
        if (!in_array($data['payment_method'] ?? '', $validMethods)) {
            $errors['payment_method'] = 'Método de pago inválido o no disponible';
        }
        
        // Special validation for collections
        if ($data['payment_method'] === 'pendiente_por_cobrar' && !$this->systemSettingsModel->isCollectionsEnabled()) {
            $errors['payment_method'] = 'Las cuentas por cobrar están deshabilitadas';
        }
        
        // Validate order exists and is ready
        if (!empty($data['order_id'])) {
            $order = $this->orderModel->find($data['order_id']);
            if (!$order) {
                $errors['order_id'] = 'El pedido seleccionado no existe';
            } elseif ($order['status'] !== ORDER_READY) {
                $errors['order_id'] = 'El pedido debe estar en estado "Listo" para generar el ticket';
            } else {
                // Check if order already has a ticket
                $existingTicket = $this->ticketModel->findBy('order_id', $data['order_id']);
                if ($existingTicket) {
                    $errors['order_id'] = 'Este pedido ya tiene un ticket generado';
                }
            }
        }
        
        return $errors;
    }
    
    /**
     * Create separated tickets by customer name with individual payment methods
     */
    private function createSeparatedTicketsByCustomer($orders, $separateCustomers, $cashierId, $defaultPaymentMethod, $customerPaymentMethods = []) {
        $ticketIds = [];
        $tableId = null;
        
        // Get table ID from the first order (all should be from same table)
        if (!empty($orders)) {
            $tableId = $orders[0]['table_id'];
        }
        
        // Group orders by customer name, ensuring we handle null/empty values properly
        $ordersByCustomer = [];
        foreach ($orders as $order) {
            // Use COALESCE logic to get the best available customer name
            $customerName = !empty($order['customer_name']) ? trim($order['customer_name']) : 'Sin cliente asignado';
            
            if (!isset($ordersByCustomer[$customerName])) {
                $ordersByCustomer[$customerName] = [];
            }
            $ordersByCustomer[$customerName][] = $order;
        }
        
        // Validate that we have orders and separation is requested
        if (empty($separateCustomers) || !is_array($separateCustomers)) {
            error_log("TicketsController::createSeparatedTicketsByCustomer - No separation customers specified or invalid format");
            throw new Exception('No se especificaron clientes para separar o formato inválido');
        }
        
        // Start a single transaction for all separated ticket creation
        $db = Database::getInstance();
        
        try {
            // Clean up any orphaned transactions
            if ($db->getConnection()->inTransaction()) {
                error_log("Warning: Found active transaction before creating separated tickets, cleaning up");
                $db->rollback();
            }
            
            error_log("Starting transaction for separated tickets by customer");
            $db->beginTransaction();
            
            // Create separate tickets for selected customers
            foreach ($separateCustomers as $customerName) {
                $customerName = trim($customerName);
                if (isset($ordersByCustomer[$customerName])) {
                    $customerOrders = $ordersByCustomer[$customerName];
                    $orderIds = array_map(function($order) { return $order['id']; }, $customerOrders);
                    
                    if (!empty($orderIds)) {
                        try {
                            // Use customer-specific payment method if provided, otherwise use default
                            $customerPaymentMethod = $customerPaymentMethods[$customerName] ?? $defaultPaymentMethod;
                            
                            // Validate customer payment method
                            $validMethods = ['efectivo', 'tarjeta', 'transferencia', 'intercambio', 'pendiente_por_cobrar'];
                            if (!in_array($customerPaymentMethod, $validMethods)) {
                                throw new Exception("Método de pago inválido para cliente '{$customerName}': {$customerPaymentMethod}");
                            }
                            
                            $ticketId = $this->ticketModel->createTicketFromMultipleOrdersWithoutTableFree($orderIds, $cashierId, $customerPaymentMethod, false);
                            $ticketIds[] = $ticketId;
                            
                            // Remove these orders from the list
                            unset($ordersByCustomer[$customerName]);
                            
                            error_log("TicketsController::createSeparatedTicketsByCustomer - Created ticket {$ticketId} for customer '{$customerName}' with " . count($orderIds) . " orders using payment method '{$customerPaymentMethod}'");
                        } catch (Exception $e) {
                            error_log("TicketsController::createSeparatedTicketsByCustomer - Failed to create ticket for customer '{$customerName}': " . $e->getMessage());
                            throw new Exception("Error al crear ticket para cliente '{$customerName}': " . $e->getMessage());
                        }
                    }
                } else {
                    error_log("TicketsController::createSeparatedTicketsByCustomer - Customer '{$customerName}' not found in orders");
                }
            }
            
            // Create a single ticket for remaining orders (if any)
            $remainingOrdersList = [];
            foreach ($ordersByCustomer as $customerName => $customerOrders) {
                $remainingOrdersList = array_merge($remainingOrdersList, $customerOrders);
            }
            
            if (!empty($remainingOrdersList)) {
                $orderIds = array_map(function($order) { return $order['id']; }, $remainingOrdersList);
                try {
                    // Use default payment method for remaining orders
                    $ticketId = $this->ticketModel->createTicketFromMultipleOrdersWithoutTableFree($orderIds, $cashierId, $defaultPaymentMethod, false);
                    $ticketIds[] = $ticketId;
                    
                    error_log("TicketsController::createSeparatedTicketsByCustomer - Created ticket {$ticketId} for remaining " . count($orderIds) . " orders using default payment method '{$defaultPaymentMethod}'");
                } catch (Exception $e) {
                    error_log("TicketsController::createSeparatedTicketsByCustomer - Failed to create ticket for remaining orders: " . $e->getMessage());
                    throw new Exception("Error al crear ticket para pedidos restantes: " . $e->getMessage());
                }
            }
            
            // Now free the table since all tickets have been created
            if ($tableId && !empty($ticketIds)) {
                $tableModel = new Table();
                $tableModel->freeTable($tableId);
                error_log("TicketsController::createSeparatedTicketsByCustomer - Table {$tableId} freed after creating " . count($ticketIds) . " tickets");
            }
            
            // Commit the transaction since all tickets were created successfully
            error_log("Committing separated tickets transaction");
            $db->commit();
            error_log("Separated tickets transaction committed successfully");
            
        } catch (Exception $e) {
            error_log("Error in createSeparatedTicketsByCustomer: " . $e->getMessage());
            // Rollback the transaction on any error
            if ($db->getConnection()->inTransaction()) {
                try {
                    error_log("Rolling back separated tickets transaction due to error");
                    $db->rollback();
                    error_log("Separated tickets rollback completed successfully");
                } catch (\Throwable $rollbackError) {
                    // Log rollback error but don't throw it
                    error_log("TicketsController::createSeparatedTicketsByCustomer - Rollback error (ignored): " . $rollbackError->getMessage());
                }
            }
            error_log("TicketsController::createSeparatedTicketsByCustomer - Error during ticket creation: " . $e->getMessage());
            throw $e;
        }
        
        if (empty($ticketIds)) {
            throw new Exception('No se pudo crear ningún ticket. Verifique que hay pedidos válidos para procesar.');
        }
        
        return $ticketIds;
    }
    
    public function cancel($id) {
        $this->requireRole([ROLE_ADMIN]); // Only admins can cancel tickets
        
        $ticket = $this->ticketModel->find($id);
        if (!$ticket) {
            $this->redirect('tickets', 'error', 'Ticket no encontrado');
            return;
        }
        
        // Check if ticket is already cancelled
        if (isset($ticket['status']) && $ticket['status'] === 'cancelled') {
            $this->redirect('tickets', 'error', 'Este ticket ya está cancelado');
            return;
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->processCancellation($id);
        } else {
            // Show cancellation form
            $ticketDetails = $this->ticketModel->getTicketWithDetails($id);
            
            $this->view('tickets/cancel', [
                'ticket' => $ticketDetails,
                'user' => $this->getCurrentUser()
            ]);
        }
    }
    
    private function processCancellation($ticketId) {
        $errors = $this->validateInput($_POST, [
            'cancellation_reason' => ['required' => true, 'min_length' => 10]
        ]);
        
        if (!empty($errors)) {
            $ticketDetails = $this->ticketModel->getTicketWithDetails($ticketId);
            $this->view('tickets/cancel', [
                'ticket' => $ticketDetails,
                'errors' => $errors,
                'old' => $_POST,
                'user' => $this->getCurrentUser()
            ]);
            return;
        }
        
        $user = $this->getCurrentUser();
        $reason = trim($_POST['cancellation_reason']);
        
        try {
            $this->ticketModel->cancelTicket($ticketId, $user['id'], $reason);
            $this->redirect('tickets', 'success', 'Ticket cancelado correctamente. Se ha descontado del ingreso del sistema.');
        } catch (Exception $e) {
            $ticketDetails = $this->ticketModel->getTicketWithDetails($ticketId);
            $this->view('tickets/cancel', [
                'ticket' => $ticketDetails,
                'error' => 'Error al cancelar el ticket: ' . $e->getMessage(),
                'old' => $_POST,
                'user' => $this->getCurrentUser()
            ]);
        }
    }
    /**
     * Dashboard de propinas
     */
    public function dashboardTips() {
        $this->requireRole([ROLE_ADMIN, ROLE_CASHIER]);
        $dateFrom = $_GET['date_from'] ?? date('Y-m-01');
        $dateTo = $_GET['date_to'] ?? date('Y-m-d');
        $tips = $this->ticketModel->getTipsByDate($dateFrom, $dateTo);
        $this->view('tips/dashboard', [
            'tips' => $tips
        ]);
    }
    
    public function addTip() {
        $this->requireRole([ROLE_ADMIN, ROLE_CASHIER]);
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Método no permitido']);
            return;
        }
        $user = $this->getCurrentUser();
        $ticketId = intval($_POST['ticket_id'] ?? 0);
        $tipAmount = (isset($_POST['tip_amount']) && $_POST['tip_amount'] !== '') ? floatval($_POST['tip_amount']) : null;
        $tipPercentage = (isset($_POST['tip_percentage']) && $_POST['tip_percentage'] !== '') ? floatval($_POST['tip_percentage']) : null;
        if ($ticketId > 0) {
            // Propina asociada a ticket (multiticket: actualizar todos los tickets con el mismo ticket_number)
            $ticket = $this->ticketModel->find($ticketId);
            if (!$ticket || !is_array($ticket)) {
                http_response_code(404);
                echo json_encode(['error' => 'Ticket no encontrado']);
                return;
            }
            $ticketNumber = isset($ticket['ticket_number']) ? $ticket['ticket_number'] : null;
            if ($tipAmount === null && $tipPercentage !== null) {
                // Sumar el total de todos los tickets con ese ticket_number
                $tickets = $this->ticketModel->findBy('ticket_number', $ticketNumber, true); // true: traer todos
                $totalGlobal = 0;
                if (is_array($tickets) && count($tickets) > 0) {
                    foreach ($tickets as $t) {
                        if (is_array($t) && isset($t['total'])) {
                            $totalGlobal += floatval($t['total']);
                        }
                    }
                }
                // Si no se encontró ningún ticket, usar el total del ticket actual
                if ($totalGlobal == 0 && is_array($ticket) && isset($ticket['total'])) {
                    $totalGlobal = floatval($ticket['total']);
                }
                // Solo calcular propina si el total es mayor a 0
                if ($totalGlobal > 0) {
                    $tipAmount = round($totalGlobal * $tipPercentage / 100, 2);
                } else {
                    $tipAmount = null;
                }
            }
            $updateData = [
                'tip_amount' => $tipAmount,
                'tip_percentage' => $tipPercentage,
                'tip_date' => date('Y-m-d'),
                'tip_added_by' => $user['id']
            ];
            // Actualizar todos los tickets con el mismo ticket_number
            $result = $this->ticketModel->updateByTicketNumber($ticketNumber, $updateData);
            if ($result) {
                echo json_encode(['success' => true, 'tip_amount' => $tipAmount, 'tip_percentage' => $tipPercentage]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'No se pudo guardar la propina']);
            }
        } else if ($tipAmount !== null && $tipAmount > 0) {
            // Propina manual (sin ticket)
            $result = $this->ticketModel->addManualTip($tipAmount, $user['id']);
            if ($result) {
                echo json_encode(['success' => true, 'tip_amount' => $tipAmount]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'No se pudo guardar la propina manual']);
            }
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Datos de propina inválidos']);
        }
    }
}
?>
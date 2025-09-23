<?php $title = 'Generar Ticket'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="bi bi-receipt-cutoff"></i> Generar Ticket</h1>
    <a href="<?= BASE_URL ?>/tickets" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Volver a Tickets
    </a>
</div>

<?php if (isset($error)): ?>
    <div class="alert alert-danger">
        <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<?php if (isset($errors['selection'])): ?>
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($errors['selection']) ?>
    </div>
<?php endif; ?>

<?php if (empty($tables)): ?>
<div class="card">
    <div class="card-body">
        <div class="text-center py-5">
            <i class="bi bi-exclamation-triangle display-1 text-warning"></i>
            <h3 class="mt-3">No hay pedidos listos</h3>
            <p class="text-muted">
                No hay pedidos en estado "Listo" disponibles para generar tickets.<br>
                Los pedidos deben estar marcados como "Listo" antes de poder generar un ticket.
            </p>
            <div class="mt-4">
                <a href="<?= BASE_URL ?>/orders" class="btn btn-outline-primary">
                    <i class="bi bi-clipboard-check"></i> Ver Pedidos
                </a>
                <a href="<?= BASE_URL ?>/tickets" class="btn btn-outline-secondary ms-2">
                    <i class="bi bi-arrow-left"></i> Volver a Tickets
                </a>
            </div>
        </div>
    </div>
</div>
<?php else: ?>
<form method="POST" action="<?= BASE_URL ?>/tickets/create">
    <div class="row">
        <!-- Table Selection -->
        <div class="col-md-7">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-table"></i> Seleccionar Mesa con Pedidos Listos
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (isset($errors['table_id'])): ?>
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($errors['table_id']) ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i>
                        <strong>Nueva funcionalidad:</strong> Ahora puedes generar un ticket que incluya múltiples pedidos listos de una mesa en una sola factura.
                    </div>
                    
                    <div class="row">
                        <?php foreach ($tables as $table): ?>
                        <div class="col-md-6 mb-3">
                            <div class="card table-card" data-table-id="<?= $table['table_id'] ?>">
                                <div class="card-body">
                                    <div class="form-check">
                                        <input type="radio" 
                                               class="form-check-input" 
                                               name="table_id" 
                                               value="<?= $table['table_id'] ?>"
                                               id="table_<?= $table['table_id'] ?>"
                                               <?= (($old['table_id'] ?? '') == $table['table_id']) ? 'checked' : '' ?>
                                               required>
                                        <label class="form-check-label w-100" for="table_<?= $table['table_id'] ?>">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div>
                                                    <h5 class="card-title">
                                                        <span class="badge bg-primary">Mesa <?= $table['table_number'] ?></span>
                                                    </h5>
                                                    <p class="card-text">
                                                        <strong><?= $table['order_count'] ?></strong> pedido<?= $table['order_count'] > 1 ? 's' : '' ?> listo<?= $table['order_count'] > 1 ? 's' : '' ?><br>
                                                        <small class="text-muted">Total: <strong>$<?= number_format($table['total_amount'], 2) ?></strong></small>
                                                    </p>
                                                </div>
                                                <div class="text-end">
                                                    <i class="bi bi-receipt-cutoff text-primary fs-4"></i>
                                                </div>
                                            </div>
                                        </label>
                                    </div>
                                    
                                    <!-- Order Details -->
                                    <div class="mt-3 border-top pt-3">
                                        <h6 class="text-muted mb-2">Pedidos incluidos:</h6>
                                        <?php foreach ($table['orders'] as $order): ?>
                                        <div class="d-flex justify-content-between align-items-center mb-2 p-2 border rounded order-item">
                                            <div class="flex-grow-1">
                                                <span class="small fw-bold">
                                                    Pedido #<?= $order['id'] ?> - <?= htmlspecialchars($order['waiter_name']) ?>
                                                </span>
                                                <?php if (!empty($order['customer_name'])): ?>
                                                <br><span class="small text-muted">Cliente: <?= htmlspecialchars($order['customer_name']) ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="text-end">
                                                <div class="small fw-bold mb-1">$<?= number_format($order['total'], 2) ?></div>
                                                <?php if (count($table['orders']) > 1): ?>
                                                <button type="button" 
                                                        class="btn btn-outline-success btn-sm individual-ticket-btn" 
                                                        data-order-id="<?= $order['id'] ?>"
                                                        data-order-total="<?= $order['total'] ?>"
                                                        data-table-number="<?= $table['table_number'] ?>"
                                                        title="Generar ticket individual para este pedido">
                                                    <i class="bi bi-receipt"></i>
                                                </button>
                                                <?php else: ?>
                                                <span class="badge bg-info text-dark small">Ticket único</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                        
                                        <div class="mt-2 p-2 bg-light rounded">
                                            <small class="text-muted">
                                                <i class="bi bi-info-circle"></i>
                                                <strong>Opciones:</strong><br>
                                                <?php if (count($table['orders']) > 1): ?>
                                                • Usa los botones <i class="bi bi-receipt"></i> para generar tickets individuales por pedido<br>
                                                • O selecciona la mesa para generar un ticket combinado
                                                <?php else: ?>
                                                • Mesa con un solo pedido - se generará un ticket único automáticamente
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                    </div>
                                    
                                    <div class="mt-2">
                                        <a href="javascript:void(0)" 
                                           class="btn btn-outline-primary btn-sm view-details" 
                                           data-table-id="<?= $table['table_id'] ?>"
                                           title="Ver detalles de los pedidos">
                                            <i class="bi bi-eye"></i> Ver detalles
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Ticket Details -->
        <div class="col-md-5">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-receipt"></i> Detalles del Ticket
                    </h5>
                </div>
                <div class="card-body">
                    <!-- Payment Method Selection -->
                    <div class="mb-3">
                        <label for="payment_method" class="form-label">Método de Pago *</label>
                        <select class="form-select <?= isset($errors['payment_method']) ? 'is-invalid' : '' ?>" 
                                id="payment_method" 
                                name="payment_method" 
                                required>
                            <option value="">Seleccionar método...</option>
                            <option value="efectivo" <?= (($old['payment_method'] ?? '') === 'efectivo') ? 'selected' : '' ?>>
                                <i class="bi bi-cash"></i> Efectivo
                            </option>
                            <option value="tarjeta" <?= (($old['payment_method'] ?? '') === 'tarjeta') ? 'selected' : '' ?>>
                                <i class="bi bi-credit-card"></i> Tarjeta
                            </option>
                            <option value="transferencia" <?= (($old['payment_method'] ?? '') === 'transferencia') ? 'selected' : '' ?>>
                                <i class="bi bi-bank"></i> Transferencia
                            </option>
                            <option value="intercambio" <?= (($old['payment_method'] ?? '') === 'intercambio') ? 'selected' : '' ?>>
                                <i class="bi bi-arrow-left-right"></i> Intercambio
                            </option>
                            <option value="pendiente_por_cobrar" <?= (($old['payment_method'] ?? '') === 'pendiente_por_cobrar') ? 'selected' : '' ?>>
                                <i class="bi bi-clock-history"></i> Pendiente por Cobrar
                            </option>
                        </select>
                        <?php if (isset($errors['payment_method'])): ?>
                            <div class="invalid-feedback">
                                <?= htmlspecialchars($errors['payment_method']) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- New Grouping Mode Option -->
                    <div class="mb-3">
                        <label for="group_by" class="form-label">Agrupar Pedidos Por</label>
                        <select class="form-select" id="group_by" name="group_by">
                            <option value="customer" <?= (($old['group_by'] ?? 'customer') === 'customer') ? 'selected' : '' ?>>
                                <i class="bi bi-person"></i> Cliente (Recomendado)
                            </option>
                            <option value="table" <?= (($old['group_by'] ?? '') === 'table') ? 'selected' : '' ?>>
                                <i class="bi bi-table"></i> Mesa (Modo tradicional)
                            </option>
                        </select>
                        <div class="form-text">
                            <strong>Cliente:</strong> Agrupa pedidos por cliente, permitiendo un mejor control de cuentas individuales.<br>
                            <strong>Mesa:</strong> Agrupa todos los pedidos de la mesa independientemente del cliente.
                        </div>
                    </div>
                    
                    <div class="mb-3" id="separationModeContainer" style="display: none;">
                        <label for="separation_mode" class="form-label">Tipo de Ticket</label>
                        <select class="form-select" id="separation_mode" name="separation_mode">
                            <option value="single">Ticket único (todos los pedidos juntos)</option>
                            <option value="by_customer">Tickets separados por cliente</option>
                        </select>
                        <div class="form-text">
                            Seleccione cómo generar los tickets para esta mesa
                        </div>
                    </div>
                    
                    <div id="customerSeparationOptions" style="display: none;">
                        <!-- This will be populated dynamically with customer options -->
                    </div>
                    
                    <?php if (isset($errors['customer_payment_methods'])): ?>
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($errors['customer_payment_methods']) ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="card bg-light mb-3" id="ticketPreview" style="display: none;">
                        <div class="card-body">
                            <h6 class="card-title">Preview del Ticket</h6>
                            <div id="previewContent">
                                <!-- Will be populated by JavaScript -->
                            </div>
                        </div>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i>
                        <strong>Información:</strong><br>
                        Los precios ya incluyen 16% de IVA. En el ticket se mostrará desglosado el subtotal sin IVA y el impuesto por separado.
                    </div>
                    
                    <!-- Action Buttons moved here -->
                    <div class="d-flex justify-content-end gap-2 mt-4">
                        <a href="<?= BASE_URL ?>/tickets" class="btn btn-secondary">
                            <i class="bi bi-x-circle"></i> Cancelar
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-circle"></i> Generar Ticket
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<!-- Modal for Order Details -->
<div class="modal fade" id="orderDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detalles de los Pedidos</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="orderDetailsContent">
                <!-- Will be populated by JavaScript -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal for Individual Ticket Creation -->
<div class="modal fade" id="individualTicketModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="individualTicketForm" method="POST" action="<?= BASE_URL ?>/tickets/create">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-receipt"></i> Generar Ticket Individual
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i>
                        Se generará un ticket exclusivo para este pedido.
                    </div>
                    
                    <div id="orderSummary" class="card mb-3">
                        <!-- Will be populated by JavaScript -->
                    </div>
                    
                    <div class="mb-3">
                        <label for="individual_payment_method" class="form-label">Método de Pago *</label>
                        <select class="form-select" id="individual_payment_method" name="payment_method" required>
                            <option value="">Seleccionar método...</option>
                            <option value="efectivo">
                                <i class="bi bi-cash"></i> Efectivo
                            </option>
                            <option value="tarjeta">
                                <i class="bi bi-credit-card"></i> Tarjeta
                            </option>
                            <option value="transferencia">
                                <i class="bi bi-bank"></i> Transferencia
                            </option>
                            <option value="intercambio">
                                <i class="bi bi-arrow-left-right"></i> Intercambio
                            </option>
                            <option value="pendiente_por_cobrar">
                                <i class="bi bi-clock-history"></i> Pendiente por Cobrar
                            </option>
                        </select>
                    </div>
                    
                    <input type="hidden" id="individual_order_id" name="order_id" />
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle"></i> Cancelar
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle"></i> Generar Ticket
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const tableCards = document.querySelectorAll('.table-card');
    const ticketPreview = document.getElementById('ticketPreview');
    const previewContent = document.getElementById('previewContent');
    
    // Handle table selection
    document.addEventListener('change', function(e) {
        if (e.target.name === 'table_id') {
            const tableId = e.target.value;
            const tableCard = document.querySelector(`[data-table-id="${tableId}"]`);
            
            if (tableCard) {
                // Get table data
                const tableData = <?= json_encode($tables) ?>;
                const selectedTable = tableData.find(t => t.table_id == tableId);
                
                if (selectedTable) {
                    // Show separation mode options
                    document.getElementById('separationModeContainer').style.display = 'block';
                    
                    const totalWithTax = selectedTable.total_amount;
                    const subtotal = totalWithTax / 1.16;
                    const tax = totalWithTax - subtotal;
                    const total = totalWithTax;
                    
                    let ordersList = '';
                    selectedTable.orders.forEach(order => {
                        ordersList += `<div class="row mb-1">
                            <div class="col-8">Pedido #${order.id}</div>
                            <div class="col-4 text-end">$${parseFloat(order.total).toFixed(2)}</div>
                        </div>`;
                    });
                    
                    const previewHtml = `
                        <div class="row mb-2">
                            <div class="col-6"><strong>Mesa:</strong></div>
                            <div class="col-6 text-end">${selectedTable.table_number}</div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-6"><strong>Pedidos:</strong></div>
                            <div class="col-6 text-end">${selectedTable.order_count}</div>
                        </div>
                        <hr>
                        ${ordersList}
                        <hr>
                        <div class="row mb-2">
                            <div class="col-6">Subtotal:</div>
                            <div class="col-6 text-end">$${subtotal.toFixed(2)}</div>
                        </div>
                        <div class="row mb-2">
                            <div class="col-6">IVA (16%):</div>
                            <div class="col-6 text-end">$${tax.toFixed(2)}</div>
                        </div>
                        <hr>
                        <div class="row">
                            <div class="col-6"><strong>Total:</strong></div>
                            <div class="col-6 text-end"><strong>$${total.toFixed(2)}</strong></div>
                        </div>
                    `;
                    
                    previewContent.innerHTML = previewHtml;
                    ticketPreview.style.display = 'block';
                }
            }
            
            // Highlight selected table
            tableCards.forEach(card => card.classList.remove('selected'));
            tableCard.classList.add('selected');
        }
    });
    
    // Handle view details buttons
    document.addEventListener('click', function(e) {
        if (e.target.closest('.view-details')) {
            const tableId = e.target.closest('.view-details').getAttribute('data-table-id');
            const tableData = <?= json_encode($tables) ?>;
            const selectedTable = tableData.find(t => t.table_id == tableId);
            
            if (selectedTable) {
                let detailsHtml = `<h6>Mesa ${selectedTable.table_number}</h6>`;
                
                selectedTable.orders.forEach(order => {
                    detailsHtml += `
                        <div class="card mb-3">
                            <div class="card-body">
                                <h6 class="card-title">Pedido #${order.id}</h6>
                                <p class="card-text">
                                    <strong>Mesero:</strong> ${order.waiter_name} (${order.employee_code})<br>
                                    <strong>Total:</strong> $${parseFloat(order.total).toFixed(2)}<br>
                                    <strong>Fecha:</strong> ${new Date(order.created_at).toLocaleString()}
                                </p>
                                <a href="<?= BASE_URL ?>/orders/show/${order.id}" target="_blank" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-eye"></i> Ver detalles completos
                                </a>
                            </div>
                        </div>
                    `;
                });
                
                document.getElementById('orderDetailsContent').innerHTML = detailsHtml;
                new bootstrap.Modal(document.getElementById('orderDetailsModal')).show();
            }
        }
        
        // Handle individual ticket creation buttons
        if (e.target.closest('.individual-ticket-btn')) {
            const button = e.target.closest('.individual-ticket-btn');
            const orderId = button.getAttribute('data-order-id');
            const orderTotal = button.getAttribute('data-order-total');
            const tableNumber = button.getAttribute('data-table-number');
            
            // Populate the modal with order information
            const orderSummary = document.getElementById('orderSummary');
            const totalWithTax = parseFloat(orderTotal);
            const subtotal = totalWithTax / 1.16;
            const tax = totalWithTax - subtotal;
            
            orderSummary.innerHTML = `
                <div class="card-header">
                    <h6 class="mb-0">Resumen del Pedido #${orderId}</h6>
                </div>
                <div class="card-body">
                    <div class="row mb-2">
                        <div class="col-6"><strong>Mesa:</strong></div>
                        <div class="col-6 text-end">${tableNumber}</div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-6"><strong>Pedido:</strong></div>
                        <div class="col-6 text-end">#${orderId}</div>
                    </div>
                    <hr>
                    <div class="row mb-2">
                        <div class="col-6">Subtotal:</div>
                        <div class="col-6 text-end">$${subtotal.toFixed(2)}</div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-6">IVA (16%):</div>
                        <div class="col-6 text-end">$${tax.toFixed(2)}</div>
                    </div>
                    <hr>
                    <div class="row">
                        <div class="col-6"><strong>Total:</strong></div>
                        <div class="col-6 text-end"><strong>$${totalWithTax.toFixed(2)}</strong></div>
                    </div>
                </div>
            `;
            
            // Set the order ID in the hidden input
            document.getElementById('individual_order_id').value = orderId;
            
            // Show the modal
            new bootstrap.Modal(document.getElementById('individualTicketModal')).show();
        }
    });
    
    // Initial setup if there's a pre-selected table
    const selectedTable = document.querySelector('input[name="table_id"]:checked');
    if (selectedTable) {
        selectedTable.dispatchEvent(new Event('change'));
    }
    
    // Handle separation mode changes
    document.getElementById('separation_mode').addEventListener('change', function() {
        const selectedTableId = document.querySelector('input[name="table_id"]:checked')?.value;
        if (!selectedTableId) return;
        
        const tableData = <?= json_encode($tables) ?>;
        const selectedTable = tableData.find(t => t.table_id == selectedTableId);
        
        if (this.value === 'by_customer' && selectedTable) {
            showCustomerSeparationOptions(selectedTable);
        } else {
            document.getElementById('customerSeparationOptions').style.display = 'none';
        }
    });
    
    function showCustomerSeparationOptions(tableData) {
        // Get unique customers from orders
        const customers = {};
        tableData.orders.forEach(order => {
            const customerKey = order.customer_name || 'Sin cliente asignado';
            if (!customers[customerKey]) {
                customers[customerKey] = {
                    name: customerKey,
                    orders: [],
                    total: 0
                };
            }
            customers[customerKey].orders.push(order);
            customers[customerKey].total += parseFloat(order.total);
        });
        
        let optionsHtml = `
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">Configurar tickets separados por cliente</h6>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i>
                        <strong>Instrucciones:</strong><br>
                        • Marque los clientes que desea incluir en tickets separados<br>
                        • Seleccione el método de pago para cada cliente<br>
                        • Los clientes no marcados se incluirán en un ticket conjunto con el método de pago principal
                    </div>
        `;
        
        const paymentMethodOptions = [
            { value: 'efectivo', text: 'Efectivo', icon: 'cash' },
            { value: 'tarjeta', text: 'Tarjeta', icon: 'credit-card' },
            { value: 'transferencia', text: 'Transferencia', icon: 'bank' },
            { value: 'intercambio', text: 'Intercambio', icon: 'arrow-left-right' },
            { value: 'pendiente_por_cobrar', text: 'Pendiente por Cobrar', icon: 'clock-history' }
        ];
        
        Object.keys(customers).forEach((customerKey, index) => {
            const customer = customers[customerKey];
            const customerId = customerKey.replace(/\s+/g, '_').replace(/[^a-zA-Z0-9_]/g, '');
            
            optionsHtml += `
                <div class="border rounded p-3 mb-3 customer-separation-item">
                    <div class="form-check mb-3">
                        <input class="form-check-input customer-checkbox" type="checkbox" 
                               value="${customerKey}" 
                               name="separate_customers[]" 
                               id="customer_${customerId}"
                               data-customer-id="${customerId}">
                        <label class="form-check-label fw-bold" for="customer_${customerId}">
                            ${customer.name}
                        </label>
                    </div>
                    
                    <div class="customer-details">
                        <div class="row mb-2">
                            <div class="col-6">
                                <small class="text-muted">Pedidos:</small> ${customer.orders.length}
                            </div>
                            <div class="col-6">
                                <small class="text-muted">Total:</small> <strong>$${customer.total.toFixed(2)}</strong>
                            </div>
                        </div>
                        
                        <div class="customer-payment-method" id="payment_method_container_${customerId}" style="display: none;">
                            <label for="customer_payment_method_${customerId}" class="form-label">
                                <i class="bi bi-credit-card"></i> Método de Pago para ${customer.name}
                            </label>
                            <select class="form-select form-select-sm" 
                                    name="customer_payment_methods[${customerKey}]" 
                                    id="customer_payment_method_${customerId}">
                                <option value="">Seleccionar método...</option>`;
            
            paymentMethodOptions.forEach(method => {
                optionsHtml += `<option value="${method.value}">
                    ${method.text}
                </option>`;
            });
            
            optionsHtml += `
                            </select>
                        </div>
                        
                        <div class="mt-2">
                            <small class="text-muted">Pedidos incluidos:</small>
                            <ul class="list-unstyled mt-1">`;
            
            customer.orders.forEach(order => {
                optionsHtml += `
                    <li class="small">• Pedido #${order.id} - $${parseFloat(order.total).toFixed(2)}</li>
                `;
            });
            
            optionsHtml += `
                            </ul>
                        </div>
                    </div>
                </div>
            `;
        });
        
        optionsHtml += `
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle"></i>
                        <strong>Importante:</strong> Se generará un ticket separado para cada cliente marcado. 
                        Los clientes no marcados se incluirán en un ticket conjunto.
                    </div>
                </div>
            </div>
        `;
        
        document.getElementById('customerSeparationOptions').innerHTML = optionsHtml;
        document.getElementById('customerSeparationOptions').style.display = 'block';
        
        // Add event listeners for customer checkboxes
        document.querySelectorAll('.customer-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const customerId = this.getAttribute('data-customer-id');
                const paymentContainer = document.getElementById(`payment_method_container_${customerId}`);
                const paymentSelect = document.getElementById(`customer_payment_method_${customerId}`);
                
                if (this.checked) {
                    paymentContainer.style.display = 'block';
                    paymentSelect.required = true;
                } else {
                    paymentContainer.style.display = 'none';
                    paymentSelect.required = false;
                    paymentSelect.value = '';
                }
            });
        });
    }
});
</script>

<style>
.table-card {
    cursor: pointer;
    transition: all 0.3s ease;
    border: 2px solid transparent;
}

.table-card:hover {
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    border-color: #dee2e6;
}

.table-card.selected {
    border-color: #0d6efd !important;
    box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
}

.form-check-input:checked ~ .form-check-label .card-title {
    color: #0d6efd;
}

.customer-separation-item {
    background-color: #f8f9fa;
}

.customer-separation-item .form-check-input:checked ~ .form-check-label {
    color: #0d6efd;
    font-weight: bold;
}

.customer-payment-method {
    margin-left: 1.5rem;
    padding: 0.5rem;
    background-color: #fff;
    border-radius: 0.375rem;
    border: 1px solid #dee2e6;
}

.order-item {
    background-color: #f8f9fa;
    transition: all 0.2s ease;
}

.order-item:hover {
    background-color: #e9ecef;
}

.individual-ticket-btn {
    transition: all 0.2s ease;
}

.individual-ticket-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}
</style>
<?php $title = 'Gestión de Tickets'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="bi bi-receipt"></i> Gestión de Tickets</h1>
    <div>
        <a href="<?= BASE_URL ?>/tickets/pendingPayments" class="btn btn-outline-warning me-2">
            <i class="bi bi-clock-history"></i> Cuentas Pendientes
        </a>
        <a href="<?= BASE_URL ?>/tickets/createExpiredTicket" class="btn btn-outline-danger me-2">
            <i class="bi bi-exclamation-triangle"></i> Tickets Vencidos
        </a>
        <a href="<?= BASE_URL ?>/tickets/create" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Generar Ticket
        </a>
        <?php if (in_array($user['role'], [ROLE_ADMIN, ROLE_CASHIER])): ?>
        <a href="<?= BASE_URL ?>/tickets/report" class="btn btn-outline-info">
            <i class="bi bi-graph-up"></i> Reportes
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- Date Filter and Search -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="<?= BASE_URL ?>/tickets" class="row g-3 align-items-end">
            <div class="col-md-2">
                <label for="date" class="form-label">Fecha:</label>
                <input type="date" 
                       class="form-control" 
                       id="date" 
                       name="date" 
                       value="<?= htmlspecialchars($selectedDate) ?>">
            </div>
            <div class="col-md-3">
                <label for="search" class="form-label">Buscar:</label>
                <input type="text" 
                       class="form-control" 
                       id="search" 
                       name="search" 
                       placeholder="Cliente, teléfono, email, mesa..."
                       value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-outline-primary">
                    <i class="bi bi-search"></i> Buscar
                </button>
                <?php if (!empty($_GET['search'])): ?>
                    <a href="<?= BASE_URL ?>/tickets?date=<?= $selectedDate ?>" class="btn btn-outline-secondary ms-1">
                        <i class="bi bi-x"></i>
                    </a>
                <?php endif; ?>
            </div>
            <div class="col-md-5 text-end">
                <div class="d-flex justify-content-end gap-3">
                    <div class="text-center">
                        <small class="text-muted">Total Tickets</small>
                        <div class="h5 mb-0"><?= $salesReport['totals']['total_tickets'] ?? 0 ?></div>
                    </div>
                    <div class="text-center">
                        <small class="text-muted">Total Ventas</small>
                        <div class="h5 mb-0 text-success">$<?= number_format($salesReport['totals']['total_amount'] ?? 0, 2) ?></div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<?php if (empty($tickets)): ?>
<div class="card">
    <div class="card-body">
        <div class="text-center py-5">
            <i class="bi bi-receipt display-1 text-muted"></i>
            <h3 class="mt-3">No hay tickets registrados</h3>
            <p class="text-muted">
                <?php if ($user['role'] === ROLE_CASHIER): ?>
                    No has generado tickets para la fecha seleccionada.
                <?php else: ?>
                    No se han generado tickets para la fecha seleccionada.
                <?php endif; ?>
            </p>
            <div class="mt-4">
                <a href="<?= BASE_URL ?>/tickets/create" class="btn btn-primary">
                    <i class="bi bi-plus-circle"></i> Generar Primer Ticket
                </a>
                <a href="<?= BASE_URL ?>/dashboard" class="btn btn-outline-secondary ms-2">
                    <i class="bi bi-arrow-left"></i> Volver al Dashboard
                </a>
            </div>
        </div>
    </div>
</div>
<?php else: ?>
<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Ticket #</th>
                        <th>Mesa</th>
                        <th>Cliente</th>
                        <th>Cajero</th>
                        <th>Subtotal</th>
                        <th>Impuesto</th>
                        <th>Total</th>
                        <th>Método Pago</th>
                        <th>Fecha</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tickets as $ticket): ?>
                    <tr <?= (isset($ticket['status']) && $ticket['status'] === 'cancelled') ? 'class="table-danger"' : '' ?>>
                        <td>
                            <strong><?= htmlspecialchars($ticket['ticket_number']) ?></strong>
                            <?php if (isset($ticket['status']) && $ticket['status'] === 'cancelled'): ?>
                                <br><span class="badge bg-danger">CANCELADO</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge bg-info">Mesa <?= $ticket['table_number'] ?></span>
                        </td>
                        <td>
                            <i class="bi bi-person-fill text-info"></i> 
                            <small class="text-muted">Cliente:</small><br>
                            <?= htmlspecialchars($ticket['customer_name'] ?: $ticket['order_customer_name'] ?: 'Público') ?>
                            <?php if (!empty($ticket['customer_phone'])): ?>
                                <br><small class="text-muted"><?= htmlspecialchars($ticket['customer_phone']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= htmlspecialchars($ticket['cashier_name']) ?>
                        </td>
                        <td>
                            $<?= number_format($ticket['subtotal'], 2) ?>
                        </td>
                        <td>
                            $<?= number_format($ticket['tax'], 2) ?>
                        </td>
                        <td>
                            <strong>$<?= number_format($ticket['total'], 2) ?></strong>
                        </td>
                        <td>
                            <span class="badge payment-<?= $ticket['payment_method'] ?>">
                                <?= getPaymentMethodText($ticket['payment_method']) ?>
                            </span>
                        </td>
                        <td>
                            <small><?= date('d/m/Y H:i', strtotime($ticket['created_at'])) ?></small>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm" role="group">
                                <a href="<?= BASE_URL ?>/tickets/show/<?= $ticket['id'] ?>" 
                                   class="btn btn-outline-primary btn-sm" 
                                   title="Ver detalles">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <a href="<?= BASE_URL ?>/tickets/print/<?= $ticket['id'] ?>" 
                                   class="btn btn-outline-success btn-sm" 
                                   title="Imprimir" 
                                   target="_blank">
                                    <i class="bi bi-printer"></i>
                                </a>
                                <!-- Botón de propina -->
                                <button type="button" class="btn btn-outline-info btn-sm" title="Agregar propina" data-bs-toggle="modal" data-bs-target="#tipModal" data-ticket-id="<?= $ticket['id'] ?>" data-total="<?= $ticket['total'] ?>" data-payment-method="<?= $ticket['payment_method'] ?>">
                                    <i class="bi bi-cash-coin"></i>
                                </button>
                                <?php if (in_array($user['role'], [ROLE_ADMIN, ROLE_CASHIER]) && (!isset($ticket['status']) || $ticket['status'] !== 'cancelled')): ?>
                                <a href="<?= BASE_URL ?>/tickets/updatePaymentMethod/<?= $ticket['id'] ?>" 
                                   class="btn btn-outline-warning btn-sm" 
                                   title="Cambiar método de pago">
                                    <i class="bi bi-credit-card"></i>
                                </a>
                                <?php endif; ?>
                                <?php if ($user['role'] === ROLE_ADMIN && (!isset($ticket['status']) || $ticket['status'] !== 'cancelled')): ?>
                                <a href="<?= BASE_URL ?>/tickets/cancel/<?= $ticket['id'] ?>" 
                                   class="btn btn-outline-danger btn-sm" 
                                   title="Cancelar ticket">
                                    <i class="bi bi-x-circle"></i>
                                </a>
                                <?php endif; ?>

                                                                <!-- Botón para agregar propina -->
                                                                <button type="button" class="btn btn-outline-info btn-sm" data-bs-toggle="modal" data-bs-target="#tipModal" data-ticket-id="<?= $ticket['id'] ?>" data-total="<?= $ticket['total'] ?>" data-payment-method="<?= $ticket['payment_method'] ?>">
                                                                        <i class="bi bi-cash-coin"></i> Agregar Propina
                                                                </button>
                                                        </div>
                                                </td>
                                        </tr>
                                        <?php endforeach; ?>
                                </tbody>
                        </table>
                </div>
        </div>
</div>

<!-- Modal para agregar propina -->
<div class="modal fade" id="tipModal" tabindex="-1" aria-labelledby="tipModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="tipModalLabel">Agregar Propina</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <form id="tipForm">
                    <input type="hidden" name="ticket_id" id="tip_ticket_id">
                    <input type="hidden" name="payment_method" id="tip_payment_method">
                    <div class="mb-3" id="tip_percentage_group">
                        <label for="tip_percentage" class="form-label">Selecciona porcentaje de propina:</label>
                        <div class="btn-group w-100 mb-2" role="group">
                            <button type="button" class="btn btn-outline-primary tip-btn" data-percentage="5">5%</button>
                            <button type="button" class="btn btn-outline-primary tip-btn" data-percentage="10">10%</button>
                            <button type="button" class="btn btn-outline-primary tip-btn" data-percentage="15">15%</button>
                            <button type="button" class="btn btn-outline-primary tip-btn" data-percentage="20">20%</button>
                        </div>
                        <input type="number" class="form-control mb-2" id="tip_percentage" name="tip_percentage" min="0" max="100" step="0.01" placeholder="Porcentaje personalizado">
                        <label for="tip_amount" class="form-label mt-2">Monto de propina en efectivo:</label>
                        <input type="number" class="form-control" id="tip_amount" name="tip_amount" min="0" step="0.01" placeholder="Cantidad en efectivo dejada">
                        <div class="form-text">Si el cliente dejó una cantidad específica en efectivo, ingrésala aquí. Si usas porcentaje, deja este campo vacío.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Propina registrada:</label>
                        <div id="tip_calculated" class="fw-bold text-success">$0.00</div>
                    </div>
                </form>
                <div class="alert alert-info mt-2" id="manualTipInfo" style="display:none;">
                    <i class="bi bi-info-circle"></i> Si solo deseas registrar la cantidad de propina dejada, ingresa el monto y deja el campo de porcentaje vacío.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="saveTipBtn">Guardar Propina</button>
            </div>
        </div>
    </div>
</div>

<script>

document.addEventListener('DOMContentLoaded', function() {
    var tipModal = document.getElementById('tipModal');
    var tipForm = document.getElementById('tipForm');
    var tipPercentageInput = document.getElementById('tip_percentage');
    var tipAmountInput = document.getElementById('tip_amount');
    var tipCalculated = document.getElementById('tip_calculated');
    var tipManualGroup = document.getElementById('tip_manual_group');
    var tipPercentageGroup = document.getElementById('tip_percentage_group');
    var saveTipBtn = document.getElementById('saveTipBtn');
    var manualTipInfo = document.getElementById('manualTipInfo');

    tipModal.addEventListener('show.bs.modal', function (event) {
        var button = event.relatedTarget;
        var ticketId = button.getAttribute('data-ticket-id');
        var total = parseFloat(button.getAttribute('data-total'));
        var paymentMethod = button.getAttribute('data-payment-method');
        document.getElementById('tip_ticket_id').value = ticketId;
        document.getElementById('tip_payment_method').value = paymentMethod;
        tipPercentageInput.value = '';
        tipAmountInput.value = '';
        tipCalculated.textContent = '$0.00';
        // Si no hay ticketId, solo mostrar campo de monto manual
        if (!ticketId || ticketId === '0') {
            tipPercentageGroup.style.display = 'none';
            tipManualGroup.style.display = 'block';
            manualTipInfo.style.display = 'block';
        } else {
            tipPercentageGroup.style.display = 'block';
            tipManualGroup.style.display = (paymentMethod === 'efectivo') ? 'block' : 'none';
            manualTipInfo.style.display = 'none';
        }
    });

    document.querySelectorAll('.tip-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var percentage = parseFloat(this.getAttribute('data-percentage'));
            tipPercentageInput.value = percentage;
            var ticketId = document.getElementById('tip_ticket_id').value;
            var total = 0;
            if (ticketId && ticketId !== '0') {
                total = parseFloat(document.querySelector('[data-bs-target="#tipModal"][data-ticket-id="' + ticketId + '"]').getAttribute('data-total'));
            }
            var tipValue = (total * percentage / 100).toFixed(2);
            tipCalculated.textContent = '$' + tipValue;
            tipAmountInput.value = '';
        });
    });

    tipPercentageInput.addEventListener('input', function() {
        var percentage = parseFloat(this.value);
        var ticketId = document.getElementById('tip_ticket_id').value;
        var total = 0;
        if (ticketId && ticketId !== '0') {
            total = parseFloat(document.querySelector('[data-bs-target="#tipModal"][data-ticket-id="' + ticketId + '"]').getAttribute('data-total'));
        }
        if (!isNaN(percentage) && percentage > 0) {
            var tipValue = (total * percentage / 100).toFixed(2);
            tipCalculated.textContent = '$' + tipValue;
            tipAmountInput.value = '';
        } else {
            tipCalculated.textContent = '$0.00';
        }
    });

    tipAmountInput.addEventListener('input', function() {
        var tipValue = parseFloat(this.value);
        if (!isNaN(tipValue) && tipValue > 0) {
            tipCalculated.textContent = '$' + tipValue.toFixed(2);
            tipPercentageInput.value = '';
        } else {
            tipCalculated.textContent = '$0.00';
        }
    });

    saveTipBtn.addEventListener('click', function() {
        var ticketId = document.getElementById('tip_ticket_id').value;
        var paymentMethod = document.getElementById('tip_payment_method').value;
        var tipPercentage = tipPercentageInput.value ? parseFloat(tipPercentageInput.value) : null;
        var tipAmount = tipAmountInput.value ? parseFloat(tipAmountInput.value) : null;
        var data = new FormData();
        data.append('ticket_id', ticketId);
        if (!ticketId || ticketId === '0') {
            // Propina manual (sin ticket)
            if (tipAmount) {
                data.append('tip_amount', tipAmount);
                data.append('tip_percentage', '');
            }
        } else if (paymentMethod === 'efectivo' && tipAmount) {
            data.append('tip_amount', tipAmount);
            data.append('tip_percentage', '');
        } else if (tipPercentage) {
            data.append('tip_percentage', tipPercentage);
            data.append('tip_amount', '');
        }
        fetch('<?= BASE_URL ?>/tickets/addTip', {
            method: 'POST',
            body: data
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                tipCalculated.textContent = '$' + (result.tip_amount ? result.tip_amount.toFixed(2) : '0.00');
                alert('Propina guardada correctamente');
            } else {
                alert(result.error || 'Error al guardar la propina');
            }
            var modal = bootstrap.Modal.getInstance(tipModal);
            modal.hide();
        })
        .catch(() => {
            alert('Error de red al guardar la propina');
        });
    });
});
</script>

<!-- Payment Method Summary -->
<?php if (!empty($salesReport['by_payment_method'])): ?>
<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="bi bi-credit-card"></i> Resumen por Método de Pago
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php foreach ($salesReport['by_payment_method'] as $method): ?>
                    <div class="col-md-4 mb-3">
                        <div class="card border-left-primary">
                            <div class="card-body">
                                <div class="d-flex align-items-center">
                                    <div class="me-3">
                                        <i class="bi bi-<?= getPaymentMethodIcon($method['payment_method']) ?> text-primary" style="font-size: 2rem;"></i>
                                    </div>
                                    <div>
                                        <h6 class="card-title mb-1"><?= getPaymentMethodText($method['payment_method']) ?></h6>
                                        <p class="card-text mb-0">
                                            <strong><?= $method['method_count'] ?> tickets</strong><br>
                                            <span class="text-success">$<?= number_format($method['total_amount'], 2) ?></span>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
<?php endif; ?>

<style>
.payment-efectivo { background-color: #198754; color: #fff; }
.payment-tarjeta { background-color: #0d6efd; color: #fff; }
.payment-transferencia { background-color: #6f42c1; color: #fff; }
.payment-intercambio { background-color: #17a2b8; color: #fff; }
.payment-pendiente_por_cobrar { background-color: #dc3545; color: #fff; }

.border-left-primary {
    border-left: 4px solid #0d6efd !important;
}
</style>

<?php
function getPaymentMethodText($method) {
    $methods = [
        'efectivo' => 'Efectivo',
        'tarjeta' => 'Tarjeta',
        'transferencia' => 'Transferencia',
        'intercambio' => 'Intercambio',
        'pendiente_por_cobrar' => 'Pendiente por Cobrar'
    ];
    
    return $methods[$method] ?? $method;
}

function getPaymentMethodIcon($method) {
    $icons = [
        'efectivo' => 'cash',
        'tarjeta' => 'credit-card',
        'transferencia' => 'bank',
        'intercambio' => 'arrow-left-right',
        'pendiente_por_cobrar' => 'clock-history'
    ];
    
    return $icons[$method] ?? 'cash';
}
?>
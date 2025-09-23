<?php $title = 'Pedidos de Mesa ' . $table['number']; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>
        <i class="bi bi-grid-3x3-gap"></i> Mesa <?= $table['number'] ?> - Pedidos
    </h1>
    <div>
        <a href="<?= BASE_URL ?>/waiters/tableOrders" class="btn btn-secondary me-2">
            <i class="bi bi-arrow-left"></i> Volver
        </a>
        <a href="<?= BASE_URL ?>/orders/create?table_id=<?= $table['id'] ?>" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Nuevo Pedido
        </a>
    </div>
</div>

<!-- Table Info Card -->
<div class="card mb-4">
    <div class="card-body">
        <div class="row">
            <div class="col-md-3">
                <h6 class="text-muted">Mesa</h6>
                <p class="h5"><?= $table['number'] ?></p>
            </div>
            <div class="col-md-3">
                <h6 class="text-muted">Estado</h6>
                <span class="badge bg-<?= getTableStatusColor($table['status']) ?>">
                    <?= getTableStatusText($table['status']) ?>
                </span>
            </div>
            <div class="col-md-3">
                <h6 class="text-muted">Capacidad</h6>
                <p><?= $table['capacity'] ?> personas</p>
            </div>
            <div class="col-md-3">
                <h6 class="text-muted">Pedidos Hoy</h6>
                <p class="h5 text-info"><?= count($orders) ?></p>
            </div>
        </div>
    </div>
</div>

<?php if (empty($orders)): ?>
<div class="card">
    <div class="card-body">
        <div class="text-center py-5">
            <i class="bi bi-cart-x display-1 text-muted"></i>
            <h3 class="mt-3">No hay pedidos para esta mesa</h3>
            <p class="text-muted">La mesa no tiene pedidos registrados para el día de hoy.</p>
            <div class="mt-4">
                <a href="<?= BASE_URL ?>/orders/create?table_id=<?= $table['id'] ?>" class="btn btn-primary">
                    <i class="bi bi-plus-circle"></i> Crear Primer Pedido
                </a>
            </div>
        </div>
    </div>
</div>

<?php else: ?>

<!-- Orders List -->
<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">
            <i class="bi bi-list-check"></i> Pedidos del Día
        </h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Pedido #</th>
                        <th>Cliente</th>
                        <th>Estado</th>
                        <th>Items</th>
                        <th>Total</th>
                        <th>Hora</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order): ?>
                    <tr>
                        <td>
                            <strong>#<?= $order['id'] ?></strong>
                        </td>
                        <td>
                            <?php if (!empty($order['customer_name'])): ?>
                                <i class="bi bi-person-fill text-info"></i>
                                <?= htmlspecialchars($order['customer_name']) ?>
                            <?php else: ?>
                                <span class="text-muted">Mesa general</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge status-<?= $order['status'] ?>">
                                <?= getOrderStatusText($order['status']) ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge bg-secondary"><?= $order['items_count'] ?> items</span>
                        </td>
                        <td>
                            <strong>$<?= number_format($order['total'], 2) ?></strong>
                        </td>
                        <td>
                            <small><?= date('H:i', strtotime($order['created_at'])) ?></small>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm" role="group">
                                <a href="<?= BASE_URL ?>/orders/show/<?= $order['id'] ?>" 
                                   class="btn btn-outline-primary btn-sm" 
                                   title="Ver detalles">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <?php if ($order['status'] !== ORDER_DELIVERED && $order['status'] !== ORDER_PENDING_CONFIRMATION): ?>
                                <button type="button" 
                                        class="btn btn-outline-success btn-sm" 
                                        title="Cambiar estado"
                                        onclick="showStatusModal(<?= $order['id'] ?>, '<?= $order['status'] ?>')">
                                    <i class="bi bi-arrow-up-circle"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Order Summary -->
<div class="row mt-4">
    <div class="col-md-4">
        <div class="card">
            <div class="card-body text-center">
                <h5 class="text-muted">Total Pedidos</h5>
                <h2 class="text-primary"><?= count($orders) ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-body text-center">
                <h5 class="text-muted">Pendientes</h5>
                <h2 class="text-warning">
                    <?= count(array_filter($orders, function($o) { 
                        return in_array($o['status'], [ORDER_PENDING, ORDER_PREPARING, ORDER_READY]); 
                    })) ?>
                </h2>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-body text-center">
                <h5 class="text-muted">Total Ventas</h5>
                <h2 class="text-success">$<?= number_format(array_sum(array_column($orders, 'total')), 2) ?></h2>
            </div>
        </div>
    </div>
</div>

<?php endif; ?>

<!-- Status Update Modal -->
<div class="modal fade" id="statusModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Actualizar Estado del Pedido</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="statusForm" method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="status" class="form-label">Nuevo Estado</label>
                        <select class="form-select" id="status" name="status" required>
                            <option value="pendiente">Pendiente</option>
                            <option value="en_preparacion">En Preparación</option>
                            <option value="listo">Listo</option>
                            <option value="entregado">Entregado</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Actualizar Estado</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.status-pendiente { background-color: #6c757d; color: #fff; }
.status-en_preparacion { background-color: #fd7e14; color: #fff; }
.status-listo { background-color: #20c997; color: #fff; }
.status-entregado { background-color: #198754; color: #fff; }
.status-pendiente_confirmacion { background-color: #dc3545; color: #fff; }
</style>

<script>
function showStatusModal(orderId, currentStatus) {
    const modal = new bootstrap.Modal(document.getElementById('statusModal'));
    const form = document.getElementById('statusForm');
    const statusSelect = document.getElementById('status');
    
    // Set form action
    form.action = '<?= BASE_URL ?>/orders/updateStatus/' + orderId;
    
    // Set current status as selected
    statusSelect.value = currentStatus;
    
    // Remove options that don't make sense based on current status
    const options = statusSelect.querySelectorAll('option');
    options.forEach(option => {
        option.style.display = 'block';
    });
    
    // Logic to show only valid next states
    switch(currentStatus) {
        case 'pendiente':
            options.forEach(option => {
                if (['pendiente', 'en_preparacion'].includes(option.value)) {
                    option.style.display = 'block';
                } else {
                    option.style.display = 'none';
                }
            });
            statusSelect.value = 'en_preparacion';
            break;
        case 'en_preparacion':
            options.forEach(option => {
                if (['en_preparacion', 'listo'].includes(option.value)) {
                    option.style.display = 'block';
                } else {
                    option.style.display = 'none';
                }
            });
            statusSelect.value = 'listo';
            break;
        case 'listo':
            options.forEach(option => {
                if (['listo', 'entregado'].includes(option.value)) {
                    option.style.display = 'block';
                } else {
                    option.style.display = 'none';
                }
            });
            statusSelect.value = 'entregado';
            break;
    }
    
    modal.show();
}
</script>

<?php
function getTableStatusColor($status) {
    $colors = [
        'disponible' => 'success',
        'ocupada' => 'warning',
        'cuenta_solicitada' => 'info',
        'cerrada' => 'secondary'
    ];
    return $colors[$status] ?? 'secondary';
}

function getTableStatusText($status) {
    $statuses = [
        'disponible' => 'Disponible',
        'ocupada' => 'Ocupada',
        'cuenta_solicitada' => 'Cuenta Solicitada',
        'cerrada' => 'Cerrada'
    ];
    return $statuses[$status] ?? $status;
}

function getOrderStatusText($status) {
    $statuses = [
        'pendiente_confirmacion' => 'Pendiente Confirmación',
        'pendiente' => 'Pendiente',
        'en_preparacion' => 'En Preparación',
        'listo' => 'Listo',
        'entregado' => 'Entregado'
    ];
    return $statuses[$status] ?? $status;
}
?>
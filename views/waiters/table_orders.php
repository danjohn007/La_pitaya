<?php $title = 'Pedidos por Mesa'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="bi bi-grid-3x3-gap"></i> Pedidos por Mesa</h1>
    <a href="<?= BASE_URL ?>/orders/create" class="btn btn-primary">
        <i class="bi bi-plus-circle"></i> Nuevo Pedido
    </a>
</div>

<?php if (empty($tableOrderSummary)): ?>
<div class="card">
    <div class="card-body">
        <div class="text-center py-5">
            <i class="bi bi-grid-3x3-gap display-1 text-muted"></i>
            <h3 class="mt-3">No tienes mesas asignadas</h3>
            <p class="text-muted">Espera a que el administrador te asigne mesas o consulta las mesas disponibles.</p>
            
            <?php if (!empty($availableTables)): ?>
            <div class="mt-4">
                <h5>Mesas Disponibles</h5>
                <div class="row">
                    <?php foreach ($availableTables as $table): ?>
                    <div class="col-md-3 mb-2">
                        <div class="card border-success">
                            <div class="card-body text-center">
                                <h6 class="card-title">Mesa <?= $table['number'] ?></h6>
                                <span class="badge bg-success">Disponible</span>
                                <div class="mt-2">
                                    <a href="<?= BASE_URL ?>/orders/create?table_id=<?= $table['id'] ?>" class="btn btn-sm btn-success">
                                        <i class="bi bi-plus"></i> Tomar Mesa
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php else: ?>

<div class="row">
    <?php foreach ($tableOrderSummary as $summary): ?>
    <div class="col-lg-4 col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    <i class="bi bi-grid-3x3-gap"></i> Mesa <?= $summary['table']['number'] ?>
                </h5>
                <span class="badge bg-<?= getTableStatusColor($summary['table']['status']) ?>">
                    <?= getTableStatusText($summary['table']['status']) ?>
                </span>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-6">
                        <small class="text-muted">Pedidos Hoy</small>
                        <div class="h5 mb-0"><?= count($summary['orders']) ?></div>
                    </div>
                    <div class="col-6">
                        <small class="text-muted">Pendientes</small>
                        <div class="h5 mb-0 text-warning"><?= $summary['pending_count'] ?></div>
                    </div>
                </div>
                
                <div class="mb-3">
                    <small class="text-muted">Total del Día</small>
                    <div class="h5 mb-0 text-success">$<?= number_format($summary['total_amount'], 2) ?></div>
                </div>
                
                <?php if (!empty($summary['orders'])): ?>
                <div class="mb-3">
                    <h6>Últimos Pedidos:</h6>
                    <?php 
                    $recentOrders = array_slice($summary['orders'], -3);
                    foreach ($recentOrders as $order): 
                    ?>
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <small>
                            Pedido #<?= $order['id'] ?>
                            <span class="badge status-<?= $order['status'] ?> badge-sm">
                                <?= getOrderStatusText($order['status']) ?>
                            </span>
                        </small>
                        <small class="text-muted">$<?= number_format($order['total'], 2) ?></small>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <div class="card-footer">
                <div class="d-flex gap-2">
                    <a href="<?= BASE_URL ?>/waiters/tableOrders/<?= $summary['table']['id'] ?>" 
                       class="btn btn-primary btn-sm flex-fill">
                        <i class="bi bi-eye"></i> Ver Todos
                    </a>
                    <a href="<?= BASE_URL ?>/orders/create?table_id=<?= $summary['table']['id'] ?>" 
                       class="btn btn-success btn-sm flex-fill">
                        <i class="bi bi-plus"></i> Nuevo Pedido
                    </a>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Available Tables Section -->
<?php if (!empty($availableTables)): ?>
<div class="mt-5">
    <h4><i class="bi bi-grid-3x3-gap-fill"></i> Mesas Disponibles</h4>
    <div class="row">
        <?php foreach ($availableTables as $table): ?>
        <?php if (!isset($tableOrderSummary[$table['id']])): ?>
        <div class="col-lg-3 col-md-4 col-sm-6 mb-3">
            <div class="card border-success">
                <div class="card-body text-center">
                    <h6 class="card-title">Mesa <?= $table['number'] ?></h6>
                    <p class="card-text">
                        <span class="badge bg-success">Disponible</span><br>
                        <small class="text-muted">Capacidad: <?= $table['capacity'] ?> personas</small>
                    </p>
                    <a href="<?= BASE_URL ?>/orders/create?table_id=<?= $table['id'] ?>" 
                       class="btn btn-success btn-sm">
                        <i class="bi bi-plus"></i> Tomar Mesa
                    </a>
                </div>
            </div>
        </div>
        <?php endif; ?>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php endif; ?>

<style>
.status-pendiente { background-color: #6c757d; color: #fff; }
.status-en_preparacion { background-color: #fd7e14; color: #fff; }
.status-listo { background-color: #20c997; color: #fff; }
.status-entregado { background-color: #198754; color: #fff; }
.status-pendiente_confirmacion { background-color: #dc3545; color: #fff; }

.badge-sm {
    font-size: 0.65em;
}
</style>

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
        'pendiente_confirmacion' => 'Pend. Conf.',
        'pendiente' => 'Pendiente',
        'en_preparacion' => 'Preparando',
        'listo' => 'Listo',
        'entregado' => 'Entregado'
    ];
    return $statuses[$status] ?? $status;
}
?>
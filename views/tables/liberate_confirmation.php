<?php $title = 'Liberar Todas las Mesas'; ?>

<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-unlock"></i> Liberar Todas las Mesas
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($pendingOrders)): ?>
                    <div class="alert alert-danger">
                        <h6><i class="bi bi-exclamation-triangle"></i> No se pueden liberar las mesas</h6>
                        <p class="mb-2">Existen <strong><?= count($pendingOrders) ?></strong> pedidos del día de hoy que aún no han sido entregados:</p>
                        
                        <div class="table-responsive">
                            <table class="table table-sm table-striped">
                                <thead>
                                    <tr>
                                        <th>Pedido #</th>
                                        <th>Mesa</th>
                                        <th>Mesero</th>
                                        <th>Estado</th>
                                        <th>Creado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pendingOrders as $order): ?>
                                    <tr>
                                        <td><?= $order['id'] ?></td>
                                        <td>
                                            <?php if ($order['table_number']): ?>
                                                Mesa <?= $order['table_number'] ?>
                                            <?php else: ?>
                                                <span class="text-muted">Sin mesa</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($order['waiter_name'] ?? 'Sin asignar') ?></td>
                                        <td>
                                            <span class="badge status-<?= $order['status'] ?>">
                                                <?= getOrderStatusText($order['status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <small><?= date('H:i', strtotime($order['created_at'])) ?></small>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <p class="mb-0">
                            <strong>Acción requerida:</strong> Complete la entrega de todos los pedidos pendientes antes de liberar las mesas.
                        </p>
                    </div>
                    
                    <div class="d-flex justify-content-between">
                        <a href="<?= BASE_URL ?>/tables" class="btn btn-secondary">
                            <i class="bi bi-arrow-left"></i> Volver a Mesas
                        </a>
                        <a href="<?= BASE_URL ?>/orders" class="btn btn-primary">
                            <i class="bi bi-list-check"></i> Ver Pedidos Pendientes
                        </a>
                    </div>
                    
                    <?php else: ?>
                    <div class="alert alert-success">
                        <h6><i class="bi bi-check-circle"></i> Todos los pedidos del día han sido entregados</h6>
                        <p class="mb-0">Se pueden liberar todas las mesas de forma segura.</p>
                    </div>
                    
                    <div class="alert alert-warning">
                        <h6><i class="bi bi-exclamation-triangle"></i> Confirmación requerida</h6>
                        <p>Esta acción liberará <strong><?= $totalTables ?></strong> mesas, estableciendo su estado como "Disponible" y eliminando todas las asignaciones de meseros.</p>
                        <p class="mb-0"><strong>Esta acción no se puede deshacer.</strong></p>
                    </div>
                    
                    <form method="POST" onsubmit="return confirm('¿Está seguro de que desea liberar TODAS las mesas? Esta acción no se puede deshacer.')">
                        <div class="d-flex justify-content-between">
                            <a href="<?= BASE_URL ?>/tables" class="btn btn-secondary">
                                <i class="bi bi-arrow-left"></i> Cancelar
                            </a>
                            <button type="submit" class="btn btn-danger">
                                <i class="bi bi-unlock"></i> Liberar Todas las Mesas
                            </button>
                        </div>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
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

<?php
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
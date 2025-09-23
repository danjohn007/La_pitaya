<?php $title = 'Cancelar Ticket'; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="text-danger"><i class="bi bi-x-circle"></i> Cancelar Ticket</h1>
    <a href="<?= BASE_URL ?>/tickets/show/<?= $ticket['id'] ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Volver al Ticket
    </a>
</div>

<?php if (isset($error)): ?>
    <div class="alert alert-danger">
        <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-md-8">
        <div class="card border-danger">
            <div class="card-header bg-danger text-white">
                <h5 class="card-title mb-0">
                    <i class="bi bi-exclamation-triangle"></i> Confirmar Cancelación de Ticket
                </h5>
            </div>
            <div class="card-body">
                <div class="alert alert-warning">
                    <h6><i class="bi bi-exclamation-triangle"></i> ¡Atención!</h6>
                    <p class="mb-2">Esta acción <strong>NO se puede deshacer</strong>. Al cancelar este ticket:</p>
                    <ul class="mb-0">
                        <li>Se descontará <strong>$<?= number_format($ticket['total'], 2) ?></strong> del ingreso del sistema</li>
                        <li>El pedido volverá al estado "Listo" para ser re-procesado si es necesario</li>
                        <li>Se revertirán las estadísticas del cliente</li>
                        <li>Se registrará una auditoría de la cancelación</li>
                    </ul>
                </div>

                <form method="POST" action="<?= BASE_URL ?>/tickets/cancel/<?= $ticket['id'] ?>">
                    <div class="mb-3">
                        <label for="cancellation_reason" class="form-label">
                            <strong>Motivo de la Cancelación *</strong>
                        </label>
                        <textarea class="form-control <?= isset($errors['cancellation_reason']) ? 'is-invalid' : '' ?>" 
                                  id="cancellation_reason" 
                                  name="cancellation_reason" 
                                  rows="4" 
                                  required
                                  placeholder="Describa detalladamente el motivo de la cancelación (mínimo 10 caracteres)..."><?= htmlspecialchars($old['cancellation_reason'] ?? '') ?></textarea>
                        <?php if (isset($errors['cancellation_reason'])): ?>
                            <div class="invalid-feedback">
                                <?= htmlspecialchars($errors['cancellation_reason']) ?>
                            </div>
                        <?php endif; ?>
                        <div class="form-text">
                            El motivo será registrado permanentemente en el sistema para auditorías.
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <a href="<?= BASE_URL ?>/tickets/show/<?= $ticket['id'] ?>" class="btn btn-secondary">
                            <i class="bi bi-x-circle"></i> Cancelar Acción
                        </a>
                        <button type="submit" 
                                class="btn btn-danger" 
                                onclick="return confirm('¿Está ABSOLUTAMENTE SEGURO de cancelar este ticket? Esta acción NO se puede deshacer.')">
                            <i class="bi bi-trash"></i> Confirmar Cancelación
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="bi bi-receipt"></i> Detalles del Ticket
                </h5>
            </div>
            <div class="card-body">
                <dl class="row">
                    <dt class="col-sm-6">Número:</dt>
                    <dd class="col-sm-6"><?= htmlspecialchars($ticket['ticket_number']) ?></dd>

                    <dt class="col-sm-6">Mesa:</dt>
                    <dd class="col-sm-6">
                        <?= $ticket['table_number'] ? 'Mesa ' . $ticket['table_number'] : 'Para llevar' ?>
                    </dd>

                    <dt class="col-sm-6">Cajero:</dt>
                    <dd class="col-sm-6"><?= htmlspecialchars($ticket['cashier_name']) ?></dd>

                    <dt class="col-sm-6">Mesero:</dt>
                    <dd class="col-sm-6">
                        <?= htmlspecialchars($ticket['waiter_name']) ?> 
                        (<?= htmlspecialchars($ticket['employee_code']) ?>)
                    </dd>

                    <dt class="col-sm-6">Fecha:</dt>
                    <dd class="col-sm-6"><?= date('d/m/Y H:i', strtotime($ticket['created_at'])) ?></dd>

                    <dt class="col-sm-6">Método de Pago:</dt>
                    <dd class="col-sm-6">
                        <span class="badge bg-info">
                            <?= ucfirst($ticket['payment_method']) ?>
                        </span>
                    </dd>
                </dl>

                <hr>

                <dl class="row">
                    <dt class="col-sm-6">Subtotal:</dt>
                    <dd class="col-sm-6">$<?= number_format($ticket['subtotal'], 2) ?></dd>

                    <dt class="col-sm-6">IVA (16%):</dt>
                    <dd class="col-sm-6">$<?= number_format($ticket['tax'], 2) ?></dd>

                    <dt class="col-sm-6"><strong>Total:</strong></dt>
                    <dd class="col-sm-6"><strong class="text-danger">$<?= number_format($ticket['total'], 2) ?></strong></dd>
                </dl>
            </div>
        </div>
    </div>
</div>
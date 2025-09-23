<?php $title = 'Actualizar Método de Pago'; ?>

<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-credit-card"></i> Actualizar Método de Pago
                    </h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <strong>Ticket #<?= htmlspecialchars($ticket['ticket_number']) ?></strong><br>
                        <small>Total: $<?= number_format($ticket['total'], 2) ?></small><br>
                        <small>Método actual: <?= getPaymentMethodText($ticket['payment_method']) ?></small>
                    </div>

                    <?php if (isset($error)): ?>
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
                    </div>
                    <?php endif; ?>

                    <form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                        <div class="mb-3">
                            <label for="payment_method" class="form-label">Nuevo Método de Pago <span class="text-danger">*</span></label>
                            <select class="form-select <?= isset($errors['payment_method']) ? 'is-invalid' : '' ?>" 
                                    id="payment_method" 
                                    name="payment_method" 
                                    required>
                                <option value="">Seleccionar método de pago</option>
                                <option value="efectivo" <?= ($old['payment_method'] ?? '') === 'efectivo' ? 'selected' : '' ?>>Efectivo</option>
                                <option value="tarjeta" <?= ($old['payment_method'] ?? '') === 'tarjeta' ? 'selected' : '' ?>>Tarjeta</option>
                                <option value="transferencia" <?= ($old['payment_method'] ?? '') === 'transferencia' ? 'selected' : '' ?>>Transferencia</option>
                                <option value="intercambio" <?= ($old['payment_method'] ?? '') === 'intercambio' ? 'selected' : '' ?>>Intercambio</option>
                                <option value="pendiente_por_cobrar" <?= ($old['payment_method'] ?? '') === 'pendiente_por_cobrar' ? 'selected' : '' ?>>Pendiente por Cobrar</option>
                            </select>
                            <?php if (isset($errors['payment_method'])): ?>
                                <div class="invalid-feedback">
                                    <?= htmlspecialchars($errors['payment_method']) ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="mb-3">
                            <label for="evidence_file" class="form-label">Evidencia de Pago (Opcional)</label>
                            <input type="file" 
                                   class="form-control <?= isset($errors['evidence_file']) ? 'is-invalid' : '' ?>" 
                                   id="evidence_file" 
                                   name="evidence_file"
                                   accept=".jpg,.jpeg,.png,.pdf,.doc,.docx">
                            <div class="form-text">
                                Archivos permitidos: JPG, PNG, PDF, DOC, DOCX (máximo 5MB)
                            </div>
                            <?php if (isset($errors['evidence_file'])): ?>
                                <div class="invalid-feedback">
                                    <?= htmlspecialchars($errors['evidence_file']) ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="d-flex justify-content-between">
                            <a href="<?= BASE_URL ?>/tickets" class="btn btn-secondary">
                                <i class="bi bi-arrow-left"></i> Volver
                            </a>
                            <button type="submit" class="btn btn-success">
                                <i class="bi bi-check-circle"></i> Actualizar Método de Pago
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Bootstrap form validation
(function() {
    'use strict';
    window.addEventListener('load', function() {
        var forms = document.getElementsByClassName('needs-validation');
        var validation = Array.prototype.filter.call(forms, function(form) {
            form.addEventListener('submit', function(event) {
                if (form.checkValidity() === false) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            }, false);
        });
    }, false);
})();
</script>

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
?>
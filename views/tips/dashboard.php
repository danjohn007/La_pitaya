<?php $title = 'Dashboard de Propinas'; ?>

<div class="container mt-4">
  <h1><i class="bi bi-cash-coin"></i> Dashboard de Ingresos por Propinas</h1>
  <div class="card mt-3">
    <div class="card-header">
      <strong>Propinas registradas</strong>
    </div>
    <div class="card-body">
      <form method="GET" class="row g-3 mb-3">
        <div class="col-md-4">
          <label for="date_from" class="form-label">Desde</label>
          <input type="date" class="form-control" id="date_from" name="date_from" value="<?= htmlspecialchars($_GET['date_from'] ?? date('Y-m-01')) ?>">
        </div>
        <div class="col-md-4">
          <label for="date_to" class="form-label">Hasta</label>
          <input type="date" class="form-control" id="date_to" name="date_to" value="<?= htmlspecialchars($_GET['date_to'] ?? date('Y-m-d')) ?>">
        </div>
        <div class="col-md-4 d-flex align-items-end">
          <button type="submit" class="btn btn-primary">
            <i class="bi bi-funnel"></i> Filtrar
          </button>
        </div>
      </form>
      <table class="table table-striped">
        <thead>
          <tr>
            <th>Ticket #</th>
            <th>Monto</th>
            <th>Porcentaje</th>
            <th>Fecha</th>
            <th>Cajero</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($tips as $tip): ?>
          <tr>
            <td><?= htmlspecialchars($tip['ticket_number']) ?></td>
            <td>$<?= number_format($tip['tip_amount'], 2) ?></td>
            <td><?= $tip['tip_percentage'] ? $tip['tip_percentage'] . '%' : '-' ?></td>
            <td><?= date('d/m/Y', strtotime($tip['tip_date'])) ?></td>
            <td><?= htmlspecialchars($tip['cashier_name']) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php if (empty($tips)): ?>
        <div class="alert alert-info mt-3">No hay propinas registradas en el periodo seleccionado.</div>
      <?php endif; ?>
    </div>
  </div>
</div>

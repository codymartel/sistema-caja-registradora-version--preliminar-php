<?php
// caja_salida.php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'db.php'; // conexión y helpers

// helpers
if (!function_exists('formatMoney')) {
    function formatMoney($v) { return 'S/. ' . number_format(floatval($v), 2); }
}

// Obtener caja activa
$caja_activa = null;
if (isset($_SESSION['caja_id'])) {
    $caja_activa = fetchOne("SELECT * FROM cajas WHERE id = ? AND estado = 'abierta'", [$_SESSION['caja_id']]);
}
if (!$caja_activa) {
    $hoy = date('Y-m-d');
    $caja_activa = fetchOne("SELECT * FROM cajas WHERE fecha = ? AND estado = 'abierta' ORDER BY id DESC LIMIT 1", [$hoy]);
    if (!$caja_activa) {
        $caja_activa = fetchOne("SELECT * FROM cajas WHERE estado = 'abierta' ORDER BY id DESC LIMIT 1");
    }
    if ($caja_activa) $_SESSION['caja_id'] = $caja_activa['id'];
}

// Obtener transacciones
$transacciones = $caja_activa ? fetchAll(
    "SELECT v.*, tp.nombre as tipo_pago, pv.nombre as punto_venta
     FROM ventas v
     JOIN tipos_pago tp ON v.tipo_pago_id = tp.id
     JOIN puntos_venta pv ON v.punto_venta_id = pv.id
     WHERE v.caja_id = ? AND v.total_salida > 0
     ORDER BY v.fecha ASC LIMIT 100", 
    [$caja_activa['id']]
) : [];

// Calcular totales de salida
$total_salida = 0;
foreach ($transacciones as $t) {
    $total_salida += $t['total_salida'];
}

// Saldo inicial
$saldo_inicial = $caja_activa['saldo_inicial'] ?? 0;
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Caja Solo Salidas</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
<style>
.text-money { font-weight: 600; letter-spacing: 0.5px; }
.card-body h6 { font-size: 0.9rem; }
.card { transition: all 0.2s ease-in-out; }
.card:hover { box-shadow: 0 0.6rem 1rem rgba(0,0,0,0.08); }
.table-bordered th, .table-bordered td { border-color: #dee2e6 !important; }
</style>
</head>
<body>
<div class="container mt-4">
  <div class="col-12">


  <!-- 🖤 Barra de opciones superior en negro -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4 shadow-sm">
  <div class="container-fluid">
    <a class="navbar-brand fw-bold text-white" href="#">
      💼 Sistema de Caja
    </a>

    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarOpciones" aria-controls="navbarOpciones" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="navbarOpciones">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <li class="nav-item">
          <a class="nav-link active text-white" href="index.php">
            <i class="bi bi-house-door"></i> Inicio
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link text-white" href="caja_efectivo.php">
            <i class="bi bi-cart-check"></i> caja efectivo
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link text-white" href="caja_otros.php">
            <i class="bi bi-people"></i> caja yape y otros
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link text-white" href="agente.php">
            <i class="bi bi-bar-chart"></i> caja  agente
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link text-white" href="salidas.php">
            <i class="bi bi-gear"></i> caja salidas
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link text-white" href="conteomonedas.php">
            <i class="bi bi-gear"></i> conteo monedas caja fisica
          </a>
        
      </ul>
      
    </div>
  </div>
</nav>

    <h5 class="mb-3 text-danger"><i class="bi bi-box-arrow-up"></i> Caja Solo Salidas</h5>

    <div class="card border border-danger-subtle rounded-4 shadow-sm mb-4">
      <div class="card-header bg-light fw-semibold border-bottom border-danger-subtle">
        <i class="bi bi-journal-minus me-2 text-danger"></i> Resumen Salidas
      </div>
      <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between gap-3 mb-4">
          <!-- SALDO INICIAL -->
          <div class="card flex-fill shadow-sm border border-secondary-subtle rounded-3" style="min-width: 210px;">
            <div class="card-body text-center">
              <h6 class="text-muted mb-2"><i class="bi bi-cash-stack"></i> Saldo Inicial</h6>
              <h3 class="text-dark text-money"><?= formatMoney($saldo_inicial); ?></h3>
            </div>
          </div>
          <!-- TOTAL SALIDA -->
          <div class="card flex-fill shadow-sm border border-secondary-subtle rounded-3" style="min-width: 210px;">
            <div class="card-body text-center">
              <h6 class="text-muted mb-2"><i class="bi bi-arrow-up-circle"></i> Total Salida</h6>
              <h3 class="text-danger text-money"><?= formatMoney($total_salida); ?></h3>
            </div>
          </div>
          <!-- SALDO ACTUAL -->
          <div class="card flex-fill shadow-sm border border-secondary-subtle rounded-3" style="min-width: 210px;">
            <div class="card-body text-center">
              <h6 class="text-muted mb-2"><i class="bi bi-wallet2"></i> Saldo Actual</h6>
              <h3 class="text-primary text-money"><?= formatMoney($saldo_inicial - $total_salida); ?></h3>
            </div>
          </div>
        </div>

        <!-- TABLA SALIDAS -->
        <div class="card border border-secondary-subtle rounded-3 shadow-sm mt-4">
          <div class="card-header bg-light fw-semibold border-bottom">
            <i class="bi bi-arrow-up-circle text-danger me-2"></i> Movimientos de Salida
          </div>
          <div class="card-body p-0">
            <table class="table table-hover table-bordered mb-0" style="table-layout: fixed; width: 100%;">
              <thead class="table-light">
                <tr>
                  <th style="width: 60px;">Hora</th>
                  <th style="width: 200px;">Descripción</th>
                  <th style="width: 90px;">Tipo Pago</th>
                  <th style="width: 120px;">Punto Venta</th>
                  <th style="width: 50px;" class="text-end">Cant.</th>
                  <th style="width: 90px;" class="text-end">Precio Unit.</th>
                  <th style="width: 90px;" class="text-end">Entrada</th>
                  <th style="width: 90px;" class="text-end">Salida</th>
                  <th style="width: 90px;" class="text-end">Saldo</th>
                  <th style="width: 90px;" class="text-center no-print">Acciones</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($transacciones as $t): ?>
                  <tr>
                    <td><small><?= date('H:i:s', strtotime($t['fecha'])); ?></small></td>
                    <td><?= htmlspecialchars($t['descripcion']); ?></td>
                    <td><small><span class="badge bg-secondary"><?= $t['tipo_pago']; ?></span></small></td>
                    <td><small><?= $t['punto_venta']; ?></small></td>
                    <td class="text-end"><?= $t['cantidad']; ?></td>
                    <td class="text-end"><?= ($t['cantidad']>0)?number_format($t['total_entrada']/$t['cantidad'],2):'0.00'; ?></td>
                    <td class="text-end text-success"><?= number_format($t['total_entrada'],2); ?></td>
                    <td class="text-end text-danger"><?= number_format($t['total_salida'],2); ?></td>
                    <td class="text-end"><?= number_format($t['saldo'],2); ?></td>
                    <td class="text-center no-print">
                      <a href="edit.php?id=<?= $t['id']; ?>" class="btn btn-sm btn-warning"><i class="bi bi-pencil"></i></a>
                      <a href="delete.php?id=<?= $t['id']; ?>" class="btn btn-sm btn-danger btn-delete"><i class="bi bi-trash"></i></a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>

      </div>
    </div>
  </div>
</div>
</body>
</html>

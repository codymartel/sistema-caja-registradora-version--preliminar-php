<?php
require_once "db.php";

// Obtener todas las cajas
$sql = "SELECT * FROM cajas ORDER BY fecha DESC";
$cajas = fetchAll($sql);

foreach ($cajas as &$caja) {
  $id_caja = $caja['id'];

  // 💵 EFECTIVO (solo efectivo, sin agente)
  $sqlEfectivo = "SELECT SUM(total_entrada - total_salida) AS total 
                  FROM ventas 
                  WHERE caja_id = :id_caja 
                    AND tipo_pago_id = 1 
                    AND punto_venta_id != 5";
  $efectivo = fetchOne($sqlEfectivo, [':id_caja' => $id_caja]);
  $caja['efectivo'] = $efectivo['total'] ?? 0;

  // 🌐 DIGITAL (no efectivo, no agente)
  $sqlDigital = "SELECT SUM(total_entrada - total_salida) AS total 
                 FROM ventas 
                 WHERE caja_id = :id_caja 
                   AND tipo_pago_id != 1 
                   AND punto_venta_id != 5";
  $digital = fetchOne($sqlDigital, [':id_caja' => $id_caja]);
  $caja['digital'] = $digital['total'] ?? 0;

  // 🏦 AGENTE (todos los pagos, solo punto_venta_id = 5)
  $sqlAgente = "SELECT SUM(total_entrada - total_salida) AS total 
                FROM ventas 
                WHERE caja_id = :id_caja 
                  AND punto_venta_id = 5";
  $agente = fetchOne($sqlAgente, [':id_caja' => $id_caja]);
  $caja['agente'] = $agente['total'] ?? 0;

  // 📊 Calcular saldo general final
  $caja['saldo_final'] = $caja['efectivo'] + $caja['digital'] + $caja['agente'];

  // 🛒 Productos vendidos
  $sqlProductos = "SELECT SUM(cantidad) AS total_vendidos 
                   FROM ventas 
                   WHERE caja_id = :id_caja";
  $p = fetchOne($sqlProductos, [':id_caja' => $id_caja]);
  $caja['productos_vendidos'] = $p['total_vendidos'] ?? 0;
}
unset($caja);
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Historial de Cajas</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    body { 
      font-size: 1.1rem; 
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      min-height: 100vh;
      padding-top: 2rem;
    }
    .container-main {
      background: rgba(255, 255, 255, 0.95);
      border-radius: 20px;
      backdrop-filter: blur(10px);
      box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
      overflow: hidden;
    }
    h3 { 
      font-size: 2.2rem; 
      font-weight: 700;
      background: linear-gradient(135deg, #667eea, #764ba2);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      text-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    .card { 
      border: none;
      border-radius: 20px;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
      overflow: hidden;
    }
    .table th {
      background: linear-gradient(135deg, #667eea, #764ba2);
      color: white;
      border: none;
      font-weight: 600;
      padding: 1.2rem !important;
    }
    .table td {
      padding: 1.2rem !important;
      vertical-align: middle;
      border-color: #f1f3f4;
    }
    .table tbody tr {
      transition: all 0.3s ease;
    }
    .table tbody tr:hover {
      background-color: rgba(102, 126, 234, 0.05);
      transform: translateY(-2px);
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    }
    .btn { 
      font-size: 1rem; 
      border-radius: 12px;
      font-weight: 600;
      padding: 0.6rem 1.2rem;
      transition: all 0.3s ease;
    }
    .badge {
      border-radius: 10px;
      padding: 0.6rem 1rem;
      font-size: 0.9rem;
      font-weight: 600;
    }
    .saldo-final {
      background: linear-gradient(135deg, #28a745, #20c997);
      color: white;
      font-weight: 700;
      border-radius: 12px;
      padding: 0.8rem;
      text-align: center;
      box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
    }
    .efectivo { color: #28a745 !important; font-weight: 700; }
    .digital { color: #17a2b8 !important; font-weight: 700; }
    .agente { color: #ffc107 !important; font-weight: 700; }
    .header-gradient {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      padding: 2rem 0;
      border-radius: 0 0 30px 30px;
      margin-bottom: 2rem;
    }
  </style>
</head>
<body>
  <div class="header-gradient">
    <div class="container">
      <div class="row align-items-center">
        <div class="col">
          <h3 class="mb-0 text-white">
            <i class="bi bi-clock-history me-3"></i> Historial de Cajas
          </h3>
        </div>
        <div class="col-auto">
          <a href="index.php" class="btn btn-light btn-lg">
            <i class="bi bi-arrow-left me-2"></i> Volver al Inicio
          </a>
        </div>
      </div>
    </div>
  </div>

  <div class="container">
    <div class="container-main p-4">
      <div class="card">
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead>
                <tr>
                  <th>#</th>
                  <th>Fecha</th>
                  <th>Saldo Inicial</th>
                  <th>💵 Efectivo</th>
                  <th>🌐 Digital</th>
                  <th>🏦 Agente</th>
                  <th>💰 Saldo Final</th>
                  <th>📦 Vendidos</th>
                  <th>Estado</th>
                  <th>Acciones</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!empty($cajas)): ?>
                  <?php foreach ($cajas as $caja): ?>
                    <tr>
                      <td class="fw-bold text-primary"><?= $caja['id']; ?></td>
                      <td class="fw-medium"><?= date("d/m/Y", strtotime($caja['fecha'])); ?></td>
                      <td class="fw-bold text-dark"><?= number_format($caja['saldo_inicial'], 2); ?></td>

                      <!-- SALDOS POR TIPO -->
                      <td class="efectivo"><?= number_format($caja['efectivo'], 2); ?></td>
                      <td class="digital"><?= number_format($caja['digital'], 2); ?></td>
                      <td class="agente"><?= number_format($caja['agente'], 2); ?></td>

                      <!-- SALDO FINAL -->
                      <td>
                        <div class="saldo-final">
                          <?= number_format($caja['saldo_final'], 2); ?>
                        </div>
                      </td>

                      <td>
                        <span class="badge bg-dark fs-6">
                          <i class="bi bi-box-seam me-1"></i>
                          <?= $caja['productos_vendidos']; ?>
                        </span>
                      </td>
                      <td>
                        <?php if ($caja['estado'] === 'abierta'): ?>
                          <span class="badge bg-success">
                            <i class="bi bi-unlock me-1"></i>Abierta
                          </span>
                        <?php else: ?>
                          <span class="badge bg-secondary">
                            <i class="bi bi-lock me-1"></i>Cerrada
                          </span>
                        <?php endif; ?>
                      </td>
                      <td>
                        <div class="d-flex gap-2">
                          <a href="ver_caja.php?id=<?= $caja['id']; ?>" class="btn btn-primary btn-sm">
                            <i class="bi bi-eye me-1"></i> Ver
                          </a>
                          <?php if ($caja['estado'] === 'abierta'): ?>
                            <a href="cerrar_caja.php?id=<?= $caja['id']; ?>" class="btn btn-danger btn-sm">
                              <i class="bi bi-lock me-1"></i> Cerrar
                            </a>
                          <?php endif; ?>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php else: ?>
                  <tr>
                    <td colspan="10" class="text-center text-muted py-4">
                      <i class="bi bi-inbox display-4 d-block mb-3"></i>
                      No hay cajas registradas
                    </td>
                  </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'db.php'; // tu conexión y helpers

// ============================
// HELPERS (igual que en caja_efectivo.php)
// ============================
if (!function_exists('formatMoney')) {
    function formatMoney($v) { return 'S/. ' . number_format(floatval($v), 2); }
}
if (!function_exists('formatDate')) {
    function formatDate($d) { return date('d/m/Y', strtotime($d)); }
}
if (!function_exists('fetchOne')) {
    function fetchOne($query, $params = []) {
        global $pdo;
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
if (!function_exists('fetchAll')) {
    function fetchAll($query, $params = []) {
        global $pdo;
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// ============================
// OBTENER CAJA ACTIVA (igual que caja_efectivo.php)
// ============================
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

if (!$caja_activa) {
    die("No hay caja abierta.");
}

$caja_id = $caja_activa['id'];
$saldo_inicial = $caja_activa['saldo_inicial'] ?? 0;

// ============================
// OBTENER VENTAS (igual que caja_efectivo.php)
// ============================
$transacciones = fetchAll(
    "SELECT v.*, tp.nombre as tipo_pago, pv.nombre as punto_venta
     FROM ventas v
     JOIN tipos_pago tp ON v.tipo_pago_id = tp.id
     JOIN puntos_venta pv ON v.punto_venta_id = pv.id
     WHERE v.caja_id = ? ORDER BY v.fecha ASC",
    [$caja_id]
);

// ============================
// CÁLCULO SALDO EFECTIVO (100% igual que caja_efectivo.php)
// ============================
$total_entrada_general = 0;
$total_salida_general = 0;

foreach ($transacciones as $t) {
    if ($t['tipo_pago_id'] == 1) { // solo efectivo
        $total_entrada_general += $t['total_entrada'] ?? 0;
        $total_salida_general += $t['total_salida'] ?? 0;
    }
}

$saldo_bd = $saldo_inicial + $total_entrada_general - $total_salida_general;

// ============================
// Conteo actual (hoy)
// ============================
$stmt = $pdo->prepare("
    SELECT denominacion, SUM(cantidad) as cantidad
    FROM conteo_monedas
    WHERE caja_id = ? 
      AND DATE(fecha_registro) = CURDATE()
    GROUP BY denominacion
");
$stmt->execute([$caja_id]);
$conteo_vals = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

if (empty($conteo_vals)) {
    foreach ([200,100,50,20,10,5,2,1,0.5,0.2,0.1] as $d) {
        $conteo_vals[(string)$d] = 0;
    }
}

// Calcular total inicial desde DB para mostrar en HTML directamente
$total_fisico_db = 0;
foreach ($conteo_vals as $den => $cant) {
    $total_fisico_db += floatval($den) * intval($cant);
}
$diferencia_db = $total_fisico_db - $saldo_bd;
$diff_text_db = ($diferencia_db >= 0 ? '+' : '') . number_format($diferencia_db, 2);
if ($diferencia_db == 0) {
    $diff_text_db .= ' ✅ CUADRADO';
    $diff_color_db = 'green';
} else if ($diferencia_db > 0) {
    $diff_text_db .= ' ⚡ SOBRANTE';
    $diff_color_db = 'blue';
} else {
    $diff_text_db .= ' ❌ FALTANTE';
    $diff_color_db = 'red';
}

// ============================
// Historial
// ============================
$historial = $pdo->query("
    SELECT DATE(fecha_registro) as fecha, caja_id
    FROM conteo_monedas
    GROUP BY DATE(fecha_registro), caja_id
    ORDER BY fecha_registro DESC
")->fetchAll(PDO::FETCH_ASSOC);

foreach ($historial as &$h) {
    $det = $pdo->prepare("
        SELECT denominacion, SUM(cantidad) as cantidad 
        FROM conteo_monedas 
        WHERE caja_id = ? AND DATE(fecha_registro) = ?
        GROUP BY denominacion
    ");
    $det->execute([$h['caja_id'], $h['fecha']]);
    $detalle = $det->fetchAll(PDO::FETCH_ASSOC);
    $total = 0;
    $det_str = [];
    foreach ($detalle as $d) {
        $subtotal = $d['denominacion'] * $d['cantidad'];
        $det_str[] = "S/{$d['denominacion']} → {$d['cantidad']} = S/" . number_format($subtotal, 2);
        $total += $subtotal;
    }
    $h['detalle'] = $det_str; // Array para modal
    $h['total_general'] = $total;
    $h['fecha_formateada'] = date('d/m/Y H:i', strtotime($h['fecha'] . ' 00:00:00')); // Ajuste para hora
}
unset($h);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Conteo de Monedas - Sistema Pro 2026</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
  :root {
    --primary: #0d6efd;
    --secondary: #6c757d;
    --success: #198754;
    --info: #0dcaf0;
    --warning: #ffc107;
    --danger: #dc3545;
    --light: #f8f9fa;
    --dark: #212529;
    --bg-gradient: linear-gradient(135deg, #f0f4ff, #e0eaff);
    --card-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
    --transition: all 0.3s ease;
  }
  body { 
    background: var(--bg-gradient); 
    font-family: 'Poppins', sans-serif; 
    color: var(--dark); 
    font-size: 15px;
    line-height: 1.6;
  }
  .card { 
    border-radius: 16px; 
    box-shadow: var(--card-shadow); 
    border: none; 
    overflow: hidden;
    transition: var(--transition);
  }
  .card:hover { box-shadow: 0 12px 32px rgba(0, 0, 0, 0.12); }
  .table { 
    border-radius: 8px; 
    overflow: hidden; 
  }
  .table td, .table th { 
    vertical-align: middle; 
    padding: 12px; 
    border-color: #e9ecef; 
  }
  .table thead th { 
    background: #f1f3f5; 
    font-weight: 600; 
    color: var(--dark); 
    text-transform: uppercase;
    font-size: 13px;
  }
  .table tfoot th { 
    background: #e9ecef; 
    font-weight: 600; 
  }
  .historial-row { 
    transition: var(--transition); 
  }
  .historial-row:hover { 
    background-color: #f8f9fa; 
    transform: translateY(-2px); 
  }
  .verde-saldo { 
    border-left: 6px solid var(--success); 
    background: white; 
    border-radius: 12px; 
    padding: 20px !important;
    transition: var(--transition);
  }
  .morado-conteo { 
    border-left: 6px solid #6610f2; 
    background: white; 
    border-radius: 12px; 
    padding: 20px !important;
    transition: var(--transition);
  }
  .amarillo-diff { 
    border-left: 6px solid var(--warning); 
    background: white; 
    border-radius: 12px; 
    padding: 20px !important;
    transition: var(--transition);
  }
  .text-purple { color: #6610f2; }
  .navbar { 
    box-shadow: 0 4px 12px rgba(0,0,0,0.1); 
    border-bottom: 1px solid rgba(255,255,255,0.1);
  }
  .navbar-brand { font-weight: 700; letter-spacing: 0.5px; }
  .nav-link { 
    font-weight: 500; 
    position: relative; 
    padding-bottom: 8px; 
  }
  .nav-link.active::after { 
    content: ''; 
    position: absolute; 
    bottom: 0; 
    left: 0; 
    width: 100%; 
    height: 2px; 
    background: white; 
  }
  .btn-primary { 
    background: var(--primary); 
    border: none; 
    border-radius: 8px; 
    padding: 10px 20px; 
    font-weight: 500;
    transition: var(--transition);
  }
  .btn-primary:hover { 
    background: #0b5ed7; 
    transform: translateY(-2px); 
  }
  .badge-fecha { 
    background: #0d6efd; 
    font-size: 0.9rem; 
    padding: 6px 12px; 
    border-radius: 20px; 
  }
  .detalle-btn { 
    cursor: pointer; 
    color: var(--primary); 
    text-decoration: none; 
    font-weight: 500;
    transition: var(--transition);
  }
  .detalle-btn:hover { 
    color: #0b5ed7; 
    text-decoration: underline; 
  }
  .modal-content { border-radius: 12px; }
  .modal-header { background: #f8f9fa; border-bottom: none; }
  h4 { font-weight: 700; margin-bottom: 8px; }
  small { font-size: 0.85rem; opacity: 0.8; }
  input.form-control { 
    border-radius: 8px; 
    border: 1px solid #dee2e6; 
    transition: var(--transition); 
  }
  input.form-control:focus { 
    border-color: var(--primary); 
    box-shadow: 0 0 0 0.25rem rgba(13,110,253,0.1); 
  }
</style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-5">
  <div class="container-fluid">
    <a class="navbar-brand text-white" href="#">Sistema conteo computec</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarOpciones">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarOpciones">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <li class="nav-item"><a class="nav-link text-white" href="index.php">Inicio</a></li>
        <li class="nav-item"><a class="nav-link text-white" href="caja_efectivo.php">Caja Efectivo</a></li>
        <li class="nav-item"><a class="nav-link text-white" href="caja_otros.php">Caja Yape y Otros</a></li>
        <li class="nav-item"><a class="nav-link text-white" href="agente.php">Caja Agente</a></li>
        <li class="nav-item"><a class="nav-link text-white" href="salidas.php">Caja Salidas</a></li>
        <li class="nav-item"><a class="nav-link active text-white" href="conteomonedas.php">Conteo Monedas Caja Física</a></li>
      </ul>
    </div>
  </div>
</nav>

<div class="container py-4">
  <!-- Saldo real -->
  <div class="row g-4 mb-5">
    <div class="col-md-4">
      <div class="verde-saldo">
        <strong class="text-success">Saldo Final Esperado (Sistema):</strong><br>
        <h4 class="text-success">S/ <?= number_format($saldo_bd, 2) ?></h4>
        <small>
          Inicial: S/ <?= number_format($saldo_inicial, 2) ?> 
          + Entradas: S/ <?= number_format($total_entrada_general, 2) ?>
          - Salidas: S/ <?= number_format($total_salida_general, 2) ?>
        </small>
      </div>
    </div>
    <div class="col-md-4">
      <div class="morado-conteo">
        <strong class="text-purple">Total Físico Contado:</strong><br>
        <h4 class="text-purple" id="totalFisico"><?= number_format($total_fisico_db, 2) ?></h4>
      </div>
    </div>
    <div class="col-md-4">
      <div class="amarillo-diff">
        <strong class="text-warning">Diferencia:</strong><br>
        <h4 id="estadoDesbalance" style="color: <?= $diff_color_db ?>;"><?= $diff_text_db ?></h4>
      </div>
    </div>
  </div>

  <!-- Conteo -->
  <div class="card mb-5">
    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center py-3 px-4">
      <h5 class="mb-0">Conteo de Monedas / Billetes</h5>
      <button id="btnGuardarConteo" class="btn btn-light btn-sm">Guardar Conteo</button>
    </div>
    <div class="card-body p-4">
      <table class="table table-bordered table-sm text-end align-middle">
        <thead>
          <tr><th class="text-start">Denominación</th><th>Cantidad</th><th>Total (S/)</th></tr>
        </thead>
        <tbody id="tablaConteo">
          <?php
          $denominaciones = [200,100,50,20,10,5,2,1,0.5,0.2,0.1];
          $total_general_html = 0;
          foreach ($denominaciones as $den):
          $cantidad = $conteo_vals[(string)$den] ?? 0;
          $subtotal = $den * $cantidad;
          $total_general_html += $subtotal;
          ?>
          <tr>
            <td class="text-start fw-bold">S/ <?= number_format($den, $den < 1 ? 2 : 0) ?></td>
            <td><input type="number" min="0" value="<?= $cantidad ?>" class="form-control form-control-sm cantidad" data-den="<?= $den ?>"></td>
            <td class="subtotal fw-bold"><?= number_format($subtotal, 2) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr>
            <th colspan="2" class="text-end">Total General:</th>
            <th id="totalGeneral" class="fw-bold"><?= number_format($total_general_html, 2) ?></th>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>

  <!-- Historial -->
  <div class="card">
    <div class="card-header bg-dark text-white py-3 px-4">
      <strong>Historial de Conteos (Uno por Día)</strong>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead class="table-dark">
            <tr>
              <th>Fecha</th>
              <th>Caja</th>
              <th>Detalle</th>
              <th>Total (S/)</th>
            </tr>
          </thead>
          <tbody id="historialBody">
            <?php if (count($historial) > 0): ?>
              <?php foreach ($historial as $index => $h): ?>
                <tr class="historial-row">
                  <td><span class="badge badge-fecha"><?= htmlspecialchars($h['fecha_formateada']) ?></span></td>
                  <td><span class="badge bg-secondary"><?= $h['caja_id'] ?></span></td>
                  <td>
                    <span class="detalle-btn" data-bs-toggle="modal" data-bs-target="#detalleModal<?= $index ?>">Ver Detalle</span>
                  </td>
                  <td class="fw-bold text-success">S/ <?= number_format($h['total_general'], 2) ?></td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr><td colspan="4" class="text-center text-muted py-4">Sin registros aún.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Modales para detalles -->
<?php foreach ($historial as $index => $h): ?>
<div class="modal fade" id="detalleModal<?= $index ?>" tabindex="-1" aria-labelledby="detalleModalLabel<?= $index ?>" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="detalleModalLabel<?= $index ?>">Detalle de Conteo - <?= htmlspecialchars($h['fecha_formateada']) ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <table class="table table-sm">
          <thead>
            <tr><th>Denominación</th><th>Cantidad</th><th>Subtotal (S/)</th></tr>
          </thead>
          <tbody>
            <?php foreach ($h['detalle'] as $det): 
              list($den_str, $cant_str, $sub_str) = explode(' → ', str_replace(' = S/', ' → ', $det));
              $den = trim(str_replace('S/', '', $den_str));
              $cant = trim($cant_str);
              $sub = trim($sub_str);
            ?>
              <tr>
                <td>S/ <?= $den ?></td>
                <td class="text-center"><?= $cant ?></td>
                <td class="text-end">S/ <?= $sub ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr><th colspan="2" class="text-end">Total:</th><th class="text-end">S/ <?= number_format($h['total_general'], 2) ?></th></tr>
          </tfoot>
        </table>
      </div>
    </div>
  </div>
</div>
<?php endforeach; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener("DOMContentLoaded", () => {
  const filas = document.querySelectorAll("#tablaConteo tr");
  const totalEl = document.getElementById("totalGeneral");
  const cajaId = <?= (int)$caja_id ?>;
  const saldoEsperado = <?= $saldo_bd ?>;

  // Cargar desde localStorage si existe
  const storedConteo = localStorage.getItem('conteoTemp');
  if (storedConteo) {
    const conteo = JSON.parse(storedConteo);
    filas.forEach(fila => {
      const input = fila.querySelector(".cantidad");
      if (conteo[input.dataset.den] !== undefined) {
        input.value = conteo[input.dataset.den];
      }
    });
  }

  function recalcular() {
    let total = 0;
    const conteo = {};
    filas.forEach(fila => {
      const input = fila.querySelector(".cantidad");
      const den = parseFloat(input.dataset.den);
      const cant = parseFloat(input.value) || 0;
      const subtotal = den * cant;
      fila.querySelector(".subtotal").textContent = subtotal.toFixed(2);
      total += subtotal;
      conteo[den] = cant;
    });
    totalEl.textContent = total.toFixed(2);
    document.getElementById("totalFisico").textContent = total.toFixed(2);
    const diff = total - saldoEsperado;
    const diffEl = document.getElementById("estadoDesbalance");
    diffEl.textContent = (diff >= 0 ? '+' : '') + diff.toFixed(2);
    if (diff === 0) {
      diffEl.style.color = "green";
      diffEl.textContent += " ✅ CUADRADO";
    } else if (diff > 0) {
      diffEl.style.color = "blue";
      diffEl.textContent += " ⚡ SOBRANTE";
    } else {
      diffEl.style.color = "red";
      diffEl.textContent += " ❌ FALTANTE";
    }
    // Guardar en localStorage
    localStorage.setItem('conteoTemp', JSON.stringify(conteo));
  }

  function agregarFilaHistorial(data) {
    const tbody = document.getElementById("historialBody");
    const hoy = new Date().toLocaleDateString('es-PE', { day: '2-digit', month: '2-digit', year: 'numeric' });
    let filaHoy = Array.from(tbody.querySelectorAll("tr")).find(tr => 
      tr.cells[0].textContent.includes(hoy)
    );

    const detalleArray = Object.entries(data.conteo)
      .filter(([_, c]) => c > 0)
      .map(([d, c]) => `S/${d} → ${c} = S/${(d*c).toFixed(2)}`);
    const total = Object.entries(data.conteo).reduce((t, [d, c]) => t + d*c, 0).toFixed(2);

    const newRowHtml = `
      <td><span class="badge badge-fecha">${new Date().toLocaleString('es-PE').replace(',', '')}</span></td>
      <td><span class="badge bg-secondary">${cajaId}</span></td>
      <td><span class="detalle-btn" data-bs-toggle="modal" data-bs-target="#detalleModalNew">Ver Detalle</span></td>
      <td class="fw-bold text-success">S/ ${total}</td>
    `;

    if (filaHoy) {
      filaHoy.innerHTML = newRowHtml;
    } else {
      const tr = document.createElement("tr");
      tr.className = "historial-row";
      tr.innerHTML = newRowHtml;
      tbody.insertBefore(tr, tbody.firstChild);
    }

    // Agregar modal dinámico si es nuevo
    let modal = document.getElementById('detalleModalNew');
    if (modal) modal.remove();
    const modalHtml = `
      <div class="modal fade" id="detalleModalNew" tabindex="-1" aria-labelledby="detalleModalLabelNew" aria-hidden="true">
        <div class="modal-dialog modal-lg">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title" id="detalleModalLabelNew">Detalle de Conteo - Hoy</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
              <table class="table table-sm">
                <thead><tr><th>Denominación</th><th>Cantidad</th><th>Subtotal (S/)</th></tr></thead>
                <tbody>
                  ${detalleArray.map(det => {
                    const [den_str, cant_str, sub_str] = det.replace(' = S/', ' → ').split(' → ');
                    const den = den_str.replace('S/', '');
                    const cant = cant_str;
                    const sub = sub_str;
                    return `<tr><td>S/ ${den}</td><td class="text-center">${cant}</td><td class="text-end">S/ ${sub}</td></tr>`;
                  }).join('')}
                </tbody>
                <tfoot><tr><th colspan="2" class="text-end">Total:</th><th class="text-end">S/ ${total}</th></tr></tfoot>
              </table>
            </div>
          </div>
        </div>
      </div>
    `;
    document.body.insertAdjacentHTML('beforeend', modalHtml);

    // Limpiar localStorage después de guardar
    localStorage.removeItem('conteoTemp');
  }

  function guardar() {
    const conteo = {};
    filas.forEach(fila => {
      const input = fila.querySelector(".cantidad");
      conteo[input.dataset.den] = parseInt(input.value) || 0;
    });
    fetch("guardar_conteo.php", {
      method: "POST",
      headers: {"Content-Type": "application/json"},
      body: JSON.stringify({ caja_id: cajaId, conteo })
    })
    .then(r => r.json())
    .then(d => {
      if (d.status === "ok") {
        alert("✅ Conteo guardado correctamente");
        agregarFilaHistorial(d);
        recalcular();
      } else {
        alert("❌ Error: " + d.msg);
      }
    });
  }

  filas.forEach(f => f.querySelector(".cantidad").addEventListener("input", recalcular));
  document.getElementById("btnGuardarConteo").addEventListener("click", guardar);
  recalcular();
});
</script>
</body>
</html>
<?php
// crear_pdf.php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/vendor/autoload.php';
require_once 'db.php';
require_once "helpers.php";

// === HELPERS ===
if (!function_exists('formatMoney')) {
    function formatMoney($v) { return 'S/. ' . number_format(floatval($v), 2); }
}

// NUEVA FUNCIÓN: SOLO HORA (H:i:s)
if (!function_exists('formatTime')) {
    function formatTime($d) { 
        return date('H:i:s', strtotime($d)); 
    }
}

if (!function_exists('fetchOne')) {
    function fetchOne($q, $p=[]) { 
        global $pdo; 
        $s = $pdo->prepare($q); 
        $s->execute($p); 
        return $s->fetch(PDO::FETCH_ASSOC); 
    }
}

if (!function_exists('fetchAll')) {
    function fetchAll($q, $p=[]) { 
        global $pdo; 
        $s = $pdo->prepare($q); 
        $s->execute($p); 
        return $s->fetchAll(PDO::FETCH_ASSOC); 
    }
}

// === CAJA ACTIVA ===
$caja_activa = null;
if (isset($_SESSION['caja_id'])) {
    $caja_activa = fetchOne("SELECT * FROM cajas WHERE id = ? AND estado = 'abierta'", [$_SESSION['caja_id']]);
}
if (!$caja_activa) {
    $hoy = date('Y-m-d');
    $caja_activa = fetchOne("SELECT * FROM cajas WHERE fecha = ? AND estado = 'abierta' ORDER BY id DESC LIMIT 1", [$hoy]);
    if ($caja_activa) $_SESSION['caja_id'] = $caja_activa['id'];
}
if (!$caja_activa) die("No hay caja activa.");

$caja_id = $caja_activa['id'];
$saldo_inicial = floatval($caja_activa['saldo_inicial'] ?? 0);

// === VENTAS ===
$transacciones = fetchAll(
    "SELECT v.*, v.tipo_pago_id, v.punto_venta_id, v.medio_pago_id, v.observacion, tp.nombre as tipo_pago, pv.nombre as punto_venta, COALESCE(mp.nombre, '—') as medio_pago
     FROM ventas v
     LEFT JOIN tipos_pago tp ON v.tipo_pago_id = tp.id
     LEFT JOIN puntos_venta pv ON v.punto_venta_id = pv.id
     LEFT JOIN medios_pago mp ON v.medio_pago_id = mp.id
     WHERE v.caja_id = ? ORDER BY v.fecha ASC",
    [$caja_id]
);

// === SALDO EFECTIVO ===
$total_entrada_efectivo = 0;
$total_salida_efectivo = 0;
foreach ($transacciones as $t) {
    if (($t['tipo_pago_id'] ?? 0) == 1) {
        $total_entrada_efectivo += $t['total_entrada'] ?? 0;
        $total_salida_efectivo += $t['total_salida'] ?? 0;
    }
}
$saldo_efectivo = $saldo_inicial + $total_entrada_efectivo - $total_salida_efectivo;

// === SALDO GENERAL ===
$entrada_general = array_sum(array_column($transacciones, 'total_entrada'));
$salida_general = array_sum(array_column($transacciones, 'total_salida'));
$saldo_general = $saldo_inicial + $entrada_general - $salida_general;

// === SALDO GANANCIAS (excluye PV 5) ===
$entrada_ganancias = 0;
$salida_ganancias = 0;
foreach ($transacciones as $t) {
    if (($t['punto_venta_id'] ?? 0) != 5) {
        $entrada_ganancias += $t['total_entrada'] ?? 0;
        $salida_ganancias += $t['total_salida'] ?? 0;
    }
}
$saldo_ganancias = $saldo_inicial + $entrada_ganancias - $salida_ganancias;

// === RESUMEN POR TIPO DE PAGO ===
$resumen_tipo_pago = fetchAll("
    SELECT 
        COALESCE(tp.nombre, 'Sin tipo') as tipo_pago,
        SUM(v.total_entrada) as entrada,
        SUM(v.total_salida) as salida
    FROM ventas v
    LEFT JOIN tipos_pago tp ON v.tipo_pago_id = tp.id
    WHERE v.caja_id = ?
    GROUP BY v.tipo_pago_id, tp.nombre
    ORDER BY entrada DESC
", [$caja_id]);

// === RESUMEN POR PUNTO DE VENTA ===
$resumen_punto_venta = fetchAll("
    SELECT 
        COALESCE(pv.nombre, 'Sin punto') as punto_venta,
        SUM(v.total_entrada) as entrada,
        SUM(v.total_salida) as salida
    FROM ventas v
    LEFT JOIN puntos_venta pv ON v.punto_venta_id = pv.id
    WHERE v.caja_id = ?
    GROUP BY v.punto_venta_id, pv.nombre
    ORDER BY entrada DESC
", [$caja_id]);

// === CONTEO FÍSICO ===
$stmt = $pdo->prepare("
    SELECT denominacion, SUM(cantidad) as cantidad, SUM(cantidad*denominacion) as total
    FROM conteo_monedas 
    WHERE caja_id = ? AND DATE(fecha_registro) = CURDATE()
    GROUP BY denominacion ORDER BY denominacion DESC
");
$stmt->execute([$caja_id]);
$conteo_monedas = $stmt->fetchAll(PDO::FETCH_ASSOC);
$total_fisico = array_sum(array_column($conteo_monedas, 'total')) ?: 0;

// === DESBALANCE ===
$diferencia = $total_fisico - $saldo_efectivo;
$fecha_hora = date('d/m/Y H:i');
ob_start();
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Reporte de Caja - <?= $caja_id ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
    :root {
      --primary: #2c3e50;
      --success: #27ae60;
      --info: #2980b9;
      --warning: #f39c12;
      --danger: #c0392b;
      --light: #f8f9fa;
      --dark: #343a40;
      --border: #dee2e6;
    }
    * { box-sizing: border-box; }
    body { 
      font-family: 'Inter', Arial, sans-serif; 
      font-size: 12px; 
      color: #2c3e50; 
      background: #f4f6f9; 
      margin: 0; 
      padding: 20px; 
      line-height: 1.5;
    }
    .container { 
      max-width: 900px; 
      margin: 0 auto; 
      background: white; 
      border-radius: 10px; 
      overflow: hidden; 
      box-shadow: 0 4px 20px rgba(0,0,0,0.08); 
    }
    .header { 
      background: var(--primary); 
      color: white; 
      padding: 20px; 
      text-align: center; 
    }
    .header h1 { 
      margin: 0; 
      font-size: 22px; 
      font-weight: 600; 
    }
    .meta { 
      font-size: 13px; 
      opacity: 0.9; 
      margin-top: 4px; 
    }
    .btn-pdf { 
      background: #e74c3c; 
      color: white; 
      padding: 9px 20px; 
      border-radius: 6px; 
      text-decoration: none; 
      font-weight: 500; 
      font-size: 13px;
      display: inline-block; 
      margin: 12px 0; 
      transition: all 0.2s;
      box-shadow: 0 2px 8px rgba(231,76,60,0.3);
    }
    .btn-pdf:hover { 
      background: #c0392b; 
      transform: translateY(-1px);
    }
    .resumen { 
      display: grid; 
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); 
      gap: 15px; 
      padding: 20px; 
      background: #f8f9fa; 
    }
    .card { 
      background: white; 
      padding: 16px; 
      border-radius: 8px; 
      text-align: center; 
      font-weight: 500; 
      border: 1px solid var(--border); 
      box-shadow: 0 2px 6px rgba(0,0,0,0.05);
    }
    .card h3 { 
      margin: 8px 0; 
      font-size: 18px; 
      font-weight: 600; 
    }
    .card small { 
      color: #6c757d; 
      font-size: 11px; 
    }
    .verde { border-left: 5px solid var(--success); }
    .azul { border-left: 5px solid var(--info); }
    .morado { border-left: 5px solid #8e44ad; }
    .amarillo { border-left: 5px solid var(--warning); }
    .section { margin: 20px; }
    .section-title { 
      background: var(--dark); 
      color: white; 
      padding: 10px 16px; 
      border-radius: 6px; 
      font-size: 15px; 
      font-weight: 600; 
      margin-bottom: 12px; 
    }
    table { 
      width: 100%; 
      border-collapse: collapse; 
      margin-bottom: 18px; 
      font-size: 11.5px; 
    }
    th, td { 
      border: 1px solid var(--border); 
      padding: 9px 10px; 
      text-align: left; 
    }
    th { 
      background: #e9ecef; 
      font-weight: 600; 
      color: var(--dark); 
      font-size: 11.5px;
    }
    .right { text-align: right; }
    .center { text-align: center; }
    .summary { background: #f1f3f5; font-weight: 600; }
    .footer { 
      text-align: center; 
      padding: 16px; 
      color: #6c757d; 
      font-size: 11px; 
      border-top: 1px solid var(--border); 
      margin-top: 20px; 
    }
  </style>
</head>
<body>
<div class="container">
  <div class="header">
    <h1>Reporte de Caja Nº <?= $caja_id ?></h1>
    <div class="meta">Generado el <?= $fecha_hora ?> | Sistema Profesional</div>
    <a href="?download=1" class="btn-pdf" target="_blank" 
       onclick="setTimeout(() => document.querySelectorAll('.btn-pdf').forEach(b => b.style.display='none'), 500);">
      Guardar PDF
    </a>
  </div>

  <!-- SALDOS PRINCIPALES -->
  <div class="resumen">
    <div class="card verde">
      <div>Saldo Final Efectivo</div>
      <h3 style="color:var(--success);">S/ <?= number_format($saldo_efectivo, 2) ?></h3>
      <small>Solo tipo de pago: Efectivo</small>
    </div>
    <div class="card azul">
      <div>Saldo Final General</div>
      <h3 style="color:var(--info);">S/ <?= number_format($saldo_general, 2) ?></h3>
      <small>Todos los medios de pago</small>
    </div>
    <div class="card morado">
      <div>Saldo Final Ganancias</div>
      <h3 style="color:#8e44ad;">S/ <?= number_format($saldo_ganancias, 2) ?></h3>
      <small>Excluye agente (PV 5)</small>
    </div>
  </div>

  <!-- CONTEO Y DESBALANCE -->
  <div class="resumen">
    <div class="card morado">
      <div>Total Físico Contado</div>
      <h3 style="color:#8e44ad;">S/ <?= number_format($total_fisico, 2) ?></h3>
      <small>Conteo de monedas y billetes</small>
    </div>
    <div class="card amarillo">
      <div>Desbalance</div>
      <h3 style="color:#d35400;">
        <?= $diferencia >= 0 ? '+' : '' ?><?= number_format($diferencia, 2) ?>
        <br><small style="font-weight:600;">
          <?= $diferencia == 0 ? 'CUADRADO' : ($diferencia > 0 ? 'SOBRANTE' : 'FALTANTE') ?>
        </small>
      </h3>
    </div>
  </div>

  <!-- CONTEO FÍSICO -->
  <div class="section">
    <div class="section-title">Conteo Físico de Efectivo</div>
    <table>
      <thead>
        <tr><th>Denominación</th><th class="center">Cant.</th><th class="right">Total</th></tr>
      </thead>
      <tbody>
        <?php foreach ($conteo_monedas as $c): ?>
          <tr>
            <td>S/ <?= number_format($c['denominacion'], $c['denominacion'] < 1 ? 2 : 0) ?></td>
            <td class="center"><?= $c['cantidad'] ?></td>
            <td class="right">S/ <?= number_format($c['total'], 2) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (empty($conteo_monedas)): ?>
          <tr><td colspan="3" class="center">Sin conteo registrado</td></tr>
        <?php endif; ?>
        <tr class="summary">
          <td colspan="2" class="right"><strong>TOTAL CONTADO</strong></td>
          <td class="right"><strong>S/ <?= number_format($total_fisico, 2) ?></strong></td>
        </tr>
      </tbody>
    </table>
  </div>

  <!-- RESUMEN POR TIPO DE PAGO -->
  <div class="section">
    <div class="section-title">Resumen por Tipo de Pago</div>
    <table>
      <thead>
        <tr><th>Tipo de Pago</th><th class="right">Entrada</th><th class="right">Salida</th><th class="right">Neto</th></tr>
      </thead>
      <tbody>
        <?php foreach ($resumen_tipo_pago as $r): 
          $neto = ($r['entrada'] ?? 0) - ($r['salida'] ?? 0);
        ?>
          <tr>
            <td><?= htmlspecialchars($r['tipo_pago']) ?></td>
            <td class="right"><?= formatMoney($r['entrada']) ?></td>
            <td class="right"><?= formatMoney($r['salida']) ?></td>
            <td class="right" style="font-weight:600; color:<?= $neto >= 0 ? '#27ae60' : '#c0392b' ?>;">
              <?= formatMoney($neto) ?>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (empty($resumen_tipo_pago)): ?>
          <tr><td colspan="4" class="center">Sin datos</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- RESUMEN POR PUNTO DE VENTA -->
  <div class="section">
    <div class="section-title">Resumen por Punto de Venta</div>
    <table>
      <thead>
        <tr><th>Punto de Venta</th><th class="right">Entrada</th><th class="right">Salida</th><th class="right">Neto</th></tr>
      </thead>
      <tbody>
        <?php foreach ($resumen_punto_venta as $r): 
          $neto = ($r['entrada'] ?? 0) - ($r['salida'] ?? 0);
        ?>
          <tr>
            <td><?= htmlspecialchars($r['punto_venta']) ?></td>
            <td class="right"><?= formatMoney($r['entrada']) ?></td>
            <td class="right"><?= formatMoney($r['salida']) ?></td>
            <td class="right" style="font-weight:600; color:<?= $neto >= 0 ? '#27ae60' : '#c0392b' ?>;">
              <?= formatMoney($neto) ?>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (empty($resumen_punto_venta)): ?>
          <tr><td colspan="4" class="center">Sin datos</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- VENTAS DETALLADAS -->
  <div class="section">
    <div class="section-title">Ventas del Día</div>
    <table>
      <thead>
        <tr>
          <th>Descripción</th>
          <th class="center">Cant.</th>
          <th class="right">Entrada</th>
          <th class="right">Salida</th>
          <th>Pago</th>
          <th>Punto</th>
          <th>Medio de Pago</th>
          <th>Observación</th>
          <th>Hora</th> <!-- CAMBIADO DE "Fecha" A "Hora" -->
        </tr>
      </thead>
      <tbody>
        <?php foreach ($transacciones as $v): ?>
          <tr>
            <td><?= htmlspecialchars($v['descripcion'] ?? '-') ?></td>
            <td class="center"><?= $v['cantidad'] ?? 0 ?></td>
            <td class="right"><?= formatMoney($v['total_entrada'] ?? 0) ?></td>
            <td class="right"><?= formatMoney($v['total_salida'] ?? 0) ?></td>
            <td><?= htmlspecialchars($v['tipo_pago'] ?? '-') ?></td>
            <td><?= htmlspecialchars($v['punto_venta'] ?? '-') ?></td>
            <td><?= htmlspecialchars($v['medio_pago'] ?? '-') ?></td>
            <td><?= htmlspecialchars($v['observacion'] ?? '-') ?></td>
            <!-- SOLO HORA: H:i:s -->
            <td><?= formatTime($v['fecha']) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (empty($transacciones)): ?>
          <tr><td colspan="9" class="center">Sin ventas registradas</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <div class="footer">
    <strong>Sistema de Caja Profesional</strong><br>
    Reporte generado automáticamente
  </div>
</div>
</body>
</html>
<?php
$html = ob_get_clean();

// === GENERAR PDF ===
if (isset($_GET['download']) && $_GET['download'] == '1') {
    $dompdf = new \Dompdf\Dompdf([
        'isHtml5ParserEnabled' => true,
        'isRemoteEnabled' => true
    ]);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->loadHtml($html);
    $dompdf->render();

    $output = $dompdf->output();
    $filename = "Reporte_Caja_{$caja_id}_" . date('Ymd_His') . ".pdf";
    $archivos_dir = __DIR__ . '/archivos/';

    if (!is_dir($archivos_dir)) {
        mkdir($archivos_dir, 0755, true);
    }

    file_put_contents($archivos_dir . $filename, $output);

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo $output;
    exit;
}

echo $html;
?>
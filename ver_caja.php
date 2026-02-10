<?php
require_once "db.php";
require_once "helpers.php";

$id = $_GET['id'] ?? 0;
$caja = fetchOne("SELECT * FROM cajas WHERE id = ?", [$id]);
if (!$caja) die("Caja no encontrada");

$ventas = fetchAll("
    SELECT v.*, tp.nombre as tipo_pago_nombre, pv.nombre as punto_venta_nombre 
    FROM ventas v
    LEFT JOIN tipos_pago tp ON v.tipo_pago_id = tp.id
    LEFT JOIN puntos_venta pv ON v.punto_venta_id = pv.id
    WHERE v.caja_id = ? 
    ORDER BY v.fecha DESC
", [$id]);

// === CÁLCULOS CORRECTOS ===
$saldo_inicial = floatval($caja['saldo_inicial']);
$saldo_final_registrado = floatval($caja['saldo_final']);

$ganancia_general = 0;
$efectivo_neto = 0;
$ganancia_operacional = 0;

foreach ($ventas as $v) {
    $entrada = floatval($v['total_entrada'] ?? 0);
    $salida  = floatval($v['total_salida'] ?? 0);
    $neto    = $entrada - $salida;

    $tipo_pago_id = $v['tipo_pago_id'];
    $punto_venta_id = $v['punto_venta_id'];

    $ganancia_general += $neto;

    if ($tipo_pago_id == 1) {
        $efectivo_neto += $neto;
    }

    if ($punto_venta_id != 5) {
        $ganancia_operacional += $neto;
    }
}

$saldo_final_calculado = $saldo_inicial + $ganancia_general;
$diferencia = $saldo_final_registrado - $saldo_final_calculado;
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Caja #<?= $id ?> - <?= $caja['estado'] == 'abierta' ? 'ABIERTA' : 'CERRADA' ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        .card-saldo { border-radius: 15px; color: white; padding: 1.2rem; text-align: center; box-shadow: 0 4px 15px rgba(0,0,0,0.15); }
        .bg-inicial { background: linear-gradient(135deg, #343a40, #495057); }
        .bg-efectivo { background: linear-gradient(135deg, #198754, #157347); }
        .bg-operacional { background: linear-gradient(135deg, #0d6efd, #0b5ed7); }
        .bg-general { background: linear-gradient(135deg, #6f42c1, #5a32a3); }
        .badge-tipo { font-size: 0.75rem; padding: 0.35em 0.65em; }
        .saldo-acumulado { font-weight: bold; color: #212529; }
    </style>
</head>
<body class="bg-light">
<div class="container py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="text-primary mb-1">Caja #<?= $id ?></h2>
            <p class="mb-0 text-muted">
                <span class="badge <?= $caja['estado'] == 'abierta' ? 'bg-success' : 'bg-secondary' ?>">
                    <?= strtoupper($caja['estado']) ?>
                </span>
                &bull; <?= formatDate($caja['fecha']) ?>
            </p>
        </div>
        <a href="historial_cajas.php" class="btn btn-outline-secondary">Volver</a>
    </div>

    <!-- RESUMEN -->
    <div class="row g-3 mb-4">
        <div class="col-md-3 col-6">
            <div class="card-saldo bg-inicial">
                <h5>Saldo Inicial</h5>
                <h3><?= formatMoney($saldo_inicial) ?></h3>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card-saldo bg-efectivo">
                <h5>Efectivo</h5>
                <h3><?= formatMoney($efectivo_neto) ?></h3>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card-saldo bg-operacional">
                <h5>Operacional</h5>
                <h3><?= formatMoney($ganancia_operacional) ?></h3>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card-saldo bg-general">
                <h5>General</h5>
                <h3><?= formatMoney($ganancia_general) ?></h3>
            </div>
        </div>
    </div>

    <!-- TRANSACCIONES -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-primary text-white d-flex justify-content-between">
            <h5 class="mb-0">Transacciones</h5>
            <small><?= count($ventas) ?> registro(s)</small>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Hora</th>
                        <th>Descripción</th>
                        <th>Tipo</th>
                        <th>Punto</th>
                        <th>Entrada</th>
                        <th>Salida</th>
                        <th>Neto</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $acumulado = $saldo_inicial;
                    foreach ($ventas as $v): 
                        $entrada = floatval($v['total_entrada']);
                        $salida = floatval($v['total_salida']);
                        $neto = $entrada - $salida;
                        $acumulado += $neto;
                    ?>
                    <tr>
                        <td><?= formatTime($v['fecha']) ?></td>
                        <td><?= htmlspecialchars($v['descripcion']) ?></td>
                        <td><span class="badge bg-success"><?= htmlspecialchars($v['tipo_pago_nombre']) ?></span></td>
                        <td><span class="badge bg-secondary"><?= htmlspecialchars($v['punto_venta_nombre']) ?></span></td>
                        <td class="text-success"><?= formatMoney($entrada) ?></td>
                        <td class="text-danger"><?= formatMoney($salida) ?></td>
                        <td class="fw-bold <?= $neto >= 0 ? 'text-success' : 'text-danger' ?>"><?= formatMoney($neto) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="table-light fw-bold">
                    <tr>
                        <td colspan="6" class="text-end">SALDO FINAL:</td>
                        <td class="text-end"><?= formatMoney($saldo_final_calculado) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <!-- DIFERENCIA -->
    <div class="text-center">
        <div class="d-inline-block p-3 rounded <?= $diferencia == 0 ? 'bg-success' : 'bg-danger' ?> text-white">
            <h5 class="mb-0">
                Diferencia: <?= formatMoney($diferencia) ?>
                <?= $diferencia == 0 ? 'Perfecto' : 'Revisar' ?>
            </h5>
        </div>
    </div>

    <div class="text-end mt-4">
        <button class="btn btn-danger me-2" data-bs-toggle="modal" data-bs-target="#pdfModal">
            Generar PDF
        </button>
        <a href="historial_cajas.php" class="btn btn-secondary">Historial</a>
    </div>
</div>

<!-- MODAL PDF -->
<div class="modal fade" id="pdfModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <form action="generar_pdf.php" method="POST" target="_blank">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">PDF Seguro</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="caja_id" value="<?= $id ?>">
                    <div class="mb-3">
                        <label class="form-label">Contraseña (opcional)</label>
                        <input type="password" name="password" class="form-control" placeholder="Sin contraseña = público">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-danger w-100">Descargar</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
// caja_agente.php
if (session_status() === PHP_SESSION_NONE) session_start();

// CORREGIDO: Ruta correcta desde la carpeta raíz
require_once './db.php';  // <-- CAMBIADO DE '../db.php' A './db.php'

if (!function_exists('formatMoney')) {
    function formatMoney($v) { return 'S/. ' . number_format(floatval($v), 2); }
}

$caja_activa = null;
if (isset($_SESSION['caja_id'])) {
    $caja_activa = fetchOne("SELECT * FROM cajas WHERE id = ? AND estado = 'abierta'", [$_SESSION['caja_id']]);
}
if (!$caja_activa) {
    $hoy = date('Y-m-d');
    $caja_activa = fetchOne("SELECT * FROM cajas WHERE fecha = ? AND estado = 'abierta' ORDER BY id DESC LIMIT 1", [$hoy]);
    if (!$caja_activa) $caja_activa = fetchOne("SELECT * FROM cajas WHERE estado = 'abierta' ORDER BY id DESC LIMIT 1");
    if ($caja_activa) $_SESSION['caja_id'] = $caja_activa['id'];
}

// FILTRO: Punto de venta Agente (id=5 o nombre)
$transacciones = $caja_activa ? fetchAll("
    SELECT v.*, pv.nombre as punto_venta, pv.id as punto_venta_id
    FROM ventas v
    JOIN puntos_venta pv ON v.punto_venta_id = pv.id
    WHERE v.caja_id = ? AND (pv.id = 5 OR pv.nombre LIKE '%Agente%')
    ORDER BY v.fecha ASC
", [$caja_activa['id']]) : [];

$total_entrada = 0; $total_salida = 0;
foreach ($transacciones as $t) {
    $total_entrada += $t['total_entrada'];
    $total_salida += $t['total_salida'];
}
$saldo_inicial = $caja_activa['saldo_inicial'] ?? 0;
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Caja Agente - Sistema POS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f4f6f9; }
        .glass-card { background: rgba(255,255,255,0.95); border-radius: 16px; box-shadow: 0 8px 32px rgba(0,0,0,0.1); backdrop-filter: blur(10px); }
        .stats-card { background: linear-gradient(135deg, #ef4444, #f87171); color: white; border-radius: 16px; }
        .stats-card.danger { background: linear-gradient(135deg, #dc2626, #f87171); }
        .floating-btn { 
            position: fixed; bottom: 2rem; right: 2rem; width: 60px; height: 60px; 
            border-radius: 50%; background: #ef4444; color: white; 
            display: flex; align-items: center; justify-content: center; 
            box-shadow: 0 4px 20px rgba(239,68,68,0.4); text-decoration: none; 
        }
        .floating-btn:hover { transform: scale(1.1); color: white; }
        .indicator { width: 4px; height: 100%; position: absolute; left: 0; top: 0; background: #ef4444; border-radius: 4px 0 0 4px; }
        .badge-agente { background: #ef4444; color: white; }
    </style>
</head>
<body>
    <!-- NAV -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php">
                <i class="bi bi-person-badge me-2"></i>Sistema POS
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="index.php"><i class="bi bi-house"></i> Inicio</a>
                <div class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle active" data-bs-toggle="dropdown">
                        <i class="bi bi-building"></i> Puntos de Venta
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="cajas_tipo_de_pago/caja_golosinas.php">Golosinas</a></li>
                        <li><a class="dropdown-item" href="cajas_tipo_de_pago/caja_libreria.php">Librería</a></li>
                        <li><a class="dropdown-item" href="cajas_tipo_de_pago/caja_accesorios.php">Accesorios</a></li>
                        <li><a class="dropdown-item" href="cajas_tipo_de_pago/caja_comision.php">Comisión</a></li>
                        <li><a class="dropdown-item" href="cajas_tipo_de_pago/caja_tecnico.php">Técnico</a></li>
                        <li><a class="dropdown-item active" href="caja_agente.php">Agente</a></li>
                        <li><a class="dropdown-item" href="cajas_tipo_de_pago/caja_otros_pv.php">Otros</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 fw-bold text-dark mb-1">
                    <i class="bi bi-person-badge me-2 text-danger"></i>Caja Agente
                </h1>
                <p class="text-muted">Transacciones del punto de venta Agente (ID: 5)</p>
            </div>
            <div class="text-end">
                <small class="text-muted">Caja #<?= $caja_activa['id'] ?? 'N/A' ?></small>
            </div>
        </div>

        <!-- Stats -->
        <div class="row mb-4 g-3">
            <div class="col-md-3">
                <div class="stats-card p-4">
                    <h6 class="mb-2 opacity-75">SALDO INICIAL</h6>
                    <h4 class="fw-bold mb-0"><?= formatMoney($saldo_inicial) ?></h4>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card p-4">
                    <h6 class="mb-2 opacity-75">ENTRADAS</h6>
                    <h4 class="fw-bold mb-0"><?= formatMoney($total_entrada) ?></h4>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card danger p-4">
                    <h6 class="mb-2 opacity-75">SALIDAS</h6>
                    <h4 class="fw-bold mb-0"><?= formatMoney($total_salida) ?></h4>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card p-4">
                    <h6 class="mb-2 opacity-75">SALDO FINAL</h6>
                    <h4 class="fw-bold mb-0"><?= formatMoney($saldo_inicial + $total_entrada - $total_salida) ?></h4>
                </div>
            </div>
        </div>

        <!-- Tabla -->
        <div class="glass-card p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-bold mb-0"><i class="bi bi-list-ul me-2"></i>Transacciones del Agente</h5>
                <span class="badge badge-agente"><?= count($transacciones) ?> registros</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Hora</th>
                            <th>Descripción</th>
                            <th class="text-end">Cant.</th>
                            <th class="text-end">P. Unit</th>
                            <th class="text-end">Entrada</th>
                            <th class="text-end">Salida</th>
                            <th class="text-end">Saldo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($transacciones)): ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">
                                    <i class="bi bi-person-badge display-4 d-block mb-2"></i>
                                    No hay transacciones del Agente
                                </td>
                            </tr>
                        <?php else: 
                            $saldo_acum = $saldo_inicial;
                            foreach ($transacciones as $t): 
                                $saldo_acum += $t['total_entrada'] - $t['total_salida'];
                                $p_unit = $t['cantidad'] > 0 ? $t['total_entrada'] / $t['cantidad'] : 0;
                        ?>
                            <tr style="position: relative;">
                                <div class="indicator"></div>
                                <td><small class="text-muted"><?= date('H:i', strtotime($t['fecha'])) ?></small></td>
                                <td><?= htmlspecialchars($t['descripcion']) ?></td>
                                <td class="text-end"><?= $t['cantidad'] ?></td>
                                <td class="text-end"><?= number_format($p_unit, 2) ?></td>
                                <td class="text-end text-success fw-bold"><?= formatMoney($t['total_entrada']) ?></td>
                                <td class="text-end text-danger fw-bold"><?= formatMoney($t['total_salida']) ?></td>
                                <td class="text-end fw-bold"><?= formatMoney($saldo_acum) ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Botón flotante -->
    <a href="index.php" class="floating-btn">
        <i class="bi bi-house fs-5"></i>
    </a>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
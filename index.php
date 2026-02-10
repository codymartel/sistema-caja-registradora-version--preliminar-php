<?php
// index.php (versión mejorada con diseño premium)
// ============================================
if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'db.php';
require_once "helpers.php";
// Helpers
if (!function_exists('formatMoney')) {
    function formatMoney($v) { return 'S/. ' . number_format(floatval($v), 2); }
}
if (!function_exists('formatDate')) {
    function formatDate($d) { return date('d/m/Y', strtotime($d)); }
}
// Mensajes de sesión
if (isset($_SESSION['mensaje'])) {
    $mensaje = $_SESSION['mensaje'];
    $tipoMensaje = $_SESSION['tipoMensaje'];
    unset($_SESSION['mensaje']);
    unset($_SESSION['tipoMensaje']);
}
// Variables y lógica
$pageTitle = 'Dashboard - Sistema de Caja';
$mensaje = '';
$tipoMensaje = 'success';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['abrir_caja'])) {
        $saldo_inicial = floatval($_POST['saldo_inicial']);
        $fecha = date('Y-m-d');
        $cajaExiste = fetchOne("SELECT id FROM cajas WHERE fecha = ? AND estado = 'abierta'", [$fecha]);
        if ($cajaExiste) {
            $mensaje = "Ya existe una caja abierta para hoy";
            $tipoMensaje = 'warning';
        } else {
            query("INSERT INTO cajas (fecha, saldo_inicial, estado) VALUES (?, ?, 'abierta')", [$fecha, $saldo_inicial]);
            if (isset($pdo)) $_SESSION['caja_id'] = $pdo->lastInsertId();
            $mensaje = "Caja abierta correctamente con " . formatMoney($saldo_inicial);
            $tipoMensaje = 'success';
        }
    }
    if (isset($_POST['cerrar_caja'])) {
        $caja_id = $_SESSION['caja_id'] ?? null;
        if ($caja_id) {
            $caja = fetchOne("SELECT * FROM cajas WHERE id = ? AND estado = 'abierta'", [$caja_id]);
            if ($caja) {
                $totales = fetchOne("SELECT SUM(saldo) as saldo_final FROM ventas WHERE caja_id = ?", [$caja_id]);
                $saldo_final = $totales['saldo_final'] ?? 0;
                $efectivo = fetchOne("SELECT SUM(saldo) as total FROM ventas WHERE caja_id = ? AND tipo_pago_id = 1", [$caja_id]);
                $entrada_efectivo = $efectivo['total'] ?? 0;
                $saldo_final_efectivo = $caja['saldo_inicial'] + $entrada_efectivo;
                query("INSERT INTO saldo_final_efectivo (caja_id, fecha_cierre, saldo_inicial, entrada_efectivo, saldo_final) VALUES (?, NOW(), ?, ?, ?)", [$caja_id, $caja['saldo_inicial'], $entrada_efectivo, $saldo_final_efectivo]);
                query("UPDATE cajas SET estado = 'cerrada', saldo_final = ?, closed_at = NOW() WHERE id = ?", [$saldo_final, $caja_id]);
                unset($_SESSION['caja_id']);
           
                $_SESSION['mensaje'] = "Caja cerrada correctamente. Saldo final: " . formatMoney($saldo_final);
                $_SESSION['tipoMensaje'] = 'success';
                header("Location: index.php");
                exit;
            } else {
                $mensaje = "La caja ya está cerrada o no existe.";
                $tipoMensaje = 'warning';
            }
        }
    }
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
    if ($caja_activa) {
        $_SESSION['caja_id'] = $caja_activa['id'];
    }
}
// Estadísticas
$stats = ['total_entrada'=>0,'total_salida'=>0,'num_transacciones'=>0,'saldo_total'=>0];
if ($caja_activa) {
    $stats = fetchOne(
        "SELECT
            COUNT(*) as num_transacciones,
            COALESCE(SUM(total_entrada), 0) as total_entrada,
            COALESCE(SUM(total_salida), 0) as total_salida,
            COALESCE(SUM(saldo), 0) as saldo_total
         FROM ventas WHERE caja_id = ?",
        [$caja_activa['id']]
    ) ?: $stats;
}
$saldo_efectivo = $caja_activa ? ($caja_activa['saldo_inicial'] + $stats['saldo_total']) : 0;
$totales_tipo_pago = $caja_activa ? fetchAll(
    "SELECT tp.nombre, COALESCE(SUM(v.saldo),0) as saldo
     FROM tipos_pago tp
     LEFT JOIN ventas v ON tp.id = v.tipo_pago_id AND v.caja_id = ?
     WHERE tp.activo = 1
     GROUP BY tp.id, tp.nombre
     ORDER BY tp.nombre", [$caja_activa['id']]) : [];
$totales_punto_venta = $caja_activa ? fetchAll(
    "SELECT pv.nombre, COALESCE(SUM(v.saldo),0) as saldo
     FROM puntos_venta pv
     LEFT JOIN ventas v ON pv.id = v.punto_venta_id AND v.caja_id = ?
     WHERE pv.activo = 1
     GROUP BY pv.id, pv.nombre
     ORDER BY pv.nombre", [$caja_activa['id']]) : [];
$transacciones = $caja_activa ? fetchAll(
    "SELECT v.*, tp.nombre as tipo_pago, pv.nombre as punto_venta
     FROM ventas v
     JOIN tipos_pago tp ON v.tipo_pago_id = tp.id
     JOIN puntos_venta pv ON v.punto_venta_id = pv.id
     WHERE v.caja_id = ?
     ORDER BY v.fecha ASC
     LIMIT 200000", [$caja_activa['id']]) : [];
// Datos para selects
$tipos_pago = fetchAll("SELECT * FROM tipos_pago WHERE activo = 1 ORDER BY nombre");
$puntos_venta = fetchAll("SELECT * FROM puntos_venta WHERE activo = 1 ORDER BY nombre");
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title><?php echo htmlspecialchars($pageTitle); ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Bootstrap Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    :root {
      --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      --success-gradient: linear-gradient(135deg, #4ecdc4 0%, #44a08d 100%);
      --danger-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
      --warning-gradient: linear-gradient(135deg, #f6d365 0%, #fda085 100%);
      --info-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
      --dark-gradient: linear-gradient(135deg, #434343 0%, #000000 100%);
    }
    body {
      background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
      min-height: 100vh;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }
    .glass-card {
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(10px);
      border-radius: 20px;
      border: 1px solid rgba(255, 255, 255, 0.2);
      box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
      transition: all 0.3s ease;
    }
    .glass-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 12px 40px rgba(0, 0, 0, 0.15);
    }
    .gradient-primary { background: var(--primary-gradient); }
    .gradient-success { background: var(--success-gradient); }
    .gradient-danger { background: var(--danger-gradient); }
    .gradient-warning { background: var(--warning-gradient); }
    .gradient-info { background: var(--info-gradient); }
    .gradient-dark { background: var(--dark-gradient); }
    .stat-card {
      border-radius: 16px;
      overflow: hidden;
      color: white;
      transition: all 0.3s ease;
    }
    .stat-card:hover {
      transform: scale(1.02);
      box-shadow: 0 10px 25px rgba(0,0,0,0.2);
    }
    .stat-icon {
      font-size: 2.5rem;
      opacity: 0.8;
    }
    .logo-elegante {
      width: 180px;
      filter: drop-shadow(0 4px 8px rgba(0,0,0,0.1));
      animation: float 3s ease-in-out infinite;
    }
    @keyframes float {
      0%, 100% { transform: translateY(0px); }
      50% { transform: translateY(-10px); }
    }
    .btn-modern {
      border-radius: 12px;
      padding: 12px 24px;
      font-weight: 600;
      transition: all 0.3s ease;
      border: none;
    }
    .btn-modern:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(0,0,0,0.15);
    }
    .table-modern {
      border-radius: 16px;
      overflow: hidden;
      background: white;
      box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    }
    .table-modern thead {
      background: var(--primary-gradient);
      color: white;
    }
    .nav-tabs-modern .nav-link {
      border-radius: 12px 12px 0 0;
      font-weight: 600;
      border: none;
      margin-right: 5px;
    }
    .nav-tabs-modern .nav-link.active {
      background: var(--primary-gradient);
      color: white;
    }
    .alert-modern {
      border-radius: 16px;
      border: none;
      box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    }
    .modal-modern .modal-content {
      border-radius: 20px;
      border: none;
      box-shadow: 0 20px 60px rgba(0,0,0,0.2);
    }
    .modal-modern .modal-header {
      border-radius: 20px 20px 0 0;
      background: var(--primary-gradient);
      color: white;
    }
    .input-modern {
      border-radius: 12px;
      border: 2px solid #e9ecef;
      padding: 12px 16px;
      transition: all 0.3s ease;
    }
    .input-modern:focus {
      border-color: #667eea;
      box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
    }
    .badge-modern {
      border-radius: 10px;
      padding: 6px 12px;
      font-weight: 600;
    }
    .stat-card {
    padding: 15px !important;
    border-radius: 12px;
    min-height: 120px;
}
.stat-card h2 {
    font-size: 1.4rem;
    margin-top: 5px;
}
.stat-card h5 {
    font-size: 0.95rem;
    margin-bottom: 4px;
}
.stat-icon {
    font-size: 1.6rem;
    opacity: 0.8;
}
.gradient-deepgray {
    background: linear-gradient(135deg, #2c2c2c, #1a1a1a);
    color: #ffffff;
    border: 1px solid #444;
}
  </style>
</head>
<body>
<div class="container-fluid py-4">
  <!-- Logo y Header -->
  <div class="text-center mb-4">
    <img src="logo.png" alt="Logo" class="logo-elegante">
    <h1 class="mt-3 text-dark fw-bold">Sistema de Gestión de Caja</h1>
    <p class="text-muted">Control completo de transacciones y ventas</p>
    <p id="clock" class="text-muted"></p>
  </div>
  <!-- Mensajes -->
  <?php if (!empty($mensaje)): ?>
    <div class="alert alert-modern alert-<?php echo $tipoMensaje;?> alert-dismissible fade show">
      <div class="d-flex align-items-center">
        <i class="bi bi-check-circle-fill me-2 fs-5"></i>
        <span><?php echo $mensaje; ?></span>
      </div>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>
  <!-- 🖤 Barra superior estilo pro -->
<nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm mb-4">
  <div class="container-fluid">
    <!-- Logo / Nombre -->
    <a class="navbar-brand fw-bold text-white" href="index.php">
      💼 Sistema de Caja
    </a>
    <!-- Botón responsive -->
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#menuCaja">
      <span class="navbar-toggler-icon"></span>
    </button>
    <!-- Menú -->
    <div class="collapse navbar-collapse" id="menuCaja">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <!-- INICIO -->
        <li class="nav-item">
          <a class="nav-link text-white" href="index.php">
            <i class="bi bi-house-door"></i> Inicio
          </a>
        </li>
        <!-- CAJA EFECTIVO -->
         <!-- tipo de pago (DESPLEGABLE) -->
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle text-white" href="#" id="puntosVentaDropdown" role="button" data-bs-toggle="dropdown">
            <i class="bi bi-shop"></i> tipos de pago
          </a>
          <ul class="dropdown-menu dropdown-menu-dark">
            <li><a class="dropdown-item" href="cajas_tipo_de_pago/caja_efectivo.php">efectivo</a></li>
            <li><a class="dropdown-item" href="cajas_tipo_de_pago/caja_tarjetas.php">tarjeta</a></li>
            <li><a class="dropdown-item" href="cajas_tipo_de_pago/caja_yape.php">yape</a></li>
            <li><a class="dropdown-item" href="cajas_tipo_de_pago/caja_plin.php">plin</a></li>
            <li><a class="dropdown-item" href="cajas_tipo_de_pago/caja_otros.php">otros</a></li>
            <li><hr class="dropdown-divider"></li>
       
          </ul>
        </li>
        </li>
        <!-- PUNTOS DE VENTA (DESPLEGABLE) -->
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle text-white" href="#" id="puntosVentaDropdown" role="button" data-bs-toggle="dropdown">
            <i class="bi bi-shop"></i> Puntos de Venta
          </a>
          <ul class="dropdown-menu dropdown-menu-dark">
            <li><a class="dropdown-item" href="cajas_por_punto_venta/libreria.php">libreria</a></li>
            <li><a class="dropdown-item" href="cajas_por_punto_venta/golosinas.php">golosinas</a></li>
            <li><a class="dropdown-item" href="cajas_por_punto_venta/comision.php">comision</a></li>
             <li><a class="dropdown-item" href="cajas_por_punto_venta/tecnico.php">tecnico</a></li>
            <li><a class="dropdown-item" href="cajas_por_punto_venta/accesorios.php">accesorios</a></li>
            <li><a class="dropdown-item" href="cajas_por_punto_venta/otros.php">otros</a></li>
         
            <li><hr class="dropdown-divider"></li>
        
          </ul>
        </li>
          <li class="nav-item">
          <a class="nav-link text-white" href="agente.php">
            <i class="bi bi-box-arrow-up"></i>agente
          </a>
        </li>
     
        <!-- SALIDAS DE CAJA -->
        <li class="nav-item">
          <a class="nav-link text-white" href="salidas.php">
            <i class="bi bi-box-arrow-up"></i> Gastos(Salidas Caja)
          </a>
        </li>
        <!-- CONTEO DE MONEDAS -->
        <li class="nav-item">
          <a class="nav-link text-white" href="conteomonedas.php">
            <i class="bi bi-coin"></i> Conteo Monedas
          </a>
        </li>
    
 
      </ul>
    </div>
  </div>
</nav>
 <!-- Bloque completo unido -->
<div class="glass-card p-4 mb-4">
  <div class="row g-4">
    <!-- Control de Caja -->
    <div class="col-lg-6">
      <div class="p-2 h-100">
        <div class="d-flex align-items-center justify-content-between mb-4">
          <h4 class="mb-0 text-primary fw-bold">
            <i class="bi bi-gear-fill me-2"></i>Control de Caja
          </h4>
          <span class="badge bg-primary badge-modern">2025</span>
        </div>
        <?php if (!$caja_activa): ?>
          <!-- SALDO ANTERIOR - VERSIÓN DEFINITIVA PERÚ 2025 -->
<div class="alert alert-info alert-modern d-flex justify-content-between align-items-center mb-4 shadow-sm">
  <div class="d-flex align-items-center">
    <i class="bi bi-wallet2 fs-3 me-3 text-primary"></i>
    <div>
      <strong>Saldo final anterior (efectivo):</strong><br>
      <span id="textoSaldoAnterior" class="fs-4 fw-bold text-success">Cargando...</span>
    </div>
  </div>
  <button id="btnUsarSaldoAnterior" class="btn btn-success btn-modern shadow-sm" disabled>
    <i class="bi bi-arrow-down-circle-fill me-2"></i>Usar Saldo
  </button>
</div>
<script>
// ===== CARGA SALDO ANTERIOR + BOTÓN ÉPICO (USA TU ARCHIVO ACTUAL) =====
document.addEventListener("DOMContentLoaded", function () {
    const textoSaldo = document.getElementById("textoSaldoAnterior");
    const btnUsar = document.getElementById("btnUsarSaldoAnterior");
    const inputSaldoInicial = document.querySelector("input[name='saldo_inicial']");
    fetch("get_saldo_anterior.php") // Este es TU archivo que ya funciona
        .then(res => res.json())
        .then(data => {
            const saldo = parseFloat(data.saldo || 0).toFixed(2);
     
            if (saldo > 0) {
                textoSaldo.innerHTML = `<span class="text-success">S/. ${saldo}</span>`;
                btnUsar.disabled = false;
         
                btnUsar.onclick = function() {
                    inputSaldoInicial.value = saldo.replace(/\..*$/, ''); // sin decimales si quieres, o quita esta línea
                    inputSaldoInicial.value = parseFloat(saldo).toFixed(2); // con decimales (recomendado)
             
                    // Animación épica de confirmación
                    btnUsar.innerHTML = '<i class="bi bi-check-circle-fill"></i> ¡Listo!';
                    btnUsar.classList.remove('btn-success');
                    btnUsar.classList.add('btn-primary');
             
                    setTimeout(() => {
                        btnUsar.innerHTML = '<i class="bi bi-arrow-down-circle-fill me-2"></i>Usar Saldo';
                        btnUsar.classList.remove('btn-primary');
                        btnUsar.classList.add('btn-success');
                    }, 2000);
                };
            } else {
                textoSaldo.innerHTML = '<span class="text-warning">S/. 0.00</span>';
                btnUsar.disabled = true;
                btnUsar.innerHTML = '<i class="bi bi-dash-circle"></i> Sin saldo';
                btnUsar.classList.replace('btn-success', 'btn-secondary');
            }
        })
        .catch(() => {
            textoSaldo.innerHTML = '<span class="text-danger">Error</span>';
            btnUsar.disabled = true;
        });
});
</script>
          <form method="POST" class="needs-validation" novalidate>
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label fw-semibold">💵 Saldo Inicial</label>
                <div class="input-group">
                  <span class="input-group-text bg-light">S/.</span>
                  <input type="number" name="saldo_inicial" class="form-control input-modern" step="0.01" value="20.00" required>
                </div>
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold">📅 Fecha</label>
                <input type="date" name="fecha_apertura" class="form-control input-modern" value="<?php echo date('Y-m-d'); ?>" required>
              </div>
            </div>
            <button type="submit" name="abrir_caja" class="btn btn-success btn-modern w-100 mt-3">
              <i class="bi bi-unlock me-2"></i>Abrir Caja
            </button>
          </form>
        <?php else: ?>
          <div class="alert alert-success alert-modern mb-4">
            <div class="d-flex align-items-center">
              <i class="bi bi-check-circle-fill fs-2 me-3 text-success"></i>
              <div>
                <h5 class="mb-1">Caja Abierta</h5>
                <p class="mb-1">📅 <?php echo formatDate($caja_activa['fecha']); ?></p>
                <p class="mb-0">💰 Inicial: <?php echo formatMoney($caja_activa['saldo_inicial']); ?></p>
              </div>
            </div>
          </div>
          <div class="d-grid gap-2">
     
<!-- BOTÓN CERRAR CAJA ÉPICO 2026 -->
<button type="button" class="btn btn-danger btn-modern btn-lg shadow-lg pulse-animation" id="btnIniciarCierre">
  <i class="bi bi-lock-fill me-2"></i>Cerrar Caja del Día
</button>
<style>
.pulse-animation {
  animation: pulse 2s infinite;
}
@keyframes pulse {
  0% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.7); }
  70% { box-shadow: 0 0 0 15px rgba(220, 53, 69, 0); }
  100% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0); }
}
</style>
<!-- SWEETALERT2 + SONIDO + CIERRE DE CAJA LEGENDARIO -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.10.1/dist/sweetalert2.all.min.js"></script>
<script>
// ======================== CIERRE DE CAJA - VERSIÓN LEGENDARIA PERÚ 2025 ========================
document.getElementById('btnIniciarCierre')?.addEventListener('click', async function () {
    const { value: creds } = await Swal.fire({
        title: '<strong class="text-warning fs-3">AUTENTICACIÓN REQUERIDA</strong>',
        iconHtml: '<i class="bi bi-shield-lock-fill text-warning" style="font-size: 4rem;"></i>',
        html: `
            <div class="text-start p-4 bg-gradient bg-light rounded shadow-sm">
                <div class="alert alert-info small mb-3">
                    <i class="bi bi-info-circle"></i>
                    Credenciales por defecto: <strong>admin</strong> / <strong>password</strong>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Usuario</label>
                    <input id="swal-user" class="form-control form-control-lg" placeholder="admin" value="admin">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Contraseña</label>
                    <input type="password" id="swal-pass" class="form-control form-control-lg" placeholder="••••••••">
                </div>
                <div class="text-center">
                    <a href="#" onclick="Swal.clickCancel(); cambiarPasswordSwal()" class="text-primary fw-bold small">
                        <i class="bi bi-key"></i> Cambiar contraseña
                    </a>
                </div>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: '<i class="bi bi-fingerprint"></i> Verificar Identidad',
        cancelButtonText: 'Cancelar',
        allowOutsideClick: false,
        heightAuto: false,
        customClass: {
            popup: 'border-0 shadow-2xl',
            confirmButton: 'btn btn-warning btn-lg px-5',
            cancelButton: 'btn btn-secondary btn-lg'
        },
        preConfirm: () => {
            const user = document.getElementById('swal-user').value.trim();
            const pass = document.getElementById('swal-pass').value;
            if (!user || !pass) {
                Swal.showValidationMessage('Completa todos los campos');
                return false;
            }
            return { usuario: user, password: pass };
        }
    });
    if (!creds) return;
    Swal.fire({
        title: 'Verificando credenciales...',
        icon: 'info',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading()
    });
    try {
        const res = await fetch('verificar_login.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(creds)
        });
        const data = await res.json();
        if (data.success) {
            await Swal.fire({
                title: 'ACCESO AUTORIZADO',
                icon: 'success',
                html: '<h3 class="text-success fw-bold">¡Bienvenido, administrador!</h3>',
                timer: 1500,
                showConfirmButton: false
            });
            confirmarCierreFinal();
        } else {
            Swal.fire({
                icon: 'error',
                title: 'ACCESO DENEGADO',
                text: data.message || 'Credenciales incorrectas',
                footer: '<small>Intenta de nuevo o cambia la contraseña</small>',
                confirmButtonText: 'Reintentar'
            });
        }
    } catch {
        Swal.fire('Error', 'No se pudo conectar al servidor', 'error');
    }
});
async function confirmarCierreFinal() {
    const { isConfirmed } = await Swal.fire({
        title: '¿CERRAR CAJA DEL DÍA?',
        html: `
            <div class="text-center py-4">
                <i class="bi bi-safe2-fill text-success" style="font-size: 6rem;"></i>
                <h2 class="text-success mt-4 fw-bold">¡TODO LISTO!</h2>
                <p class="lead text-muted">La caja se cerrará permanentemente</p>
                <div class="alert alert-danger mt-4 fs-5">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <strong>¡ACCIÓN IRREVERSIBLE!</strong>
                </div>
            </div>
        `,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: '<i class="bi bi-check-circle-fill"></i> SÍ, CERRAR CAJA',
        cancelButtonText: '<i class="bi bi-x-circle-fill"></i> Cancelar',
        reverseButtons: true,
        customClass: {
            confirmButton: 'btn btn-danger btn-lg px-5 shadow-lg',
            cancelButton: 'btn btn-secondary btn-lg px-4'
        }
    });
    if (isConfirmed) {
        // SONIDO DE ÉXITO + ANIMACIÓN FINAL
        const audio = new Audio('https://assets.mixkit.co/sfx/preview/mixkit-achievement-bell-600.wav');
        audio.play().catch(() => {});
        await Swal.fire({
            title: '¡CAJA CERRADA!',
            html: '<h1 class="text-success">OPERACIÓN EXITOSA</h1><p>La caja del día ha sido cerrada correctamente.</p>',
            icon: 'success',
            timer: 3000,
            showConfirmButton: false,
            allowOutsideClick: false
        });
        // ENVÍO FINAL
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '';
        form.innerHTML = '<input type="hidden" name="cerrar_caja" value="1">';
        document.body.appendChild(form);
        form.submit();
    }
}
// ===== CAMBIO DE CONTRASEÑA PRO =====
async function cambiarPasswordSwal() {
    const { value: formData } = await Swal.fire({
        title: '<strong class="text-primary fs-3">CAMBIAR CONTRASEÑA</strong>',
        iconHtml: '<i class="bi bi-key-fill text-primary" style="font-size: 4rem;"></i>',
        html: `
            <div class="text-start p-4 bg-light rounded shadow-sm">
                <div class="alert alert-warning small mb-3">
                    <i class="bi bi-lock-fill"></i>
                    <strong>Requisitos:</strong> Mínimo 6 caracteres. Usa mayúsculas, números y símbolos para mayor seguridad.
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Usuario</label>
                    <input id="swal-user" class="form-control form-control-lg" placeholder="admin" value="admin" readonly>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Contraseña Actual</label>
                    <input type="password" id="swal-pass-actual" class="form-control form-control-lg" placeholder="••••••••" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Nueva Contraseña</label>
                    <input type="password" id="swal-pass-nueva" class="form-control form-control-lg" placeholder="••••••••" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Repetir Nueva Contraseña</label>
                    <input type="password" id="swal-pass-confirm" class="form-control form-control-lg" placeholder="••••••••" required>
                </div>
                <div class="progress mt-2" style="height: 5px;">
                    <div id="pass-strength" class="progress-bar" role="progressbar" style="width: 0%;"></div>
                </div>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: '<i class="bi bi-save-fill"></i> Cambiar Contraseña',
        cancelButtonText: 'Cancelar',
        allowOutsideClick: false,
        heightAuto: false,
        customClass: {
            popup: 'border-0 shadow-2xl',
            confirmButton: 'btn btn-primary btn-lg px-5',
            cancelButton: 'btn btn-secondary btn-lg'
        },
        didOpen: () => {
            // Fortalecimiento de contraseña en tiempo real
            const nuevaPass = document.getElementById('swal-pass-nueva');
            const strengthBar = document.getElementById('pass-strength');
            nuevaPass.addEventListener('input', () => {
                const val = nuevaPass.value;
                let strength = 0;
                if (val.length >= 6) strength += 25;
                if (val.match(/[A-Z]/)) strength += 25;
                if (val.match(/[0-9]/)) strength += 25;
                if (val.match(/[^A-Za-z0-9]/)) strength += 25;
                strengthBar.style.width = `${strength}%`;
                strengthBar.className = 'progress-bar ' + (
                    strength < 50 ? 'bg-danger' :
                    strength < 75 ? 'bg-warning' : 'bg-success'
                );
            });
        },
        preConfirm: () => {
            const user = document.getElementById('swal-user').value.trim();
            const actual = document.getElementById('swal-pass-actual').value;
            const nueva = document.getElementById('swal-pass-nueva').value;
            const confirm = document.getElementById('swal-pass-confirm').value;
            if (!actual || !nueva || !confirm) {
                Swal.showValidationMessage('Completa todos los campos');
                return false;
            }
            if (nueva !== confirm) {
                Swal.showValidationMessage('Las nuevas contraseñas no coinciden');
                return false;
            }
            if (nueva.length < 6) {
                Swal.showValidationMessage('La nueva contraseña debe tener al menos 6 caracteres');
                return false;
            }
            return { usuario: user, password_actual: actual, password_nueva: nueva };
        }
    });
    if (!formData) return;
    Swal.fire({
        title: 'Actualizando contraseña...',
        icon: 'info',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading()
    });
    try {
        const res = await fetch('cambiar_password.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(formData)
        });
        const data = await res.json();
        if (data.success) {
            const audio = new Audio('https://assets.mixkit.co/sfx/preview/mixkit-instant-win-2050.wav');
            audio.play().catch(() => {});
            await Swal.fire({
                title: '¡ÉXITO!',
                icon: 'success',
                html: '<h3 class="text-success fw-bold">Contraseña actualizada correctamente</h3><p>Usa tu nueva contraseña para iniciar sesión.</p>',
                timer: 3000,
                showConfirmButton: false
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'ERROR',
                text: data.message || 'No se pudo cambiar la contraseña',
                footer: '<small>Verifica los datos e intenta de nuevo</small>'
            });
        }
    } catch {
        Swal.fire('Error de Servidor', 'No se pudo conectar. Intenta más tarde.', 'error');
    }
}
</script>
            <a href="historial_cajas.php" class="btn btn-outline-primary btn-modern">
              <i class="bi bi-clock-history me-2"></i>Ver Historial
            </a>
            <button type="button" class="btn btn-primary btn-modern" data-bs-toggle="modal" data-bs-target="#modalNuevaVenta">
              <i class="bi bi-plus-circle me-2"></i>Nueva Venta
            </button>
          </div>
        <?php endif; ?>
      </div>
    </div>
    <!-- Saldo Anterior + Acciones Rápidas -->
    <div class="col-lg-6">
      <div class="d-flex flex-column gap-4 h-100">
        <!-- Saldo Anterior -->
        <div class="glass-card p-4 flex-fill">
          <div class="stat-card gradient-success p-4 text-center">
            <i class="bi bi-cash-stack stat-icon mb-3"></i>
            <h5 class="fw-bold">Saldo Anterior</h5>
            <h3 id="saldoAnteriorValor" class="fw-bold mb-3">Cargando...</h3>
            <button id="btnUsarSaldoAnterior" class="btn btn-light btn-sm" disabled>
              <i class="bi bi-arrow-down-circle me-1"></i>Usar Saldo
            </button>
          </div>
        </div>
        <!-- Acciones Rápidas -->
        <div class="glass-card p-4">
          <h5 class="fw-bold mb-3 text-primary">
            <i class="bi bi-lightning-charge me-2"></i>Acciones Rápidas
          </h5>
          <div class="row g-2">
            <div class="col-6">
              <a href="listavisualproductos.php" class="btn btn-outline-primary btn-modern w-100">
                <i class="bi bi-box-seam"></i> Productos
              </a>
            </div>
            <div class="col-6">
              <a href="crear_pdf.php" target="_blank" class="btn btn-outline-danger btn-modern w-100">
                <i class="bi bi-file-pdf"></i> PDF
              </a>
            </div>
            <div class="col-6">
              <button id="imprimirSello" class="btn btn-outline-dark btn-modern w-100">
                <i class="bi bi-printer"></i> Imprimir
              </button>
            </div>
            <div class="col-6">
               <button class="btn btn-outline-info btn-modern w-100" onclick="window.location.href='generar_nota.php'">
                <i class="bi bi-ticket-perforated"></i> Boleta
              </button>
            </div>
            <div class="col-6">
              <div class="dropdown">
                <button class="btn btn-outline-secondary btn-modern w-100 dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                  <i class="bi bi-robot"></i> IA
                </button>
                <ul class="dropdown-menu">
                  <li><a class="dropdown-item" href="https://chat.openai.com" target="_blank">ChatGPT</a></li>
                  <li><a class="dropdown-item" href="https://gemini.google.com" target="_blank">Gemini</a></li>
                  <li><a class="dropdown-item" href="https://deepseek.com" target="_blank">DeepSeek</a></li>
                  <li><a class="dropdown-item" href="https://grok.x.ai" target="_blank">Grok</a></li>
                </ul>
              </div>
            </div>
           <div class="col-6">
              <div class="dropdown">
                <button class="btn btn-outline-secondary btn-modern w-100 dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                  <i class="bi bi-robot"></i> IA
                </button>
                <ul class="dropdown-menu">
                  <li><a class="dropdown-item" href="https://chat.openai.com" target="_blank">ChatGPT</a></li>
                  <li><a class="dropdown-item" href="https://gemini.google.com" target="_blank">Gemini</a></li>
                  <li><a class="dropdown-item" href="https://deepseek.com" target="_blank">DeepSeek</a></li>
                  <li><a class="dropdown-item" href="https://grok.x.ai" target="_blank">Grok</a></li>
                </ul>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<!-- JS -->
<script>
document.addEventListener("DOMContentLoaded", function () {
  fetch("get_saldo_anterior.php")
    .then(res => res.json())
    .then(data => {
      const saldo = data.saldo ?? 0;
      document.getElementById("saldoAnteriorValor").textContent = saldo.toFixed(2);
      document.getElementById("btnUsarSaldoAnterior").disabled = false;
    })
    .catch(() => {
      document.getElementById("saldoAnteriorValor").textContent = "Error";
    });
});
</script>
<?php
// === LÓGICA SIN CAMBIOS ===
$saldo_inicial = isset($caja_activa['saldo_inicial']) ? $caja_activa['saldo_inicial'] : 0;
// EFECTIVO
$entrada_efectivo = 0;
$salida_efectivo = 0;
foreach ($transacciones as $t) {
    $tipo = $t['tipo_pago_id'];
    $monto_entrada = $t['total_entrada'] ?? 0;
    $monto_salida = $t['total_salida'] ?? 0;
    if ($tipo == 1) {
        $entrada_efectivo += $monto_entrada;
        $salida_efectivo += $monto_salida;
    }
}
$saldo_efectivo = $saldo_inicial + $entrada_efectivo - $salida_efectivo;
// GENERAL
$entrada_general = 0;
$salida_general = 0;
foreach ($transacciones as $t) {
    $entrada_general += $t['total_entrada'] ?? 0;
    $salida_general += $t['total_salida'] ?? 0;
}
$saldo_general = $saldo_inicial + $entrada_general - $salida_general;
// GANANCIAS (todos los pagos excepto punto_venta_id == 5)
$entrada_ganancias = 0;
$salida_ganancias = 0;
foreach ($transacciones as $t) {
    $pv = $t['punto_venta_id'];
    $entrada = $t['total_entrada'] ?? 0;
    $salida = $t['total_salida'] ?? 0;
    if ($pv != 5) {
        $entrada_ganancias += $entrada;
        $salida_ganancias += $salida;
    }
}
$saldo_ganancias = $saldo_inicial + $entrada_ganancias - $salida_ganancias;
$saldo_neto_hoy = $entrada_ganancias - $salida_ganancias;
?>
<?php
// Obtener medios de pago activos desde la base de datos
$medios_pago_disponibles = $pdo->query("SELECT id, nombre, color FROM medios_pago WHERE activo = 1 ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
$medios_pago_map = [];
foreach($medios_pago_disponibles as $mp){
    $medios_pago_map[$mp['id']] = ['nombre'=>$mp['nombre'], 'color'=>$mp['color']];
}
?>
<!-- ======================= TARJETAS PRINCIPALES ======================= -->
<div class="row g-4">
    <!-- ========================= EFECTIVO ========================= -->
    <div class="col-12">
    <h4 class="fw-bold mt-3 mb-2">Efectivo</h4>
</div>
<div class="col-xl-3 col-md-6">
    <div class="stat-card gradient-deepgray text-center">
        <i class="bi bi-piggy-bank stat-icon"></i>
        <h5 class="fw-bold">Saldo Inicial</h5>
        <h2 class="fw-bold"><?= formatMoney($saldo_inicial) ?></h2>
    </div>
</div>
<div class="col-xl-3 col-md-6">
    <div class="stat-card gradient-success text-center">
        <i class="bi bi-cash stat-icon"></i>
        <h5 class="fw-bold">Entrada Efectivo</h5>
        <h2 class="fw-bold"><?= formatMoney($entrada_efectivo) ?></h2>
    </div>
</div>
<div class="col-xl-3 col-md-6">
    <div class="stat-card gradient-danger text-center">
        <i class="bi bi-box-arrow-right stat-icon"></i>
        <h5 class="fw-bold">Salida Efectivo</h5>
        <h2 class="fw-bold"><?= formatMoney($salida_efectivo) ?></h2>
    </div>
</div>
<div class="col-xl-3 col-md-6">
    <div class="stat-card gradient-info text-center">
        <i class="bi bi-wallet2 stat-icon"></i>
        <h5 class="fw-bold">Saldo Efectivo</h5>
        <h2 class="fw-bold"><?= formatMoney($saldo_efectivo) ?></h2>
    </div>
</div>
</div>
<!-- ======================= TABS PRINCIPALES ======================= -->
<div class="glass-card p-4 mb-4">
    <ul class="nav nav-tabs nav-tabs-modern" id="myTab" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="transacciones-tab" data-bs-toggle="tab"
                data-bs-target="#transacciones" type="button" role="tab">
                <i class="bi bi-list-check me-2"></i>Transacciones
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="estadisticas-tab" data-bs-toggle="tab"
                data-bs-target="#estadisticas" type="button" role="tab">
                <i class="bi bi-bar-chart me-2"></i>Estadísticas
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="agente-tab" data-bs-toggle="tab"
                data-bs-target="#agente" type="button" role="tab">
                <i class="bi bi-person-badge me-2"></i>Vista Agente
            </button>
        </li>
    </ul>


    <!-- ======================= FILTRO TIPO DE PAGO ======================= -->



   <div class="tab-content mt-4" id="myTabContent">
    <!-- ======================= TAB TRANSACCIONES ======================= -->
    <div class="tab-pane fade show active" id="transacciones" role="tabpanel">
        
        <!-- ======================= BARRA DE SELECCIÓN: TIPO DE PAGO (ARRIBA) ======================= -->
        <div class="d-flex justify-content-start mb-3">
            <label for="selector-tipo-pago" class="me-2 fw-bold text-primary">Filtrar por Tipo de Pago:</label>
            <select id="selector-tipo-pago" class="form-select form-select-sm" style="width: 250px;">
                <option value="">Todos los tipos</option>
                <?php
                // Obtener tipos únicos de pago
                $tipos_unicos = [];
                foreach ($transacciones as $t) {
                    if (!isset($tipos_unicos[$t['tipo_pago_id']])) {
                        $tipos_unicos[$t['tipo_pago_id']] = $t['tipo_pago'];
                    }
                }
                foreach ($tipos_unicos as $id => $nombre):
                ?>
                    <option value="<?= $id ?>"><?= htmlspecialchars($nombre) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- ======================= TABLA TRANSACCIONES ======================= -->
        <div class="table-responsive">
            <table class="table table-modern table-hover text-center" id="tabla-transacciones">
                <thead>
                    <tr>
                        <th>Select</th>
                        <th>Hora</th>
                        <th>NÚMERO DE VENTA</th>
                        <th>Cant.</th>
                        <th>Descripción</th>
                        <th>Tipo Pago</th>
                        <th>Punto Venta</th>
                        <th>P. Unit.</th>
                        <th>Entrada</th>
                        <th>Salida</th>
                        <th>MEDIOS PAGO</th>
                        <th>Saldo</th>
                        <th>Observación</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $saldo_acumulado = $saldo_inicial ?? 0;
                    $i = 1;
                    foreach ($transacciones as $t):
                        $entrada = $t['total_entrada'] ?? 0;
                        $salida = $t['total_salida'] ?? 0;
                        $saldo_acumulado += $entrada - $salida;
                        $medio_actual_id = $t['medio_pago_id'] ?? null;
                    ?>
                    <tr data-id="<?= $t['id'] ?>" data-tipo-pago="<?= $t['tipo_pago_id'] ?>">
                        <!-- 1. Select -->
                        <td><input type="checkbox" class="select-venta" value="<?= $t['id'] ?>"></td>

                        <!-- 2. Hora -->
                        <td><small class="text-muted"><?= date('H:i:s', strtotime($t['fecha'])) ?></small></td>

                        <!-- 3. NÚMERO DE VENTA -->
                        <td><strong><?= $i ?></strong></td>

                        <!-- 4. Cant. -->
                        <td class="fw-semibold"><?= number_format($t['cantidad'], 0) ?></td>

                        <!-- 5. Descripción -->
                        <td class="text-truncate" style="max-width: 200px;">
                            <?= htmlspecialchars($t['descripcion']) ?>
                        </td>

                        <!-- 6. Tipo Pago -->
                        <td>
                            <?php
                            $badge = match($t['tipo_pago_id']) {
                                1 => 'bg-success',
                                2 => 'bg-primary',
                                3 => 'bg-info',
                                4 => 'bg-warning',
                                default => 'bg-secondary'
                            };
                            ?>
                            <span class="badge <?= $badge ?> badge-modern tipo-pago-filtro">
                                <?= htmlspecialchars($t['tipo_pago']) ?>
                            </span>
                        </td>

                        <!-- 7. Punto Venta -->
                        <td><small><?= htmlspecialchars($t['punto_venta']) ?></small></td>

                        <!-- 8. P. Unit. -->
                        <td><?= number_format($t['cantidad'] > 0 ? $t['total_entrada'] / $t['cantidad'] : 0, 2) ?></td>

                        <!-- 9. Entrada -->
                        <td class="text-success fw-bold"><?= number_format($entrada, 2) ?></td>

                        <!-- 10. Salida -->
                        <td class="text-danger fw-bold"><?= number_format($salida, 2) ?></td>

                        <!-- 11. MEDIOS PAGO -->
                        <td>
                            <select class="form-select form-select-sm medio-pago-select mx-auto text-center"
                                    style="width: 150px; background-color: <?= $medio_actual_id ? $medios_pago_map[$medio_actual_id]['color'] : '#ffffff' ?>; color:white;"
                                    data-id="<?= $t['id'] ?>"
                                    data-prev="<?= $medio_actual_id ?>">
                                <option value="">Seleccione...</option>
                                <?php foreach ($medios_pago_disponibles as $mp): ?>
                                    <option value="<?= $mp['id'] ?>"
                                            style="background-color: <?= $mp['color'] ?>; color:white;"
                                            <?= $medio_actual_id == $mp['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($mp['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>

                        <!-- 12. Saldo -->
                        <td class="fw-bold"><?= number_format($saldo_acumulado, 2) ?></td>

                        <!-- 13. Observación -->
                        <td>
                            <input
                                type="text"
                                class="form-control form-control-sm observacion-input border-0 shadow-none"
                                value="<?= htmlspecialchars($t['observacion'] ?? '') ?>"
                                data-id="<?= $t['id'] ?>"
                                data-original="<?= htmlspecialchars($t['observacion'] ?? '') ?>"
                                placeholder="Escribe aquí..."
                                style="font-size: 0.85rem; padding: 0.25rem 0.4rem; min-width: 130px;"
                                title="Haz clic para editar">
                            <small class="text-success d-none guardado-msj ms-1">Guardado</small>
                        </td>

                        <!-- 14. Acciones -->
                        <td>
                            <div class="btn-group btn-group-sm">
                                <button type="button" class="btn btn-warning btn-edit-single"
                                        data-id="<?= $t['id'] ?>"
                                        data-descripcion="<?= htmlspecialchars($t['descripcion']) ?>">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button type="button" class="btn btn-danger btn-delete-single"
                                        data-id="<?= $t['id'] ?>"
                                        data-descripcion="<?= htmlspecialchars($t['descripcion']) ?>">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php $i++; endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <!-- ======================= FIN TAB TRANSACCIONES ======================= -->
</div>

<!-- ======================= JAVASCRIPT: TODO UNIFICADO ======================= -->
<script>
document.addEventListener('DOMContentLoaded', function () {

    // === 1. BARRA DE SELECCIÓN: FILTRAR POR TIPO DE PAGO ===
    const selector = document.getElementById('selector-tipo-pago');
    const filas = document.querySelectorAll('#tabla-transacciones tbody tr');

    if (selector) {
        selector.addEventListener('change', function () {
            const seleccionado = this.value;
            filas.forEach(fila => {
                const tipoPago = fila.dataset.tipoPago;
                fila.style.display = (!seleccionado || tipoPago == seleccionado) ? '' : 'none';
            });
        });
    }

    // === 2. MEDIOS DE PAGO (YA FUNCIONA) ===
    document.querySelectorAll('.medio-pago-select').forEach(select => {
        select.addEventListener('change', function () {
            const ventaId = this.dataset.id;
            const nuevoMedioId = this.value;
            const prevValue = this.dataset.prev;
            
            if (!nuevoMedioId) {
                alert('Seleccione un medio de pago válido.');
                this.value = prevValue;
                return;
            }
            
            this.disabled = true;
            fetch('actualizar_medio_pago.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `venta_id=${ventaId}&medio_pago_id=${nuevoMedioId}`
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    this.dataset.prev = nuevoMedioId;
                    const option = this.querySelector(`option[value="${nuevoMedioId}"]`);
                    if (option) this.style.backgroundColor = option.style.backgroundColor;
                } else {
                    alert('Error: ' + data.message);
                    this.value = prevValue;
                }
            })
            .catch(err => {
                console.error(err);
                alert('Error de conexión.');
                this.value = prevValue;
            })
            .finally(() => this.disabled = false);
        });
    });

    // === 3. OBSERVACIÓN: GUARDAR AUTOMÁTICO ===
    document.querySelectorAll('.observacion-input').forEach(input => {
        const original = input.getAttribute('data-original');
        const guardadoMsj = input.parentElement.querySelector('.guardado-msj');

        input.addEventListener('blur', function () {
            const nuevo = this.value.trim();
            const id = this.getAttribute('data-id');
            if (nuevo === original) return;

            fetch('guardar_observacion_venta.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `id=${id}&observacion=${encodeURIComponent(nuevo)}`
            })
            .then(r => {
                if (!r.ok) throw new Error(`HTTP ${r.status}`);
                return r.json();
            })
            .then(data => {
                if (data.success) {
                    input.setAttribute('data-original', nuevo);
                    guardadoMsj.classList.remove('d-none');
                    setTimeout(() => guardadoMsj.classList.add('d-none'), 1500);
                } else {
                    alert('Error: ' + (data.error || 'No se guardó'));
                    input.value = original;
                }
            })
            .catch(err => {
                console.error('Error:', err);
                alert('Error de conexión');
                input.value = original;
            });
        });

        input.addEventListener('keypress', e => {
            if (e.key === 'Enter') input.blur();
        });
    });

    // === 4. TOAST (OPCIONAL) ===
    window.showToast = function(message, type = 'info') {
        const toast = document.createElement('div');
        toast.className = `toast align-items-center text-bg-${type === 'success' ? 'success' : type} border-0`;
        toast.style.cssText = 'position: fixed; bottom: 20px; right: 20px; z-index: 9999;';
        toast.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">${message}</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        `;
        document.body.appendChild(toast);
        new bootstrap.Toast(toast).show();
    };
});
</script>
        <!-- ======================= TAB ESTADÍSTICAS ======================= -->
        <div class="tab-pane fade" id="estadisticas" role="tabpanel">
            <div class="row g-4 mt-3">
                <div class="col-lg-6">
                    <div class="glass-card p-4">
                        <h5 class="fw-bold mb-3 text-primary">
                            <i class="bi bi-credit-card me-2"></i>Por Tipo de Pago
                        </h5>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <tbody>
                                    <?php foreach ($totales_tipo_pago as $tp): ?>
                                        <tr>
                                            <td class="fw-semibold"><?= htmlspecialchars($tp['nombre']) ?></td>
                                            <td class="text-end fw-bold text-success">
                                                <?= formatMoney($tp['saldo']) ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="glass-card p-4">
                        <h5 class="fw-bold mb-3 text-success">
                            <i class="bi bi-shop me-2"></i>Por Punto de Venta
                        </h5>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <tbody>
                                    <?php foreach ($totales_punto_venta as $pv): ?>
                                        <tr>
                                            <td class="fw-semibold"><?= htmlspecialchars($pv['nombre']) ?></td>
                                            <td class="text-end fw-bold text-success">
                                                <?= formatMoney($pv['saldo']) ?>
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
        <!-- Tab Agente -->
      <div class="tab-pane fade" id="agente" role="tabpanel">
        <?php
        $total_entrada_agente = 0;
        $total_salida_agente = 0;
        foreach ($transacciones as $t) {
          if ($t['punto_venta_id'] == 5) {
            $total_entrada_agente += $t['total_entrada'];
            $total_salida_agente += $t['total_salida'];
          }
        }
        $saldo_agente = $total_entrada_agente - $total_salida_agente;
        ?>
  
        <div class="row g-4 mb-4">
          <div class="col-md-3">
            <div class="stat-card gradient-dark p-3 text-center">
              <h6 class="fw-bold">Entrada Agente</h6>
              <h4 class="fw-bold"><?= formatMoney($total_entrada_agente) ?></h4>
            </div>
          </div>
          <div class="col-md-3">
            <div class="stat-card gradient-danger p-3 text-center">
              <h6 class="fw-bold">Salida Agente</h6>
              <h4 class="fw-bold"><?= formatMoney($total_salida_agente) ?></h4>
            </div>
          </div>
          <div class="col-md-3">
            <div class="stat-card gradient-info p-3 text-center">
              <h6 class="fw-bold">Saldo Agente</h6>
              <h4 class="fw-bold"><?= formatMoney($saldo_agente) ?></h4>
            </div>
          </div>
          <div class="col-md-3">
            <div class="stat-card gradient-warning p-3 text-center">
              <h6 class="fw-bold">Total en Caja</h6>
              <h4 class="fw-bold"><?= formatMoney($saldo_inicial + $saldo_agente) ?></h4>
            </div>
          </div>
        </div>
  
        <div class="table-responsive">
          <table class="table table-modern table-hover">
            <thead>
              <tr>
                <th>Hora</th>
                <th>Descripción</th>
                <th>Tipo Pago</th>
                <th class="text-end">Cant.</th>
                <th class="text-end">Entrada</th>
                <th class="text-end">Salida</th>
                <th class="text-end">Saldo</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($transacciones as $t): ?>
                <?php if ($t['punto_venta_id'] == 5): ?>
                  <tr>
                    <td><small><?= date('H:i:s', strtotime($t['fecha'])) ?></small></td>
                    <td><?= htmlspecialchars($t['descripcion']) ?></td>
                    <td><span class="badge bg-secondary badge-modern"><?= $t['tipo_pago'] ?></span></td>
                    <td class="text-end"><?= $t['cantidad'] ?></td>
                    <td class="text-end text-success fw-bold"><?= number_format($t['total_entrada'], 2) ?></td>
                    <td class="text-end text-danger fw-bold"><?= number_format($t['total_salida'], 2) ?></td>
                    <td class="text-end fw-bold"><?= number_format($t['saldo'], 2) ?></td>
                  </tr>
                <?php endif; ?>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
<div class="row g-4">
<!-- ========================= GENERAL ========================= -->
   <div class="col-12">
    <h4 class="fw-bold mt-4 mb-2">General</h4>
</div>
<div class="col-xl-3 col-md-6">
    <div class="stat-card gradient-deepgray text-center">
        <i class="bi bi-piggy-bank stat-icon"></i>
        <h5 class="fw-bold">Saldo Inicial</h5>
        <h2 class="fw-bold"><?= formatMoney($saldo_inicial) ?></h2>
    </div>
</div>
<div class="col-xl-3 col-md-6">
    <div class="stat-card gradient-primary text-center">
        <i class="bi bi-plus-circle stat-icon"></i>
        <h5 class="fw-bold">Entrada General</h5>
        <h2 class="fw-bold"><?= formatMoney($entrada_general) ?></h2>
    </div>
</div>
<div class="col-xl-3 col-md-6">
    <div class="stat-card gradient-warning text-center">
        <i class="bi bi-dash-circle stat-icon"></i>
        <h5 class="fw-bold">Salida General</h5>
        <h2 class="fw-bold"><?= formatMoney($salida_general) ?></h2>
    </div>
</div>
<div class="col-xl-3 col-md-6">
    <div class="stat-card gradient-danger text-center">
        <i class="bi bi-cash-coin stat-icon"></i>
        <h5 class="fw-bold">Saldo Total General</h5>
        <h2 class="fw-bold"><?= formatMoney($saldo_general) ?></h2>
    </div>
</div>
    <!-- ========================= GANANCIAS ========================= -->
    <div class="col-12">
    <h4 class="fw-bold mt-4 mb-2">Ganancias</h4>
</div>
<div class="col-xl-3 col-md-6">
    <div class="stat-card gradient-deepgray text-center">
        <i class="bi bi-piggy-bank stat-icon"></i>
        <h5 class="fw-bold">Saldo Inicial</h5>
        <h2 class="fw-bold"><?= formatMoney($saldo_inicial) ?></h2>
    </div>
</div>
<div class="col-xl-3 col-md-6">
    <div class="stat-card gradient-dark text-center">
        <i class="bi bi-phone stat-icon"></i>
        <h5 class="fw-bold">Entrada Ganancias</h5>
        <h2 class="fw-bold"><?= formatMoney($entrada_ganancias) ?></h2>
    </div>
</div>
<div class="col-xl-3 col-md-6">
    <div class="stat-card gradient-dark text-center">
        <i class="bi bi-file-earmark-x stat-icon"></i>
        <h5 class="fw-bold">Salida Ganancias</h5>
        <h2 class="fw-bold"><?= formatMoney($salida_ganancias) ?></h2>
    </div>
</div>
<div class="col-xl-3 col-md-6">
    <div class="stat-card gradient-dark text-center">
        <i class="bi bi-wallet-fill stat-icon"></i>
        <h5 class="fw-bold">Saldo Ganancias</h5>
        <h2 class="fw-bold"><?= formatMoney($saldo_ganancias) ?></h2>
    </div>
</div>
<div class="col-xl-3 col-md-6">
    <div class="stat-card gradient-dark text-center">
        <i class="bi bi-wallet-fill stat-icon"></i>
        <h5 class="fw-bold">Saldo Ganancias neto hoy (entrada menos salida)</h5>
        <h2 class="fw-bold"><?= formatMoney($saldo_neto_hoy) ?></h2>
    </div>
</div>
</div>
<!-- Modal Nueva Venta -->
<div class="modal fade modal-modern" id="modalNuevaVenta" tabindex="-1">
  <div class="modal-dialog modal-xl">
    <div class="modal-content shadow-lg">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title">Nueva Venta</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form id="formNuevaVenta" action="save.php" method="POST" class="needs-validation" novalidate>
        <div class="modal-body bg-light">
          <?php if ($caja_activa): ?>
          <div class="row mb-4">
            <div class="col-md-12">
              <div class="input-group shadow-sm">
                <span class="input-group-text bg-white border-end-0">Search</span>
                <input type="text" id="buscadorProductos" class="form-control border-start-0" placeholder="Buscar productos...">
              </div>
              <div id="resultadosBusqueda" class="mt-2 card shadow" style="display: none;"></div>
            </div>
          </div>

          <div class="table-responsive card shadow-sm">
            <table class="table table-bordered table-sm table-hover" id="tablaVentas">
              <thead class="table-primary">
                <tr>
                  <th>Descripción</th>
                  <th>Tipo Pago</th>
                  <th>Punto Venta</th>
                  <th width="60">Cant.</th>
                  <th width="100">Precio Unit.</th>
                  <th width="100">Entrada</th>
                  <th width="100">Salida</th>
                  <th width="100">Saldo</th>
                  <th width="40"></th>
                </tr>
              </thead>
              <tbody>
                <tr class="filaVenta">
                  <td>
                    <input type="text" name="descripcion[]" class="form-control producto" placeholder="Producto...">
                    <input type="hidden" name="producto_id[]" class="producto_id">
                    <input type="hidden" name="stock_real[]" class="stock_real">
                    <input type="hidden" name="orden[]" class="orden_fila" value="0">
                  </td>
                  <!-- TIPO PAGO -->
                  <td>
                    <select name="tipo_pago_id[]" class="form-select tipo-pago" required>
                      <option value="">Seleccione...</option>
                      <?php foreach($tipos_pago as $tp): ?>
                        <option value="<?= $tp['id']; ?>"><?= $tp['nombre']; ?></option>
                      <?php endforeach; ?>
                    </select>
                  </td>
                  <!-- PUNTO DE VENTA -->
                  <td>
                    <select name="punto_venta_id[]" class="form-select punto-venta" required>
                      <option value="">Seleccione...</option>
                      <?php foreach($puntos_venta as $pv): ?>
                        <option value="<?= $pv['id']; ?>">
                          <?= $pv['id'] . ': ' . trim(substr($pv['nombre'], strpos($pv['nombre'], ':') + 1)); ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </td>
                  <td><input type="number" name="cantidad[]" class="form-control cantidad" value="1" min="1"></td>
                  <td><input type="number" name="precio_unitario[]" class="form-control precio" step="0.01" value="0.00"></td>
                  <td><input type="number" name="total_entrada[]" class="form-control entrada" step="0.01" value="0.00" readonly></td>
                  <td><input type="number" name="total_salida[]" class="form-control salida" step="0.01" value="0.00"></td>
                  <td class="saldo text-end">0.00</td>
                  <td class="text-center"><button type="button" class="btn btn-danger btn-sm btn-remove">X</button></td>
                </tr>
              </tbody>
            </table>
          </div>

          <div class="d-flex justify-content-between mt-3">
            <button type="button" class="btn btn-primary btn-modern" id="btnAgregarFila">Agregar Producto</button>
            <h5 class="text-primary">Saldo Total: S/. <span id="saldoTotal">0.00</span></h5>
          </div>
          <?php else: ?>
            <div class="alert alert-warning">Debes abrir la caja antes de registrar una transacción.</div>
          <?php endif; ?>
        </div>

        <div class="modal-footer bg-light">
          <button type="button" class="btn btn-secondary btn-modern" data-bs-dismiss="modal">Cerrar</button>
          <?php if ($caja_activa): ?>
          <button type="submit" class="btn btn-primary btn-modern" id="btnGuardarVenta">
            Guardar Transacciones
          </button>
          <?php endif; ?>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ======================= JAVASCRIPT: SOLO VALIDA TIPO Y PUNTO ======================= -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('formNuevaVenta');
    const btnGuardar = document.getElementById('btnGuardarVenta');
    const tbody = document.querySelector('#tablaVentas tbody');

    // === VALIDACIÓN SOLO PARA TIPO DE P
    if (btnGuardar) {
        btnGuardar.addEventListener('click', function (e) {
            let hayError = false;
            const filas = tbody.querySelectorAll('.filaVenta');

            filas.forEach(fila => {
                const tipoPago = fila.querySelector('.tipo-pago');
                const puntoVenta = fila.querySelector('.punto-venta');

                // Resetear
                tipoPago.classList.remove('is-invalid');
                puntoVenta.classList.remove('is-invalid');

                // Validar SOLO estos dos
                if (!tipoPago.value) {
                    tipoPago.classList.add('is-invalid');
                    hayError = true;
                }
                if (!puntoVenta.value) {
                    puntoVenta.classList.add('is-invalid');
                    hayError = true;
                }
            });

            if (hayError) {
                e.preventDefault();
                showToast('Completa el Tipo de Pago y Punto de Venta en todas las filas.', 'danger');
                return false;
            }

            // Si todo bien → enviar
            form.submit();
        });
    }

    // === AGREGAR FILA ===
    document.getElementById('btnAgregarFila')?.addEventListener('click', function () {
        const nuevaFila = tbody.querySelector('.filaVenta').cloneNode(true);
        const inputs = nuevaFila.querySelectorAll('input, select');
        inputs.forEach(input => {
            if (input.type !== 'hidden') input.value = '';
            if (input.classList.contains('cantidad')) input.value = 1;
            if (input.classList.contains('precio')) input.value = '0.00';
            if (input.classList.contains('entrada')) input.value = '0.00';
            if (input.classList.contains('salida')) input.value = '0.00';
            input.classList.remove('is-invalid');
        });
        nuevaFila.querySelector('.orden_fila').value = tbody.children.length;
        tbody.appendChild(nuevaFila);
        actualizarSaldos();
    });

    // === ELIMINAR FILA ===
    document.getElementById('tablaVentas').addEventListener('click', function (e) {
        if (e.target.classList.contains('btn-remove')) {
            if (tbody.children.length > 1) {
                e.target.closest('tr').remove();
                actualizarSaldos();
            } else {
                showToast('Debe haber al menos una fila.', 'warning');
            }
        }
    });

    // === CÁLCULO AUTOMÁTICO ===
    document.getElementById('tablaVentas').addEventListener('input', function (e) {
        const fila = e.target.closest('tr');
        if (!fila) return;

        const cantidad = parseFloat(fila.querySelector('.cantidad').value) || 0;
        const precio = parseFloat(fila.querySelector('.precio').value) || 0;
        const entrada = cantidad * precio;
        const salida = parseFloat(fila.querySelector('.salida').value) || 0;

        fila.querySelector('.entrada').value = entrada.toFixed(2);
        fila.querySelector('.saldo').textContent = (entrada - salida).toFixed(2);

        actualizarSaldos();
    });

    // === ACTUALIZAR SALDO TOTAL ===
    function actualizarSaldos() {
        let total = 0;
        tbody.querySelectorAll('.filaVenta').forEach(fila => {
            const entrada = parseFloat(fila.querySelector('.entrada').value) || 0;
            const salida = parseFloat(fila.querySelector('.salida').value) || 0;
            total += entrada - salida;
        });
        document.getElementById('saldoTotal').textContent = total.toFixed(2);
    }

    // === TOAST ===
    window.showToast = function(message, type = 'info') {
        const toast = document.createElement('div');
        toast.className = `toast align-items-center text-bg-${type === 'danger' ? 'danger' : 'warning'} border-0`;
        toast.style.cssText = 'position: fixed; bottom: 20px; right: 20px; z-index: 9999; min-width: 300px;';
        toast.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">${message}</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        `;
        document.body.appendChild(toast);
        new bootstrap.Toast(toast, { delay: 4000 }).show();
        setTimeout(() => toast.remove(), 5000);
    };

    // Inicializar
    actualizarSaldos();
});
</script>

<!-- ESTILO PARA CAMPOS INVÁLIDOS -->
<style>
.is-invalid {
    border-color: #dc3545 !important;
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 12 12' width='12' height='12' fill='none' stroke='%23dc3545'%3e%3ccircle cx='6' cy='6' r='4.5'/%3e%3cpath stroke-linejoin='round' d='M5.8 3.6h.4L6 6.5z'/%3e%3ccircle cx='6' cy='8.2' r='.6' fill='%23dc3545' stroke='none'/%3e%3c/svg%3e");
    background-repeat: no-repeat;
    background-position: right calc(0.375em + 0.1875rem) center;
    background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
}
</style>
<script>
function calcularTotalMonedas() {
  const tabla = document.getElementById('tablaMonedas');
  if (!tabla) return;
  let total = 0;
  const filas = tabla.querySelectorAll('tbody tr');
  filas.forEach(row => {
    const denomText = row.querySelector('.denominacion')?.innerText || '';
    // Extrae número (quita 'S/', espacios y puntos de miles), soporta decimales con coma o punto
    const denomNum = parseFloat(
      denomText
        .replace('S/', '')
        .replace(/\./g, '') // elimina separadores de miles
        .replace(',', '.') // coma -> punto decimal
        .trim()
    ) || 0;
    const input = row.querySelector('.input-cantidad');
    const cant = parseFloat(input?.value) || 0;
    const subtotal = denomNum * cant;
    const celTotal = row.querySelector('.total-moneda');
    if (celTotal) celTotal.innerText = subtotal.toFixed(2);
    total += subtotal;
  });
  const totalEl = document.getElementById('totalMonedas');
  if (totalEl) totalEl.innerText = total.toFixed(2);
}
</script>
<style>
  .card {
    transition: all 0.2s ease-in-out;
  }
  .card:hover {
    box-shadow: 0 0.6rem 1rem rgba(0,0,0,0.08);
  }
  .card-header {
    font-size: 0.95rem;
    letter-spacing: 0.2px;
  }
  .table-bordered td, .table-bordered th {
    border-color: #dee2e6 !important;
  }
</style>
<!-- ====== SCRIPTS (CARGADOS EN ORDEN CORRECTO) ====== -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ====== SISTEMA DE VENTAS MÁS PRO DEL PERÚ - NOVIEMBRE 2025 (CON FLECHAS PERFECTAS) ======
document.addEventListener("DOMContentLoaded", function () {
    const tabla = document.querySelector("#tablaVentas tbody");
    const saldoTotalSpan = document.getElementById("saldoTotal");
    const buscador = document.getElementById("buscadorProductos");
    const resultados = document.getElementById("resultadosBusqueda");
    // ====== RECALCULAR TODO CON ESTILO PRO ======
    function recalcular() {
        let total = 0;
        tabla.querySelectorAll("tr.filaVenta").forEach(fila => {
            const cant = parseFloat(fila.querySelector(".cantidad").value) || 0;
            const precio = parseFloat(fila.querySelector(".precio").value) || 0;
            const salida = parseFloat(fila.querySelector(".salida").value) || 0;
            const stock = parseFloat(fila.querySelector(".stock_real").value) || Infinity;
            const entrada = cant * precio;
            fila.querySelector(".entrada").value = entrada.toFixed(2);
            const saldoFila = entrada - salida;
            fila.querySelector(".saldo").textContent = saldoFila.toFixed(2);
            // EFECTO VISUAL DE STOCK
            const inputCant = fila.querySelector(".cantidad");
            if (stock === Infinity) {
                inputCant.style.backgroundColor = "";
                inputCant.title = "";
            } else if (cant > stock) {
                inputCant.style.backgroundColor = "#ffebee";
                inputCant.style.color = "#c62828";
                inputCant.style.fontWeight = "bold";
                inputCant.title = `STOCK INSUFICIENTE! Disponible: ${stock}`;
            } else if (stock <= 5) {
                inputCant.style.backgroundColor = "#fff3e0";
                inputCant.style.color = "#ef6c00";
                inputCant.title = `Stock bajo: ${stock} unidades`;
            } else {
                inputCant.style.backgroundColor = "";
                inputCant.style.color = "";
                inputCant.title = `Stock disponible: ${stock}`;
            }
            total += saldoFila;
        });
        saldoTotalSpan.textContent = total.toFixed(2);
        saldoTotalSpan.style.fontSize = total >= 0 ? "1.5rem" : "1.3rem";
        saldoTotalSpan.style.color = total >= 0 ? "#2e7d32" : "#c62828";
    }
 // ====== AGREGAR FILA NUEVA CON ORDEN ======
function agregarFila() {
    const clone = tabla.querySelector("tr.filaVenta").cloneNode(true);
    
    // LIMPIAR CAMPOS
    clone.querySelectorAll("input").forEach(i => {
        if (i.classList.contains("cantidad")) i.value = "1";
        else if (!i.readOnly && i.type !== "hidden") i.value = "";
        else i.value = "";
    });
    clone.querySelectorAll("select").forEach(s => s.selectedIndex = 0);
    clone.querySelector(".saldo").textContent = "0.00";

    // ASIGNAR ORDEN (número de filas actuales)
    const filasActuales = tabla.querySelectorAll("tr.filaVenta").length;
    const inputOrden = clone.querySelector(".orden_fila");
    if (inputOrden) {
        inputOrden.value = filasActuales; // 0, 1, 2...
    }

    tabla.appendChild(clone);
    setupAutocomplete(clone);
    clone.querySelector(".producto").focus();
    recalcular();
}
     // ====== AUTOCOMPLETE PRO ======
function setupAutocomplete(fila) {
    const input = $(fila).find(".producto");
    const precio = fila.querySelector(".precio");
    const stockInput = fila.querySelector(".stock_real");
    const idInput = fila.querySelector(".producto_id");
   
    input.autocomplete({
        source: "search_producto.php",
        minLength: 2,
        select: function(e, ui) {
            input.val(ui.item.label);
            precio.value = parseFloat(ui.item.precio).toFixed(2);
            
            // AGREGADO: Soporte stock infinito
            stockInput.value = ui.item.stock_infinito ? 'infinity' : ui.item.stock;

            idInput.value = ui.item.id;

            // ===== CORRECCIÓN PUNTO DE VENTA =====
            const selectPV = fila.querySelector(".punto_venta_id");
            if (selectPV) {
                const pvId = ui.item.punto_venta_id !== null && ui.item.punto_venta_id !== undefined 
                             ? String(ui.item.punto_venta_id)
                             : '8';
                selectPV.value = selectPV.querySelector(`option[value="${pvId}"]`) ? pvId : '8';
            }

            fila.querySelector(".cantidad").value = 1;
            recalcular();
            return false;
        }
    }).data("ui-autocomplete")._renderItem = function(ul, item) {
        // AGREGADO: Mostrar INFINITO si aplica
        let stockText, stockColor;
        if (item.stock_infinito) {
            stockText = "INFINITO";
            stockColor = "text-info fw-bold";
        } else {
            stockText = item.stock <= 0 ? "SIN STOCK" : item.stock <= 5 ? `${item.stock} (BAJO)` : item.stock;
            stockColor = item.stock <= 0 ? "text-danger" : item.stock <= 5 ? "text-warning fw-bold" : "text-success";
        }

        return $("<li>").append(
            `<div class="d-flex justify-content-between">
                <div><strong>${item.label}</strong></div>
                <div>S/. ${parseFloat(item.precio).toFixed(2)}</div>
            </div>
            <div class="small ${stockColor}">Stock: ${stockText}</div>`
        ).appendTo(ul);
    };
}

   // ====== BUSCADOR ÉPICO ======
let debounce;
buscador.addEventListener("input", function () {
    clearTimeout(debounce);
    const q = this.value.trim();
    if (q.length < 2) { resultados.style.display = "none"; return; }

    debounce = setTimeout(() => {
        fetch(`search_producto.php?term=${encodeURIComponent(q)}`)
            .then(r => r.json())
            .then(items => {
                if (!items.length) {
                    resultados.innerHTML = `<div class="p-3 text-center text-muted">No hay productos</div>`;
                    resultados.style.display = "block";
                    return;
                }
                let html = '<div class="list-group">';
                items.forEach(p => {
                    const stockBadge = p.stock_infinito
                        ? '<span class="badge bg-info text-white fw-bold">∞</span>'
                        : p.stock <= 0 ? '<span class="badge bg-danger">SIN STOCK</span>'
                        : p.stock <= 5 ? `<span class="badge bg-warning text-dark">BAJO (${p.stock})</span>`
                        : `<span class="badge bg-success">${p.stock}</span>`;

                    html += `
                        <a href="#" class="list-group-item list-group-item-action px-3 py-2" data-punto-venta-id="${p.punto_venta_id || ''}">
                            <div class="d-flex w-100 justify-content-between">
                                <h6 class="mb-1">${p.nombre}</h6>
                                <small class="text-success fw-bold">S/. ${parseFloat(p.precio).toFixed(2)}</small>
                            </div>
                            <div class="d-flex w-100 justify-content-between align-items-center">
                                <small class="text-muted">Código: ${p.id}</small>
                                ${stockBadge}
                            </div>
                        </a>`;
                });
                html += '</div>';
                resultados.innerHTML = html;
                resultados.style.display = "block";

                resultados.querySelectorAll("a").forEach(a => {
                    a.onclick = function (e) {
                        e.preventDefault();
                        let fila = [...tabla.querySelectorAll("tr.filaVenta")].find(f => !f.querySelector(".producto").value.trim()) || agregarFila();

                        fila.querySelector(".producto").value = this.querySelector("h6").textContent;
                        fila.querySelector(".precio").value = parseFloat(this.querySelector(".text-success").textContent.replace("S/. ", "")).toFixed(2);

                        // CORREGIDO: ∞ → 'infinity'
                        const badgeText = this.querySelector(".badge").textContent.trim();
                        fila.querySelector(".stock_real").value = badgeText === '∞' ? 'infinity' : (badgeText.match(/\d+/)?.[0] || 0);

                        fila.querySelector(".producto_id").value = this.querySelector("small.text-muted").textContent.replace("Código: ", "");
                        const puntoVentaId = this.dataset.puntoVentaId || '8';
                        fila.querySelector(".punto_venta_id").value = puntoVentaId;
                        fila.querySelector(".cantidad").value = 1;

                        buscador.value = "";
                        resultados.style.display = "none";
                        recalcular();
                    };
                });
            });
    }, 250);
});

buscador.addEventListener("keydown", e => {
    if (e.key === "Enter" && resultados.style.display === "block") {
        resultados.querySelector("a")?.click();
        e.preventDefault();
    }
});
    // ====== EVENTOS BÁSICOS ======
    tabla.addEventListener("input", e => {
        if (e.target.matches(".cantidad, .precio, .salida")) recalcular();
    });
    tabla.addEventListener("click", e => {
        if (e.target.closest(".btn-remove") && tabla.children.length > 1) {
            e.target.closest("tr").remove();
            recalcular();
        }
    });
    document.getElementById("btnAgregarFila").addEventListener("click", agregarFila);
    // ====== NAVEGACIÓN CON FLECHAS ULTRA PRO (LO QUE PEDISTE) ======
    tabla.addEventListener("keydown", function(e) {
        // TECLA "A" → IR AL BUSCADOR
        if (e.key.toLowerCase() === "+" && !e.ctrlKey && !e.altKey) {
            buscador.focus();
            e.preventDefault();
            return;
        }
        const inputs = Array.from(tabla.querySelectorAll("input:not([readonly]):not([type='hidden']), select"));
        const current = e.target;
        const index = inputs.indexOf(current);
        if (index === -1) return;
        if (e.key === "ArrowRight" && index < inputs.length - 1) {
            inputs[index + 1].focus();
            e.preventDefault();
        }
        else if (e.key === "ArrowLeft" && index > 0) {
            inputs[index - 1].focus();
            e.preventDefault();
        }
        else if (e.key === "ArrowDown") {
            const currentRow = current.closest("tr");
            const nextRow = currentRow.nextElementSibling;
            if (nextRow) {
                const nextInputs = nextRow.querySelectorAll("input:not([readonly]):not([type='hidden']), select");
                const colIndex = Array.from(current.closest("tr").querySelectorAll("input:not([readonly]):not([type='hidden']), select")).indexOf(current);
                if (nextInputs[colIndex]) nextInputs[colIndex].focus();
            } else {
                // Si es la última fila → agrega una nueva
                agregarFila();
                setTimeout(() => {
                    const newRow = tabla.lastElementChild;
                    const newInputs = newRow.querySelectorAll("input:not([readonly]):not([type='hidden']), select");
                    if (newInputs[0]) newInputs[0].focus();
                }, 50);
            }
            e.preventDefault();
        }
        else if (e.key === "ArrowUp") {
            const currentRow = current.closest("tr");
            const prevRow = currentRow.previousElementSibling;
            if (prevRow) {
                const prevInputs = prevRow.querySelectorAll("input:not([readonly]):not([type='hidden']), select");
                const colIndex = Array.from(current.closest("tr").querySelectorAll("input:not([readonly]):not([type='hidden']), select")).indexOf(current);
                if (prevInputs[colIndex]) prevInputs[colIndex].focus();
            }
            e.preventDefault();
        }
    });
    // Activar autocomplete en filas existentes
    tabla.querySelectorAll("tr.filaVenta").forEach(setupAutocomplete);
    // Cálculo inicial
    recalcular();
    // Segundero (reloj con segundos)
    function updateClock() {
      const now = new Date();
      const time = now.toLocaleTimeString('es-PE', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
      document.getElementById('clock').innerText = time;
    }
    setInterval(updateClock, 1000);
    updateClock();
    // Impresión con botón y tecla "-"
   
    document.addEventListener('keydown', (e) => {
      if (e.key === '-' && e.target.tagName.toLowerCase() !== 'input' && e.target.tagName.toLowerCase() !== 'textarea') {
        window.print();
      }
    });
  // ====== BORRAR TRANSACCIONES - VERSIÓN CORREGIDA 2025 ======

// Seleccionar/Deseleccionar todos
document.getElementById('select-all')?.addEventListener('click', function() {
    const checkboxes = document.querySelectorAll('.select-venta');
    const allChecked = Array.from(checkboxes).every(cb => cb.checked);
    
    checkboxes.forEach(cb => {
        cb.checked = !allChecked;
    });
    
    this.innerHTML = allChecked ? 
        '<i class="bi bi-check-square"></i> Seleccionar Todo' : 
        '<i class="bi bi-square"></i> Deseleccionar Todo';
});

// ====== BORRAR SELECCIONADAS (FINAL) ======
document.getElementById('delete-selected')?.addEventListener('click', async function () {
    const seleccionadas = document.querySelectorAll('.select-venta:checked');
    if (seleccionadas.length === 0) {
        Swal.fire('Advertencia', 'Selecciona al menos una transacción', 'warning');
        return;
    }

    const ids = Array.from(seleccionadas).map(cb => cb.value);

    const { value: creds } = await Swal.fire({
        title: '<strong class="text-danger">AUTENTICACIÓN REQUERIDA</strong>',
        html: `
            <div class="text-start p-4 bg-light rounded shadow-sm">
                <div class="mb-3">
                    <label class="form-label fw-bold">Usuario</label>
                    <input id="swal-user" class="form-control form-control-lg" value="admin">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Contraseña</label>
                    <input type="password" id="swal-pass" class="form-control form-control-lg">
                </div>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: 'Verificar',
        preConfirm: () => {
            const user = document.getElementById('swal-user').value.trim();
            const pass = document.getElementById('swal-pass').value;
            if (!user || !pass) return false;
            return { usuario: user, password: pass };
        }
    });

    if (!creds) return;

    try {
        const res = await fetch('verificar_login.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(creds)
        });
        const data = await res.json();
        if (!data.success) {
            Swal.fire('Error', data.message, 'error');
            return;
        }

        const formData = new FormData();
        ids.forEach(id => formData.append('ids[]', id));
        formData.append('action', 'selected');
        // NO ENVÍES caja_id → ya está en sesión
        formData.append('usuario', creds.usuario);
        formData.append('password', creds.password);

        const deleteRes = await fetch('delete.php', { method: 'POST', body: formData });
        const deleteData = await deleteRes.json();

        if (deleteData.success) {
            await Swal.fire('Éxito', deleteData.message, 'success');
            location.reload();
        } else {
            Swal.fire('Error', deleteData.message, 'error');
        }
    } catch (error) {
        console.error(error);
        Swal.fire('Error', 'No se pudo conectar al servidor', 'error');
    }
});

// ====== BORRAR TODAS (SI LO USAS) ======
document.getElementById('delete-all')?.addEventListener('click', async function () {
    const total = document.querySelectorAll('.select-venta').length;
    if (total === 0) {
        Swal.fire('Info', 'No hay transacciones', 'info');
        return;
    }

    const { value: creds } = await Swal.fire({
        title: '<strong class="text-danger">¡ELIMINAR TODO!</strong>',
        html: `
            <div class="text-start p-4 bg-light rounded shadow-sm">
                <div class="alert alert-danger small mb-3">
                    <strong>¡IRREVERSIBLE!</strong> Se eliminarán <strong>${total}</strong> transacciones.
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Usuario</label>
                    <input id="swal-user" class="form-control form-control-lg" value="admin">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Contraseña</label>
                    <input type="password" id="swal-pass" class="form-control form-control-lg">
                </div>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: 'Eliminar Todo',
        preConfirm: () => {
            const user = document.getElementById('swal-user').value.trim();
            const pass = document.getElementById('swal-pass').value;
            if (!user || !pass) return false;
            return { usuario: user, password: pass };
        }
    });

    if (!creds) return;

    try {
        const res = await fetch('verificar_login.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(creds)
        });
        const data = await res.json();
        if (!data.success) {
            Swal.fire('Error', data.message, 'error');
            return;
        }

        const formData = new FormData();
        formData.append('action', 'all');
        // NO ENVÍES caja_id
        formData.append('usuario', creds.usuario);
        formData.append('password', creds.password);

        const deleteRes = await fetch('delete.php', { method: 'POST', body: formData });
        const deleteData = await deleteRes.json();

        if (deleteData.success) {
            await Swal.fire('Éxito', deleteData.message, 'success');
            location.reload();
        } else {
            Swal.fire('Error', deleteData.message, 'error');
        }
    } catch (error) {
        console.error(error);
        Swal.fire('Error', 'No se pudo conectar al servidor', 'error');
    }
});
// ====== ELIMINAR TRANSACCIÓN INDIVIDUAL ======
document.addEventListener('click', async function(e) {
    if (e.target.closest('.btn-delete-single')) {
        const button = e.target.closest('.btn-delete-single');
        const transaccionId = button.getAttribute('data-id');
        const descripcion = button.getAttribute('data-descripcion');
        
        await eliminarTransaccionIndividual(transaccionId, descripcion);
    }
});

async function eliminarTransaccionIndividual(id, descripcion) {
    const { value: creds } = await Swal.fire({
        title: '<strong class="text-danger">ELIMINAR TRANSACCIÓN</strong>',
        html: `
            <div class="text-start p-4 bg-light rounded shadow-sm">
                <div class="alert alert-warning mb-3">
                    <i class="bi bi-exclamation-triangle"></i>
                    <strong>¿Estás seguro?</strong><br>
                    Se eliminará la transacción:<br>
                    <strong>"${descripcion}"</strong>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Usuario</label>
                    <input id="swal-user" class="form-control form-control-lg" value="admin">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Contraseña</label>
                    <input type="password" id="swal-pass" class="form-control form-control-lg">
                </div>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: 'Eliminar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#dc3545',
        preConfirm: () => {
            const user = document.getElementById('swal-user').value.trim();
            const pass = document.getElementById('swal-pass').value;
            if (!user || !pass) {
                Swal.showValidationMessage('Completa ambos campos');
                return false;
            }
            return { usuario: user, password: pass };
        }
    });

    if (!creds) return;

    try {
        // Verificar credenciales primero
        const authRes = await fetch('verificar_login.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(creds)
        });
        
        const authData = await authRes.json();
        if (!authData.success) {
            Swal.fire('Error', authData.message, 'error');
            return;
        }

        // Eliminar la transacción
        const formData = new FormData();
        formData.append('ids[]', id);
        formData.append('action', 'selected');
        formData.append('usuario', creds.usuario);
        formData.append('password', creds.password);

        const deleteRes = await fetch('delete.php', {
            method: 'POST',
            body: formData
        });
        
        const deleteData = await deleteRes.json();

        if (deleteData.success) {
            await Swal.fire({
                icon: 'success',
                title: 'Eliminado',
                text: deleteData.message,
                timer: 2000,
                showConfirmButton: false
            });
            location.reload();
        } else {
            Swal.fire('Error', deleteData.message, 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        Swal.fire('Error', 'No se pudo conectar al servidor', 'error');
    }
}

// ====== EDITAR TRANSACCIÓN INDIVIDUAL ======
document.addEventListener('click', async function(e) {
    if (e.target.closest('.btn-edit-single')) {
        const button = e.target.closest('.btn-edit-single');
        const transaccionId = button.getAttribute('data-id');
        const descripcion = button.getAttribute('data-descripcion');
        
        await editarTransaccionIndividual(transaccionId, descripcion);
    }
});

async function editarTransaccionIndividual(id, descripcion) {
    const { value: creds } = await Swal.fire({
        title: '<strong class="text-warning">EDITAR TRANSACCIÓN</strong>',
        html: `
            <div class="text-start p-4 bg-light rounded shadow-sm">
                <div class="alert alert-info mb-3">
                    <i class="bi bi-pencil-square"></i>
                    <strong>Editar transacción:</strong><br>
                    <strong>"${descripcion}"</strong>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Usuario</label>
                    <input id="swal-user" class="form-control form-control-lg" value="admin">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Contraseña</label>
                    <input type="password" id="swal-pass" class="form-control form-control-lg">
                </div>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: 'Continuar a Editar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#ffc107',
        preConfirm: () => {
            const user = document.getElementById('swal-user').value.trim();
            const pass = document.getElementById('swal-pass').value;
            if (!user || !pass) {
                Swal.showValidationMessage('Completa ambos campos');
                return false;
            }
            return { usuario: user, password: pass };
        }
    });

    if (!creds) return;

    try {
        // Verificar credenciales primero
        const authRes = await fetch('verificar_login.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(creds)
        });
        
        const authData = await authRes.json();
        if (!authData.success) {
            Swal.fire('Error', authData.message, 'error');
            return;
        }

        // Credenciales válidas, redirigir a editar
        await Swal.fire({
            icon: 'success',
            title: 'Acceso Autorizado',
            text: 'Redirigiendo a edición...',
            timer: 1500,
            showConfirmButton: false
        });
        
        // Redirigir a la página de edición
        window.location.href = `edit.php?id=${id}`;

    } catch (error) {
        console.error('Error:', error);
        Swal.fire('Error', 'No se pudo conectar al servidor', 'error');
    }
}


});
</script>
<script>
// Cargar SweetAlert2 desde CDN (agrega esto en el <head>)
// <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// Función de impresión en blanco (envía orden sin contenido)
function imprimirEnBlanco() {
  // Crea un iframe oculto
  const iframe = document.createElement('iframe');
  iframe.style.display = 'none';
  iframe.style.position = 'absolute';
  iframe.style.left = '-9999px';
  document.body.appendChild(iframe);
  const doc = iframe.contentWindow.document;
  doc.open();
  // Contenido vacío + estilo para no mostrar nada
  doc.write(`
    <html>
      <head><title></title></head>
      <body style="margin:0;padding:0;"></body>
    </html>
  `);
  doc.close();
  // Enviar a impresora
  iframe.contentWindow.focus();
  iframe.contentWindow.print();
  // Eliminar iframe después de imprimir
  setTimeout(() => {
    if (iframe.parentNode) iframe.parentNode.removeChild(iframe);
    
    // Mostrar mensaje de confirmación elegante DESPUÉS de imprimir
    Swal.fire({
      title: '✅ Operación Exitosa',
      text: 'Impresión en blanco enviada correctamente a la impresora',
      icon: 'success',
      confirmButtonText: 'Aceptar',
      confirmButtonColor: '#3085d6',
      timer: 3000,
      timerProgressBar: true,
      toast: true,
      position: 'top-end',
      showConfirmButton: false
    });
    
  }, 1000);
}

// Asignar al botón
document.getElementById('imprimirSello')?.addEventListener('click', function(e) {
  e.preventDefault();
  imprimirEnBlanco();
});

// Asignar a la tecla "-" con prevención completa
document.addEventListener('keydown', (e) => {
  if (e.key === '-' && !['INPUT', 'TEXTAREA'].includes(e.target.tagName)) {
    // Prevenir completamente el comportamiento por defecto
    e.preventDefault();
    e.stopPropagation();
    e.stopImmediatePropagation();
    
    console.log('Tecla - presionada, imprimiendo en blanco...');
    imprimirEnBlanco();
    
    // Prevenir cualquier otro evento que pueda dispararse
    return false;
  }
});

// También prevenir el evento keyup por si acaso
document.addEventListener('keyup', (e) => {
  if (e.key === '-' && !['INPUT', 'TEXTAREA'].includes(e.target.tagName)) {
    e.preventDefault();
    e.stopPropagation();
    return false;
  }
});

</script>
</body>
</html>
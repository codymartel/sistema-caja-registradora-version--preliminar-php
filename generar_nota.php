<?php
// generar_nota.php - VERSIÓN CORREGIDA: CREA COLUMNAS FALTANTES

$host = 'localhost';
$dbname = 'notas_clientes';
$user = 'root';
$pass = '';

// ========================= CONEXIÓN Y CREACIÓN DE TABLA SEGURA =========================
try {
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `$dbname`");

    // Crear tabla base
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS nota_cliente (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nombre VARCHAR(100) NOT NULL,
        dni VARCHAR(20) NOT NULL DEFAULT '',
        numero VARCHAR(20) NOT NULL,
        servicio_problema TEXT NOT NULL,
        monto_pagado DECIMAL(10,2) DEFAULT 0.00,
        monto_pendiente DECIMAL(10,2) DEFAULT 0.00,
        pago_total DECIMAL(10,2) NOT NULL,
        fecha_emision DATE NOT NULL,
        hora_emision TIME NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // === VERIFICAR Y AGREGAR COLUMNA `pago_completado` SI NO EXISTE ===
    $columnCheck = $pdo->query("SHOW COLUMNS FROM nota_cliente LIKE 'pago_completado'")->rowCount();
    if ($columnCheck == 0) {
        $pdo->exec("ALTER TABLE nota_cliente ADD COLUMN pago_completado TINYINT(1) DEFAULT 0 AFTER monto_pendiente");
    }

    // Reconectar con la base de datos específica
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

// ========================= RESTO DEL CÓDIGO (SIN CAMBIOS) =========================
$mensaje = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $pagado = floatval($_POST['monto_pagado']);
    $pendiente = floatval($_POST['monto_pendiente']);
    $pago_completado = isset($_POST['pago_completado']) ? 1 : 0;
    $total = $pagado + $pendiente;

    try {
        if ($action === 'crear') {
            $stmt = $pdo->prepare("INSERT INTO nota_cliente (nombre, dni, numero, servicio_problema, monto_pagado, monto_pendiente, pago_completado, pago_total, fecha_emision, hora_emision) VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), CURTIME())");
            $stmt->execute([$_POST['nombre'], $_POST['dni'], $_POST['numero'], $_POST['servicio_problema'], $pagado, $pendiente, $pago_completado, $total]);
            $id_nuevo = $pdo->lastInsertId();
            $mensaje = "Nota creada con éxito! #<strong>$id_nuevo</strong>";
            header("Location: generar_nota.php?msg=creada&id=$id_nuevo");
            exit;
        }

        if ($action === 'editar') {
            $id = (int)$_POST['id'];
            $stmt = $pdo->prepare("UPDATE nota_cliente SET nombre=?, dni=?, numero=?, servicio_problema=?, monto_pagado=?, monto_pendiente=?, pago_completado=?, pago_total=? WHERE id=?");
            $stmt->execute([$_POST['nombre'], $_POST['dni'], $_POST['numero'], $_POST['servicio_problema'], $pagado, $pendiente, $pago_completado, $total, $id]);
            $mensaje = "Nota actualizada con éxito! #<strong>$id</strong>";
            header("Location: generar_nota.php?msg=editada&id=$id");
            exit;
        }
    } catch (PDOException $e) {
        $mensaje = "ERROR AL GUARDAR: " . $e->getMessage() . "<br><small>Intenta recargar la página.</small>";
    }
}

// ... [EL RESTO DEL CÓDIGO (HTML, TABLA, IMPRESIÓN) ES EL MISMO] ...

// ========================= ELIMINAR REGISTRO =========================
if (isset($_GET['eliminar'])) {
    $id = (int)$_GET['eliminar'];
    try {
        $pdo->prepare("DELETE FROM nota_cliente WHERE id = ?")->execute([$id]);
        header("Location: generar_nota.php?msg=eliminada");
        exit;
    } catch (PDOException $e) {
        $mensaje = "❌ ERROR AL ELIMINAR: " . $e->getMessage();
    }
}

// ========================= MENSAJES DE ESTADO =========================
if (isset($_GET['msg'])) {
    $msg = $_GET['msg'];
    $id = $_GET['id'] ?? '';
    if ($msg === 'creada') $mensaje = "✅ Nota creada con éxito! #<strong>$id</strong>";
    if ($msg === 'editada') $mensaje = "✅ Nota actualizada con éxito! #<strong>$id</strong>";
    if ($msg === 'eliminada') $mensaje = "🗑️ Nota eliminada correctamente.";
}

// ========================= EDITAR NOTA EXISTENTE =========================
$nota_editar = null;
if (isset($_GET['editar'])) {
    $id = (int)$_GET['editar'];
    $stmt = $pdo->prepare("SELECT * FROM nota_cliente WHERE id = ?");
    $stmt->execute([$id]);
    $nota_editar = $stmt->fetch();
}

// ========================= CARGAR TODOS LOS REGISTROS =========================
$registros = [];
try {
    $stmt = $pdo->query("SELECT * FROM nota_cliente ORDER BY id DESC");
    $registros = $stmt->fetchAll();
} catch (PDOException $e) {
    $mensaje = "❌ ERROR AL CARGAR REGISTROS: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>3DR COMPUTEC - Notas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --red: #e74c3c;
            --dark: #2c3e50;
            --success: #27ae60;
            --warning: #f39c12;
            --light: #f8f9fa;
            --gray: #95a5a6;
        }
        * { font-family: 'Poppins', sans-serif; }
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #e4e9f0 100%);
            min-height: 100vh;
            position: relative;
            overflow-x: hidden;
        }

        .logo2-lateral {
            position: fixed;
            top: 0;
            left: 0;
            width: 320px;
            height: 100vh;
            object-fit: contain;
            opacity: 0.09;
            z-index: 0;
            pointer-events: none;
        }

        .main-content {
            position: relative;
            z-index: 1;
            padding: 2.5rem 1.5rem;
        }

        .logo-container {
            background: white;
            padding: 18px 35px;
            border: 5px solid var(--red);
            border-radius: 22px;
            display: inline-block;
            box-shadow: 0 12px 35px rgba(231, 76, 60, 0.25);
            margin-bottom: 25px;
        }
        .logo-principal {
            height: 85px;
            width: auto;
        }

        .title-main {
            font-weight: 800;
            color: var(--dark);
            font-size: 2.1rem;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .card {
            border: none;
            border-radius: 18px;
            box-shadow: 0 12px 40px rgba(0,0,0,0.12);
            transition: all 0.3s ease;
        }
        .card:hover {
            box-shadow: 0 18px 50px rgba(0,0,0,0.18);
            transform: translateY(-5px);
        }
        .card-header {
            background: var(--dark);
            color: white;
            font-weight: 700;
            font-size: 1.15rem;
            padding: 1rem 1.5rem;
            border-bottom: 3px solid var(--red);
        }

        .form-control {
            border-radius: 12px;
            border: 2px solid #ced4da;
            padding: 0.7rem 1rem;
            transition: all 0.3s ease;
        }
        .form-control:focus {
            border-color: var(--red);
            box-shadow: 0 0 0 0.25rem rgba(231, 76, 60, 0.2);
        }

        .btn {
            border-radius: 14px;
            font-weight: 600;
            padding: 0.75rem 1.5rem;
            transition: all 0.3s ease;
        }
        .btn-success {
            background: var(--success);
            border: none;
        }
        .btn-success:hover {
            background: #1e8449;
            transform: scale(1.02);
        }

        .badge-id {
            background: var(--success);
            color: white;
            font-weight: 700;
            padding: 0.5em 1em;
            border-radius: 50px;
            font-size: 0.9rem;
        }

        .table thead {
            background: var(--dark);
            color: white;
            font-weight: 600;
        }

        /* ========================= IMPRESIÓN A5 COMPACTA ========================= */
        .print-area {
            background: white;
            padding: 20px; /* Reducido para A5 */
            max-width: 148mm; /* Ancho exacto A5 */
            margin: 0 auto;
            border: 1px solid #e0e0e0;
            border-radius: 0; /* Sin bordes redondeados para impresión */
            box-shadow: none;
            font-size: 0.85rem; /* Texto más compacto */
        }
        .print-logo-small {
            height: 40px; /* Logo más pequeño */
            margin-bottom: 10px;
            display: block;
            margin: 0 auto;
        }
        .print-title {
            font-size: 1.3rem; /* Título más pequeño */
            font-weight: 800;
            color: var(--dark);
            text-align: center;
            margin-bottom: 10px;
        }
        .print-line {
            height: 1px;
            background: #ddd;
            margin: 8px 0; /* Márgenes reducidos */
        }
        .print-total {
            background: var(--dark);
            color: white;
            padding: 8px;
            border-radius: 5px;
            text-align: center;
            font-weight: 800;
            font-size: 1.1rem;
            margin: 10px 0;
        }
        /* NUEVO: Estilo para la política de recojo */
        .print-policy {
            font-size: 0.75rem; /* Texto pequeño pero legible */
            font-weight: 600;
            color: var(--red);
            text-align: center;
            margin: 12px 0;
            padding: 8px;
            border: 1px solid var(--red);
            border-radius: 5px;
            background: #fff5f5;
            line-height: 1.2; /* Espaciado compacto */
        }
        /* NUEVO: Estilo para la validez */
        .print-validez {
            font-size: 0.75rem;
            text-align: center;
            font-weight: 700;
            color: var(--dark);
            margin-top: 15px;
            padding-top: 10px;
            border-top: 1px solid #eee;
        }
        /* NUEVO: Estado de pago en impresión */
        .print-pago-status {
            text-align: center;
            font-weight: 700;
            padding: 6px;
            border-radius: 5px;
            margin: 8px 0;
            font-size: 0.85rem;
        }
        .pago-completado {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .pago-pendiente {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }

        @media print {
            body, .main-content { background: white !important; margin: 0 !important; padding: 0 !important; }
            .no-print, .logo2-lateral, .logo-container { display: none !important; }
            .print-area { 
                display: block !important; 
                border: none !important; 
                box-shadow: none !important; 
                padding: 15mm 10mm !important; /* Márgenes para impresora */
                margin: 0 !important;
                width: 148mm !important; /* Ancho A5 */
                min-height: 210mm; /* Alto A5 */
            }
            /* Asegurar que todo el contenido sea visible en A5 */
            .print-area * {
                max-width: 100% !important;
                word-wrap: break-word !important;
            }
        }
        #print-area { display: none; }
        /* Estilo para formularios más detallados */
        .form-label {
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.3rem;
        }
        .form-group {
            margin-bottom: 1rem;
        }



        /* ====================== OCXMOTO A5 - 1 HOJA (HORIZONTAL Y VERTICAL) ====================== */
@media print {
    @page {
        size: A5; /* 148mm × 210mm */
        margin: 6mm !important;
    }

    body, html, .main-content {
        margin: 0 !important;
        padding: 0 !important;
        background: white !important;
        width: 148mm !important;
        height: 210mm !important;
    }

    .no-print, .logo2-lateral, .logo-container, .card, .table {
        display: none !important;
    }

    .print-area {
        display: block !important;
        position: relative;
        width: 136mm !important;    /* 148mm - 6mm margen x2 */
        height: 198mm !important;   /* 210mm - 6mm margen x2 */
        padding: 6mm 6mm !important;
        margin: 0 !important;
        font-family: Arial, sans-serif !important;
        font-size: 0.78rem !important;
        line-height: 1.25 !important;
        color: #000 !important;
        page-break-after: avoid !important;
        page-break-before: avoid !important;
        overflow: hidden !important;
        box-sizing: border-box;
    }

    /* Ajustes finos */
    .print-logo-small {
        height: 32px !important;
        margin-bottom: 4px !important;
    }
    .print-title {
        font-size: 1.22rem !important;
        margin: 0 0 6px 0 !important;
        font-weight: 800 !important;
    }
    .print-line {
        height: 1px !important;
        background: #000 !important;
        margin: 5px 0 !important;
    }
    .print-servicio-box {
        background: #f8f9fa !important;
        padding: 5px 6px !important;
        border-radius: 4px !important;
        min-height: 45px !important;
        font-size: 0.76rem !important;
        line-height: 1.3 !important;
        word-wrap: break-word !important;
        max-height: 55px !important;
        overflow: hidden !important;
    }
    .print-pago-status {
        font-size: 0.8rem !important;
        padding: 4px 6px !important;
        border-radius: 4px !important;
        text-align: center !important;
        font-weight: 700 !important;
    }
    .pago-completado {
        background: #d4edda !important;
        color: #155724 !important;
    }
    .pago-pendiente {
        background: #fff3cd !important;
        color: #856404 !important;
    }
    .print-total {
        background: #000 !important;
        color: white !important;
        padding: 5px !important;
        border-radius: 4px !important;
        text-align: center !important;
        font-weight: 800 !important;
        font-size: 0.95rem !important;
    }
    .print-policy {
        font-size: 0.68rem !important;
        font-weight: 700 !important;
        text-align: center !important;
        padding: 5px !important;
        background: #fff5f5 !important;
        border: 1px solid #e74c3c !important;
        border-radius: 4px !important;
        line-height: 1.2 !important;
    }
    .print-validez {
        font-size: 0.7rem !important;
        text-align: center !important;
        font-weight: 700 !important;
        margin-top: 6px !important;
        padding-top: 6px !important;
        border-top: 1px dashed #000 !important;
    }
}
    </style>
</head>
<body>

    <img src="logo2.png" alt="Marca de agua" class="logo2-lateral" onerror="this.style.display='none'">

    <div class="main-content container">

        <div class="text-center mb-5">
            <div class="logo-container">
                <img src="logo.png" alt="3DR COMPUTEC" class="logo-principal" onerror="this.style.display='none'">
            </div>
            <h1 class="title-main">Sistema de Notas</h1>
            <p class="text-muted">Gestión profesional de ventas</p>
        </div>

        <!-- MENSAJE -->
        <?php if ($mensaje): ?>
            <div class="alert alert-success alert-dismissible fade show shadow-sm">
                <i class="bi bi-check-circle-fill me-2"></i> <?php echo $mensaje; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- FORMULARIO DETALLADO -->
        <div class="card mb-5 no-print">
            <div class="card-header">
                <i class="bi <?php echo $nota_editar ? 'bi-pencil-square' : 'bi-file-earmark-plus'; ?> me-2"></i>
                <?php echo $nota_editar ? 'Editar Nota #' . ($nota_editar['id'] ?? 'N/A') : 'Nueva Nota'; ?>
            </div>
            <div class="card-body p-4">
                <form method="POST" class="row g-3">
                    <input type="hidden" name="action" value="<?php echo $nota_editar ? 'editar' : 'crear'; ?>">
                    <?php if ($nota_editar): ?>
                        <input type="hidden" name="id" value="<?php echo $nota_editar['id']; ?>">
                    <?php endif; ?>
                    
                    <!-- BLOQUE: NOMBRE DEL CLIENTE -->
                    <div class="form-group col-md-6">
                        <label for="nombre" class="form-label"><i class="bi bi-person-circle"></i> Nombre del Cliente:</label>
                        <input type="text" name="nombre" id="nombre" class="form-control" placeholder="Ingrese el nombre completo" value="<?php echo htmlspecialchars($nota_editar['nombre'] ?? ''); ?>" required>
                        <small class="form-text text-muted">Nombre completo del cliente</small>
                    </div>
                    
                    <!-- BLOQUE: DNI -->
                    <div class="form-group col-md-6">
                        <label for="dni" class="form-label"><i class="bi bi-credit-card-2-front"></i> DNI:</label>
                        <input type="text" name="dni" id="dni" class="form-control" placeholder="Ingrese el documento de identidad" value="<?php echo htmlspecialchars($nota_editar['dni'] ?? ''); ?>" required>
                        <small class="form-text text-muted">Documento Nacional de Identidad</small>
                    </div>
                    
                    <!-- BLOQUE: TELÉFONO -->
                    <div class="form-group col-md-6">
                        <label for="numero" class="form-label"><i class="bi bi-telephone-fill"></i> Teléfono:</label>
                        <input type="text" name="numero" id="numero" class="form-control" placeholder="Ingrese número de contacto" value="<?php echo htmlspecialchars($nota_editar['numero'] ?? ''); ?>" required>
                        <small class="form-text text-muted">Número telefónico del cliente</small>
                    </div>
                    
                    <!-- BLOQUE: SERVICIO / PROBLEMA -->
                    <div class="form-group col-12">
                        <label for="servicio_problema" class="form-label"><i class="bi bi-wrench-adjustable-circle-fill"></i> Servicio / Problema:</label>
                        <textarea name="servicio_problema" id="servicio_problema" class="form-control" rows="3" placeholder="Describa el servicio o problema a detalle" required><?php echo htmlspecialchars($nota_editar['servicio_problema'] ?? ''); ?></textarea>
                        <small class="form-text text-muted">Descripción detallada del servicio o problema</small>
                    </div>
                    
                    <!-- BLOQUE: MONTO PAGADO -->
                    <div class="form-group col-4">
                        <label for="monto_pagado" class="form-label"><i class="bi bi-cash-coin text-success"></i> Monto Pagado:</label>
                        <div class="input-group">
                            <span class="input-group-text">S/</span>
                            <input type="number" name="monto_pagado" id="monto_pagado" value="<?php echo $nota_editar['monto_pagado'] ?? '0'; ?>" step="0.01" class="form-control" required>
                        </div>
                        <small class="form-text text-muted">Monto que el cliente ha pagado</small>
                    </div>
                    
                    <!-- BLOQUE: MONTO PENDIENTE -->
                    <div class="form-group col-4">
                        <label for="monto_pendiente" class="form-label"><i class="bi bi-cash-coin text-warning"></i> Monto Pendiente:</label>
                        <div class="input-group">
                            <span class="input-group-text">S/</span>
                            <input type="number" name="monto_pendiente" id="monto_pendiente" value="<?php echo $nota_editar['monto_pendiente'] ?? '0'; ?>" step="0.01" class="form-control" required>
                        </div>
                        <small class="form-text text-muted">Monto que falta por pagar</small>
                    </div>
                    
                    <!-- BLOQUE: TOTAL CALCULADO -->
                    <div class="form-group col-4">
                        <label for="total" class="form-label"><i class="bi bi-calculator-fill text-info"></i> Total Calculado:</label>
                        <div class="input-group">
                            <span class="input-group-text bg-dark text-white">S/</span>
                            <input type="text" id="total" class="form-control fw-bold" disabled value="<?php echo $nota_editar ? number_format($nota_editar['pago_total'], 2) : '0.00'; ?>">
                        </div>
                        <small class="form-text text-muted">Total automático (Pagado + Pendiente)</small>
                    </div>
                    
                    <!-- BLOQUE: ESTADO DE PAGO -->
                    <div class="form-group col-12">
                        <div class="form-check form-switch fs-5">
                            <input class="form-check-input" type="checkbox" name="pago_completado" value="1" id="pago_completado" <?php echo ($nota_editar['pago_completado'] ?? 0) ? 'checked' : ''; ?>>
                            <label class="form-check-label fw-semibold" for="pago_completado">
                                <i class="bi bi-check-circle-fill text-success"></i> ¿PAGO COMPLETADO?
                            </label>
                        </div>
                        <small class="form-text text-muted">Marque si el cliente ha completado el pago total</small>
                    </div>
                    
                    <!-- BLOQUE: POLÍTICA DE RECOJO -->
                    <div class="form-group col-12">
                        <div class="alert alert-warning border-2" role="alert">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            <strong>Política de Recojo:</strong> Todo tipo de recojo de cualquier producto en general es con boleto, sin ello no hay lugar a reclamo.
                        </div>
                    </div>
                    
                    <!-- BLOQUE: BOTÓN DE GUARDAR -->
                    <div class="form-group col-12">
                        <button type="submit" class="btn btn-success w-100 py-3">
                            <i class="bi bi-save2-fill me-2"></i>
                            <?php echo $nota_editar ? '💾 GUARDAR CAMBIOS' : '💾 GUARDAR NOTA'; ?>
                        </button>
                        <?php if ($nota_editar): ?>
                            <a href="generar_nota.php" class="btn btn-secondary w-100 mt-2">
                                <i class="bi bi-x-circle me-2"></i> Cancelar
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <!-- TABLA DE REGISTROS -->
        <div class="card no-print">
            <div class="card-header d-flex justify-content-between">
                <span><i class="bi bi-table me-2"></i> Notas Registradas</span>
                <span class="badge bg-light text-dark"><?php echo count($registros); ?> registros</span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($registros)): ?>
                    <div class="p-5 text-center text-muted">
                        <i class="bi bi-inbox fs-1"></i>
                        <p class="mt-3">No hay notas guardadas en la base de datos.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>N°</th>
                                    <th>Cliente</th>
                                    <th>DNI</th>
                                    <th>Teléfono</th>
                                    <th>Servicio</th>
                                    <th>Pagado</th>
                                    <th>Falta</th>
                                    <th>Total</th>
                                    <th>Estado</th>
                                    <th>Fecha/Hora</th>
                                    <th class="text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($registros as $r): 
                                    // Valores seguros con fallback
                                    $dni = $r['dni'] ?? '';
                                    $pago_completado = $r['pago_completado'] ?? 0;
                                ?>
                                <tr data-id="<?php echo $r['id']; ?>">
                                    <td><span class="badge-id">#<?php echo $r['id']; ?></span></td>
                                    <td><?php echo htmlspecialchars($r['nombre']); ?></td>
                                    <td><?php echo htmlspecialchars($dni); ?></td>
                                    <td><?php echo htmlspecialchars($r['numero']); ?></td>
                                    <td title="<?php echo htmlspecialchars($r['servicio_problema']); ?>">
                                        <?php echo htmlspecialchars(substr($r['servicio_problema'], 0, 30)); ?>...
                                    </td>
                                    <td class="text-success fw-bold">S/. <?php echo number_format($r['monto_pagado'], 2); ?></td>
                                    <td class="text-warning fw-bold">S/. <?php echo number_format($r['monto_pendiente'], 2); ?></td>
                                    <td class="text-dark fw-bold">S/. <?php echo number_format($r['pago_total'], 2); ?></td>
                                    <td>
                                        <?php if ($pago_completado): ?>
                                            <span class="badge bg-success"><i class="bi bi-check-circle"></i> Pagado</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark"><i class="bi bi-clock-history"></i> Pendiente</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo date('d/m/Y', strtotime($r['fecha_emision'])); ?>
                                        <br><small class="text-muted"><?php echo substr($r['hora_emision'], 0, 5); ?></small>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm">
                                            <a href="?editar=<?php echo $r['id']; ?>" class="btn btn-warning" title="Editar">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <button type="button" class="btn btn-info btn-print" data-id="<?php echo $r['id']; ?>" title="Imprimir">
                                                <i class="bi bi-printer"></i>
                                            </button>
                                            <button type="button" class="btn btn-danger btn-delete" data-id="<?php echo $r['id']; ?>" title="Eliminar">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

       <!-- IMPRESIÓN A5 COMPACTA - 1 HOJA -->
<!-- IMPRESIÓN A5 OCXMOTO - 1 HOJA GARANTIZADA -->
<div id="print-area" class="print-area">
    <div class="print-header text-center">
        <img src="logo.png" alt="OCXMOTO" class="print-logo-small">
        <h2 class="print-title mb-1">NOTA DE VENTA</h2>
    </div>

    <div class="print-info">
        <p class="mb-1"><strong>N°:</strong> #<span id="print-id"></span></p>
        <p class="mb-1"><strong>Cliente:</strong> <span id="print-nombre"></span></p>
        <p class="mb-1"><strong>DNI:</strong> <span id="print-dni"></span></p>
        <p class="mb-1"><strong>Teléfono:</strong> <span id="print-numero"></span></p>
    </div>

    <div class="print-line"></div>

    <p class="mb-1 mt-2 fw-bold">Servicio / Problema:</p>
    <div id="print-servicio" class="print-servicio-box mb-2"></div>

    <div class="print-line"></div>

    <!-- Estado de pago -->
    <div id="print-pago-status" class="print-pago-status mb-2"></div>

    <!-- Montos -->
    <div class="row text-center mb-2 g-1">
        <div class="col-6">
            <p class="mb-0 small text-success fw-bold">PAGADO</p>
            <p class="fs-5 fw-bold text-success mb-0">S/. <span id="print-pagado"></span></p>
        </div>
        <div class="col-6">
            <p class="mb-0 small text-warning fw-bold">FALTA</p>
            <p class="fs-5 fw-bold text-warning mb-0">S/. <span id="print-pendiente"></span></p>
        </div>
    </div>

    <div class="print-total mb-2">
        TOTAL: S/. <span id="print-total"></span>
    </div>

    <!-- Política de recojo -->
    <div class="print-policy mb-2">
        TODO RECOJO  DE CUALQUIER PRODUCTO EN GENERAL ES CON SU BOLETA SIN ELLO NO HAY LUGAR AL RECLAMO
    </div>

    <p class="text-center mb-1 small">
        <strong>Fecha:</strong> <span id="print-fecha"></span> | 
        <strong>Hora:</strong> <span id="print-hora"></span>
    </p>

    <div class="print-validez">
        VIGENCIA: 30 DÍAS
    </div>

    <p class="text-center text-muted fst-italic mt-2" style="font-size: 0.7rem;">
        Gracias por preferir COMPUTEC
    </p>
</div>

    <!-- MODAL ELIMINAR -->
    <div class="modal fade" id="modalEliminar" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="bi bi-trash3-fill me-2"></i>Confirmar Eliminación</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>¿Está seguro de eliminar la nota #<span id="delete-id"></span>?</p>
                    <p class="text-danger"><small>⚠️ Esta acción no se puede deshacer.</small></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle"></i> Cancelar
                    </button>
                    <a href="#" id="btnConfirmarEliminar" class="btn btn-danger">
                        <i class="bi bi-trash3"></i> Sí, Eliminar
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Cálculo automático del total
        document.querySelectorAll('[name="monto_pagado"], [name="monto_pendiente"]').forEach(el => {
            el.addEventListener('input', () => {
                const p = parseFloat(document.querySelector('[name="monto_pagado"]').value) || 0;
                const f = parseFloat(document.querySelector('[name="monto_pendiente"]').value) || 0;
                document.getElementById('total').value = (p + f).toFixed(2);
            });
        });

        // Función de impresión
        document.querySelectorAll('.btn-print').forEach(btn => {
            btn.addEventListener('click', function() {
                const id = this.dataset.id;
                const row = document.querySelector(`tr[data-id="${id}"]`);
                if (!row) {
                    alert('❌ Error: No se encontró la fila de datos');
                    return;
                }
                
                const c = row.cells;
                
                // Rellenar datos de impresión
                document.getElementById('print-id').textContent = c[0].textContent.replace('#', '');
                document.getElementById('print-nombre').textContent = c[1].textContent;
                document.getElementById('print-dni').textContent = c[2].textContent;
                document.getElementById('print-numero').textContent = c[3].textContent;
                document.getElementById('print-servicio').textContent = c[4].title;
                document.getElementById('print-pagado').textContent = c[5].textContent.replace('S/. ', '');
                document.getElementById('print-pendiente').textContent = c[6].textContent.replace('S/. ', '');
                document.getElementById('print-total').textContent = c[7].textContent.replace('S/. ', '');
                document.getElementById('print-fecha').textContent = c[9].children[0].textContent;
                document.getElementById('print-hora').textContent = c[9].querySelector('small').textContent;
                
                // Estado de pago
                const statusEl = document.getElementById('print-pago-status');
                const estado = c[8].innerHTML.includes('Pagado') ? 'completado' : 'pendiente';
                if (estado === 'completado') {
                    statusEl.className = 'print-pago-status pago-completado';
                    statusEl.innerHTML = '<i class="bi bi-check-circle-fill"></i> PAGO COMPLETADO';
                } else {
                    statusEl.className = 'print-pago-status pago-pendiente';
                    statusEl.innerHTML = '<i class="bi bi-clock-history"></i> PAGO PENDIENTE';
                }
                
                setTimeout(() => window.print(), 100);
            });
        });

        // Función de eliminación
        document.querySelectorAll('.btn-delete').forEach(btn => {
            btn.addEventListener('click', function() {
                const id = this.dataset.id;
                document.getElementById('delete-id').textContent = id;
                document.getElementById('btnConfirmarEliminar').href = `?eliminar=${id}`;
                new bootstrap.Modal(document.getElementById('modalEliminar')).show();
            });
        });
    </script>
</body>
</html>
<?php
// exportar_excel.php
// Genera Excel basado en la misma lógica de crear_pdf.php
// Guarda copia en /reports/ y fuerza descarga al navegador

require_once 'db.php'; // debe definir $pdo y funciones fetchOne/fetchAll si las usas
require __DIR__ . '/vendor/autoload.php'; // PhpSpreadsheet

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

if (session_status() === PHP_SESSION_NONE) session_start();

// -----------------------------
// Helper: formato dinero para celdas
// -----------------------------
function excelMoneyFormat() {
    // Formato con símbolo S/. y 2 decimales
    return '"S/ " #,##0.00;[Red]-"S/ " #,##0.00';
}

// -----------------------------
// Obtener caja activa (misma lógica que crear_pdf.php)
// -----------------------------
$caja_activa = null;
if (isset($_SESSION['caja_id'])) {
    $caja_activa = fetchOne("SELECT * FROM cajas WHERE id = ? AND estado = 'abierta'", [$_SESSION['caja_id']]);
}
if (!$caja_activa) {
    $hoy = date('Y-m-d');
    $caja_activa = fetchOne("SELECT * FROM cajas WHERE fecha = ? AND estado = 'abierta' ORDER BY id DESC LIMIT 1", [$hoy]);
    if ($caja_activa) $_SESSION['caja_id'] = $caja_activa['id'];
}
if (!$caja_activa) {
    die("No hay caja activa para exportar.");
}
$caja_id = $caja_activa['id'];

// -----------------------------
// Consultas (manteniendo la misma lógica que usarías en crear_pdf.php)
// -----------------------------

// 1) Todas las ventas de la caja
$ventas = fetchAll(
    "SELECT v.*, tp.nombre as tipo_pago, pv.nombre as punto_venta
     FROM ventas v
     LEFT JOIN tipos_pago tp ON v.tipo_pago_id = tp.id
     LEFT JOIN puntos_venta pv ON v.punto_venta_id = pv.id
     WHERE v.caja_id = ?
     ORDER BY v.fecha ASC",
    [$caja_id]
);

// 2) Totales generales
$stats = fetchOne(
    "SELECT 
        COUNT(*) as num_transacciones,
        COALESCE(SUM(total_entrada), 0) as total_entrada,
        COALESCE(SUM(total_salida), 0) as total_salida,
        COALESCE(SUM(saldo), 0) as saldo_total
     FROM ventas WHERE caja_id = ?",
    [$caja_id]
) ?: ['num_transacciones'=>0,'total_entrada'=>0,'total_salida'=>0,'saldo_total'=>0];

// 3) Totales por tipo de pago
$totales_tipo_pago = fetchAll(
    "SELECT tp.id, tp.nombre, COALESCE(SUM(v.saldo),0) as saldo, COALESCE(SUM(v.total_entrada),0) as entrada, COALESCE(SUM(v.total_salida),0) as salida
     FROM tipos_pago tp
     LEFT JOIN ventas v ON tp.id = v.tipo_pago_id AND v.caja_id = ?
     WHERE tp.activo = 1
     GROUP BY tp.id, tp.nombre
     ORDER BY tp.nombre",
    [$caja_id]
);

// 4) Totales por punto de venta
$totales_punto_venta = fetchAll(
    "SELECT pv.id, pv.nombre, COALESCE(SUM(v.saldo),0) as saldo, COALESCE(SUM(v.total_entrada),0) as entrada, COALESCE(SUM(v.total_salida),0) as salida
     FROM puntos_venta pv
     LEFT JOIN ventas v ON pv.id = v.punto_venta_id AND v.caja_id = ?
     WHERE pv.activo = 1
     GROUP BY pv.id, pv.nombre
     ORDER BY pv.nombre",
    [$caja_id]
);

// 5) Resumen SOLO EFECTIVO (tipo_pago_id = 1). Para Resumen en hoja principal consideramos excluir agente = 5 donde corresponde.
$efectivo = fetchOne(
    "SELECT 
        COALESCE(SUM(total_entrada),0) as entrada,
        COALESCE(SUM(total_salida),0) as salida,
        COALESCE(SUM(saldo),0) as saldo
     FROM ventas WHERE caja_id = ? AND tipo_pago_id = 1",
    [$caja_id]
) ?: ['entrada'=>0,'salida'=>0,'saldo'=>0];

$saldo_inicial = floatval($caja_activa['saldo_inicial'] ?? 0);
$total_entrada_efectivo = floatval($efectivo['entrada']);
$total_salida_efectivo = floatval($efectivo['salida']);

// Para los cálculos "solo efectivo sin agente" (punto_venta_id != 5)
$stmt = $pdo->prepare("SELECT COALESCE(SUM(total_entrada),0) as entrada, COALESCE(SUM(total_salida),0) as salida FROM ventas WHERE caja_id = ? AND tipo_pago_id = 1 AND punto_venta_id != 5");
$stmt->execute([$caja_id]);
$efectivo_sin_agente = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['entrada'=>0,'salida'=>0];
$total_entrada_efectivo_sin_agente = floatval($efectivo_sin_agente['entrada']);
$total_salida_efectivo_sin_agente = floatval($efectivo_sin_agente['salida']);
$saldo_efectivo_calculado = $saldo_inicial + ($total_entrada_efectivo_sin_agente - $total_salida_efectivo_sin_agente);

// 6) Conteo de monedas (desde la tabla)
$stmt = $pdo->prepare("SELECT denominacion, cantidad, total FROM conteo_monedas WHERE caja_id = ? ORDER BY denominacion DESC");
$stmt->execute([$caja_id]);
$conteo_monedas = $stmt->fetchAll(PDO::FETCH_ASSOC);
$total_conteo = array_sum(array_column($conteo_monedas, 'total'));

// 7) Desbalance (conteo - saldo esperado en efectivo sin agente)
$desbalance = $total_conteo - $saldo_efectivo_calculado;

// 8) Agente (ventas de punto_venta_id = 5)
$agente = fetchAll(
    "SELECT v.*, tp.nombre as tipo_pago
     FROM ventas v
     LEFT JOIN tipos_pago tp ON v.tipo_pago_id = tp.id
     WHERE v.caja_id = ? AND v.punto_venta_id = 5
     ORDER BY v.fecha ASC",
    [$caja_id]
);

// 9) Otros pagos (no efectivo y no agente)
$otros = fetchAll(
    "SELECT v.*, tp.nombre as tipo_pago, pv.nombre as punto_venta
     FROM ventas v
     LEFT JOIN tipos_pago tp ON v.tipo_pago_id = tp.id
     LEFT JOIN puntos_venta pv ON v.punto_venta_id = pv.id
     WHERE v.caja_id = ? AND v.tipo_pago_id != 1 AND v.punto_venta_id != 5
     ORDER BY v.fecha ASC",
    [$caja_id]
);

// -----------------------------
// Crear Spreadsheet y estilos
// -----------------------------
$spreadsheet = new Spreadsheet();
$spreadsheet->getProperties()->setCreator('Sistema de Caja')->setTitle("Reporte Caja {$caja_id}");

// Estilos reutilizables
$headerStyle = [
    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4F81BD']],
    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
];
$subHeaderStyle = [
    'font' => ['bold' => true],
    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'EFEFEF']],
    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
];
$tableBorder = ['borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]];

// -----------------------------
// Hoja 1: Resumen — Solo Efectivo
// -----------------------------
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('1_ResumenEfectivo');

// Título grande
$sheet->mergeCells('A1:G1');
$sheet->setCellValue('A1', 'REPORTE DE CAJA — RESUMEN (SOLO EFECTIVO)');
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
$sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

// Fecha y caja
$sheet->setCellValue('A2', 'Fecha del reporte:');
$sheet->setCellValue('B2', date('d/m/Y H:i'));
$sheet->setCellValue('A3', 'Caja ID:');
$sheet->setCellValue('B3', $caja_id);

// Encabezado resumen
$sheet->fromArray([[
    'Saldo Inicial (efectivo)',
    'Entradas (efectivo sin agente)',
    'Salidas (efectivo sin agente)',
    'Saldo Esperado (efectivo)',
    'Conteo Real (monedas)',
    'Desbalance'
]], null, 'A5');
$sheet->getStyle('A5:F5')->applyFromArray($headerStyle);

// Valores
$sheet->fromArray([[
    $saldo_inicial,
    $total_entrada_efectivo_sin_agente,
    $total_salida_efectivo_sin_agente,
    $saldo_efectivo_calculado,
    $total_conteo,
    $desbalance
]], null, 'A6');

// Formateo monetario y bordes
foreach (['A6','B6','C6','D6','E6','F6'] as $cell) {
    $sheet->getStyle($cell)->getNumberFormat()->setFormatCode(excelMoneyFormat());
}
$sheet->getStyle('A5:F6')->applyFromArray($tableBorder);

// -----------------------------
// Hoja 2: Copia Precaución (mismo resumen, con distinto color)
// -----------------------------
$sheet2 = $spreadsheet->createSheet();
$sheet2->setTitle('2_Copia_Precaucion');

$sheet2->mergeCells('A1:G1');
$sheet2->setCellValue('A1', 'COPIA DE RESPALDO — RESUMEN (SOLO EFECTIVO)');
$sheet2->getStyle('A1')->getFont()->setBold(true)->setSize(14);
$sheet2->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

// Encabezado y datos (igual que hoja 1)
$sheet2->fromArray([['Campo','Valor']], null, 'A3');
$sheet2->getStyle('A3:B3')->applyFromArray($headerStyle);

$sheet2->fromArray([
    ['Fecha', date('d/m/Y H:i')],
    ['Caja ID', $caja_id],
    ['Saldo Inicial', $saldo_inicial],
    ['Entradas (efectivo sin agente)', $total_entrada_efectivo_sin_agente],
    ['Salidas (efectivo sin agente)', $total_salida_efectivo_sin_agente],
    ['Saldo Esperado (efectivo)', $saldo_efectivo_calculado],
    ['Conteo Real (monedas)', $total_conteo],
    ['Desbalance', $desbalance]
], null, 'A4');

$sheet2->getStyle('B4:B11')->getNumberFormat()->setFormatCode(excelMoneyFormat());
$sheet2->getStyle('A3:B11')->applyFromArray($tableBorder);
$sheet2->getStyle('A3:A11')->getFont()->setBold(true);
$sheet2->getStyle('A3:B3')->applyFromArray(['fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'6AA84F']], 'font'=>['color'=>['rgb'=>'FFFFFF']]]);

// -----------------------------
// Hoja 3: Conteo de Monedas (separada)
// -----------------------------
$sheet3 = $spreadsheet->createSheet();
$sheet3->setTitle('3_ConteoMonedas');

$sheet3->mergeCells('A1:C1');
$sheet3->setCellValue('A1', 'CONTEO DE MONEDAS Y BILLETES');
$sheet3->getStyle('A1')->getFont()->setBold(true)->setSize(14);
$sheet3->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$sheet3->fromArray([['Denominación','Cantidad','Total']], null, 'A3');
$sheet3->getStyle('A3:C3')->applyFromArray($headerStyle);

$row = 4;
foreach ($conteo_monedas as $m) {
    $sheet3->setCellValue("A{$row}", $m['denominacion']);
    $sheet3->setCellValue("B{$row}", intval($m['cantidad']));
    $sheet3->setCellValue("C{$row}", floatval($m['total']));
    $sheet3->getStyle("C{$row}")->getNumberFormat()->setFormatCode(excelMoneyFormat());
    $row++;
}
$sheet3->setCellValue("A{$row}", 'TOTAL');
$sheet3->setCellValue("C{$row}", $total_conteo);
$sheet3->getStyle("A{$row}:C{$row}")->getFont()->setBold(true);
$sheet3->getStyle("A3:C{$row}")->applyFromArray($tableBorder);
foreach (['A','B','C'] as $col) $sheet3->getColumnDimension($col)->setAutoSize(true);

// -----------------------------
// Hoja 4: Totales por Punto de Venta
// -----------------------------
$sheet4 = $spreadsheet->createSheet();
$sheet4->setTitle('4_PuntosVenta');
$sheet4->mergeCells('A1:D1');
$sheet4->setCellValue('A1', 'TOTALES POR PUNTO DE VENTA');
$sheet4->getStyle('A1')->getFont()->setBold(true)->setSize(14);
$sheet4->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$sheet4->fromArray([['Punto de Venta','Entrada','Salida','Saldo']], null, 'A3');
$sheet4->getStyle('A3:D3')->applyFromArray($headerStyle);

$r = 4;
foreach ($totales_punto_venta as $p) {
    $sheet4->setCellValue("A{$r}", $p['nombre']);
    $sheet4->setCellValue("B{$r}", floatval($p['entrada']));
    $sheet4->setCellValue("C{$r}", floatval($p['salida']));
    $sheet4->setCellValue("D{$r}", floatval($p['saldo']));
    $sheet4->getStyle("B{$r}:D{$r}")->getNumberFormat()->setFormatCode(excelMoneyFormat());
    $r++;
}
$sheet4->getStyle("A3:D" . ($r-1))->applyFromArray($tableBorder);
foreach (['A','B','C','D'] as $col) $sheet4->getColumnDimension($col)->setAutoSize(true);

// -----------------------------
// Hoja 5: Ventas del Agente (punto_venta_id = 5)
// -----------------------------
$sheet5 = $spreadsheet->createSheet();
$sheet5->setTitle('5_Agente');
$sheet5->mergeCells('A1:F1');
$sheet5->setCellValue('A1', 'VENTAS — PUNTO AGENTE (ID = 5)');
$sheet5->getStyle('A1')->getFont()->setBold(true)->setSize(14);
$sheet5->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$sheet5->fromArray([['Fecha','Descripción','Cantidad','Entrada','Salida','Tipo Pago']], null, 'A3');
$sheet5->getStyle('A3:F3')->applyFromArray($headerStyle);

$r = 4;
foreach ($agente as $a) {
    $sheet5->setCellValue("A{$r}", date('d/m/Y H:i', strtotime($a['fecha'])));
    $sheet5->setCellValue("B{$r}", $a['descripcion']);
    $sheet5->setCellValue("C{$r}", intval($a['cantidad']));
    $sheet5->setCellValue("D{$r}", floatval($a['total_entrada']));
    $sheet5->setCellValue("E{$r}", floatval($a['total_salida']));
    $sheet5->setCellValue("F{$r}", $a['tipo_pago']);
    $sheet5->getStyle("D{$r}:E{$r}")->getNumberFormat()->setFormatCode(excelMoneyFormat());
    $r++;
}
$sheet5->getStyle("A3:F" . ($r-1))->applyFromArray($tableBorder);
foreach (range('A','F') as $col) $sheet5->getColumnDimension($col)->setAutoSize(true);

// -----------------------------
// Hoja 6: Otros Tipos de Pago (no efectivo y no agente)
// -----------------------------
$sheet6 = $spreadsheet->createSheet();
$sheet6->setTitle('6_OtrosPagos');
$sheet6->mergeCells('A1:G1');
$sheet6->setCellValue('A1', 'OTROS TIPOS DE PAGO (NO EFECTIVO, NO AGENTE)');
$sheet6->getStyle('A1')->getFont()->setBold(true)->setSize(14);
$sheet6->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$sheet6->fromArray([['Fecha','Descripción','Entrada','Salida','Saldo','Tipo Pago','Punto Venta']], null, 'A3');
$sheet6->getStyle('A3:G3')->applyFromArray($headerStyle);

$r = 4;
foreach ($otros as $o) {
    $sheet6->setCellValue("A{$r}", date('d/m/Y H:i', strtotime($o['fecha'])));
    $sheet6->setCellValue("B{$r}", $o['descripcion']);
    $sheet6->setCellValue("C{$r}", floatval($o['total_entrada']));
    $sheet6->setCellValue("D{$r}", floatval($o['total_salida']));
    $sheet6->setCellValue("E{$r}", floatval($o['saldo']));
    $sheet6->setCellValue("F{$r}", $o['tipo_pago']);
    $sheet6->setCellValue("G{$r}", $o['punto_venta']);
    $sheet6->getStyle("C{$r}:E{$r}")->getNumberFormat()->setFormatCode(excelMoneyFormat());
    $r++;
}
$sheet6->getStyle("A3:G" . ($r-1))->applyFromArray($tableBorder);
foreach (range('A','G') as $col) $sheet6->getColumnDimension($col)->setAutoSize(true);

// -----------------------------
// Hoja 7: Todas las Ventas (completa)
// -----------------------------
$sheet7 = $spreadsheet->createSheet();
$sheet7->setTitle('7_TodasVentas');
$sheet7->mergeCells('A1:G1');
$sheet7->setCellValue('A1', 'TODAS LAS VENTAS - DETALLE');
$sheet7->getStyle('A1')->getFont()->setBold(true)->setSize(14);
$sheet7->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

$sheet7->fromArray([['Fecha','Descripción','Cantidad','Entrada','Salida','Tipo Pago','Punto Venta']], null, 'A3');
$sheet7->getStyle('A3:G3')->applyFromArray($headerStyle);

$r = 4;
foreach ($ventas as $v) {
    $sheet7->setCellValue("A{$r}", date('d/m/Y H:i', strtotime($v['fecha'])));
    $sheet7->setCellValue("B{$r}", $v['descripcion'] ?? '-');
    $sheet7->setCellValue("C{$r}", intval($v['cantidad']));
    $sheet7->setCellValue("D{$r}", floatval($v['total_entrada']));
    $sheet7->setCellValue("E{$r}", floatval($v['total_salida']));
    $sheet7->setCellValue("F{$r}", $v['tipo_pago'] ?? '-');
    $sheet7->setCellValue("G{$r}", $v['punto_venta'] ?? '-');
    $sheet7->getStyle("D{$r}:E{$r}")->getNumberFormat()->setFormatCode(excelMoneyFormat());
    $r++;
}
$sheet7->getStyle("A3:G" . ($r-1))->applyFromArray($tableBorder);
foreach (range('A','G') as $col) $sheet7->getColumnDimension($col)->setAutoSize(true);

// -----------------------------
// Guardar archivo en /reports y forzar descarga
// -----------------------------
$filename = "reporte_caja_{$caja_id}_" . date('Ymd_His') . ".xlsx";
$reportsDir = __DIR__ . '/reports';

// Crear carpeta si no existe
if (!is_dir($reportsDir)) {
    mkdir($reportsDir, 0755, true);
}

$filepath = $reportsDir . '/' . $filename;
$writer = new Xlsx($spreadsheet);

// Guardar copia en servidor
try {
    $writer->save($filepath);
} catch (Exception $e) {
    // Si falla guardado en servidor, continuamos y forzamos envío en memoria
}

// Forzar descarga al navegador
// Enviamos el archivo guardado (si existe) para evitar problemas de memoria en grandes reportes
if (file_exists($filepath)) {
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($filepath));
    readfile($filepath);
    exit;
} else {
    // Fallback: enviar desde memoria
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $writer->save('php://output');
    exit;
}

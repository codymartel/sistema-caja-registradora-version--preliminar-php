<?php
// crear_pdf.php - PDF de Cierre de Caja con Medios de Pago y Observaciones
if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'db.php';
require_once 'vendor/autoload.php'; // TCPDF o FPDF (usa TCPDF recomendado)

use TCPDF;

if (!isset($_SESSION['caja_id'])) {
    die("Error: No hay caja abierta.");
}

$caja_id = $_SESSION['caja_id'];

// Obtener datos de la caja
$caja = fetchOne("SELECT * FROM cajas WHERE id = ? AND estado = 'abierta'", [$caja_id]);
if (!$caja) {
    die("Error: Caja no encontrada o ya cerrada.");
}

// Obtener transacciones del día
$transacciones = fetchAll("
    SELECT 
        v.*,
        tp.nombre AS tipo_pago,
        pv.nombre AS punto_venta,
        mp.nombre AS medio_pago,
        mp.color AS medio_color
    FROM ventas v
    LEFT JOIN tipos_pago tp ON v.tipo_pago_id = tp.id
    LEFT JOIN puntos_venta pv ON v.punto_venta_id = pv.id
    LEFT JOIN medios_pago mp ON v.medio_pago_id = mp.id
    WHERE v.caja_id = ?
    ORDER BY v.fecha ASC
", [$caja_id]);

// Estadísticas
$stats = fetchOne("
    SELECT 
        COALESCE(SUM(total_entrada), 0) AS total_entrada,
        COALESCE(SUM(total_salida), 0) AS total_salida,
        COALESCE(SUM(saldo), 0) AS saldo_total
    FROM ventas WHERE caja_id = ?
", [$caja_id]);

$saldo_final = $caja['saldo_inicial'] + $stats['saldo_total'];

// Totales por medio de pago
$totales_medios = fetchAll("
    SELECT 
        mp.nombre,
        mp.color,
        COALESCE(SUM(v.saldo), 0) AS total
    FROM medios_pago mp
    LEFT JOIN ventas v ON mp.id = v.medio_pago_id AND v.caja_id = ?
    WHERE mp.activo = 1
    GROUP BY mp.id, mp.nombre, mp.color
    ORDER BY mp.nombre
", [$caja_id]);

// === INICIAR PDF CON TCPDF ===
class MYPDF extends TCPDF {
    public function Header() {
        $this->SetFont('helvetica', 'B', 12);
        $this->Cell(0, 10, 'DOCTOR COMPUTEC - REPORTE DE CAJA', 0, 1, 'C');
        $this->Ln(2);
    }
    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->Cell(0, 10, 'Página ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, 0, 'C');
    }
}

$pdf = new MYPDF('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetCreator('Doctor Computec');
$pdf->SetAuthor('Sistema de Caja');
$pdf->SetTitle('Reporte de Caja - ' . date('d-m-Y'));
$pdf->SetMargins(10, 20, 10);
$pdf->SetAutoPageBreak(TRUE, 15);
$pdf->AddPage();

// === ENCABEZADO ===
$pdf->SetFont('helvetica', 'B', 14);
$pdf->Cell(0, 10, 'REPORTE DE CIERRE DE CAJA', 0, 1, 'C');
$pdf->Ln(5);

$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(50, 6, 'Fecha de Caja:', 0, 0);
$pdf->Cell(0, 6, date('d/m/Y', strtotime($caja['fecha'])), 0, 1);
$pdf->Cell(50, 6, 'Hora de Apertura:', 0, 0);
$pdf->Cell(0, 6, date('H:i', strtotime($caja['created_at'] ?? $caja['fecha'])), 0, 1);
$pdf->Cell(50, 6, 'Saldo Inicial:', 0, 0);
$pdf->Cell(0, 6, 'S/ ' . number_format($caja['saldo_inicial'], 2), 0, 1);
$pdf->Ln(5);

// === TABLA DE TRANSACCIONES ===
$pdf->SetFont('helvetica', 'B', 9);
$pdf->SetFillColor(230, 230, 230);
$header = ['#', 'Hora', 'Descripción', 'Cant.', 'P.Unit', 'Entrada', 'Salida', 'Saldo', 'Tipo Pago', 'Punto Venta', 'Medio Pago', 'Observación'];
$w = [10, 15, 40, 12, 15, 18, 18, 18, 25, 25, 25, 40];
foreach ($header as $i => $h) {
    $pdf->Cell($w[$i], 7, $h, 1, 0, 'C', true);
}
$pdf->Ln();

$pdf->SetFont('helvetica', '', 8);
$saldo_acum = $caja['saldo_inicial'];
$i = 1;
foreach ($transacciones as $t) {
    $entrada = $t['total_entrada'] ?? 0;
    $salida = $t['total_salida'] ?? 0;
    $saldo_acum += $entrada - $salida;
    $precio_unit = $t['cantidad'] > 0 ? $entrada / $t['cantidad'] : 0;

    $pdf->Cell($w[0], 6, $i++, 1);
    $pdf->Cell($w[1], 6, date('H:i', strtotime($t['fecha'])), 1);
    $pdf->Cell($w[2], 6, substr($t['descripcion'], 0, 25), 1);
    $pdf->Cell($w[3], 6, $t['cantidad'], 1, 0, 'C');
    $pdf->Cell($w[4], 6, number_format($precio_unit, 2), 1, 0, 'R');
    $pdf->Cell($w[5], 6, number_format($entrada, 2), 1, 0, 'R');
    $pdf->Cell($w[6], 6, number_format($salida, 2), 1, 0, 'R');
    $pdf->Cell($w[7], 6, number_format($saldo_acum, 2), 1, 0, 'R');
    $pdf->Cell($w[8], 6, substr($t['tipo_pago'] ?? '-', 0, 12), 1);
    $pdf->Cell($w[9], 6, substr($t['punto_venta'] ?? '-', 0, 12), 1);
    
    // Medio de pago con color
    $medio = $t['medio_pago'] ?? 'Sin medio';
    $color = $t['medio_color'] ?? '#ffffff';
    list($r, $g, $b) = sscanf($color, "#%02x%02x%02x");
    $pdf->SetFillColor($r, $g, $b);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->Cell($w[10], 6, substr($medio, 0, 12), 1, 0, 'C', true);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFillColor(255, 255, 255);

    // Observación
    $obs = $t['observacion'] ?? '';
    $pdf->Cell($w[11], 6, substr($obs, 0, 20), 1, 1);
}

// === RESUMEN GENERAL ===
$pdf->Ln(8);
$pdf->SetFont('helvetica', 'B', 11);
$pdf->SetFillColor(240, 240, 240);
$pdf->Cell(80, 8, 'RESUMEN GENERAL', 1, 1, 'C', true);

$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(80, 7, 'Entrada Total:', 1);
$pdf->Cell(40, 7, 'S/ ' . number_format($stats['total_entrada'], 2), 1, 1, 'R');
$pdf->Cell(80, 7, 'Salida Total:', 1);
$pdf->Cell(40, 7, 'S/ ' . number_format($stats['total_salida'], 2), 1, 1, 'R');
$pdf->Cell(80, 7, 'Saldo Final:', 1);
$pdf->Cell(40, 7, 'S/ ' . number_format($saldo_final, 2), 1, 1, 'R');

// === RESUMEN POR MEDIO DE PAGO ===
$pdf->Ln(8);
$pdf->SetFont('helvetica', 'B', 11);
$pdf->SetFillColor(200, 230, 255);
$pdf->Cell(0, 8, 'RESUMEN POR MEDIO DE PAGO', 1, 1, 'C', true);

$pdf->SetFont('helvetica', 'B', 9);
$pdf->Cell(80, 7, 'Medio de Pago', 1);
$pdf->Cell(40, 7, 'Total', 1, 1, 'R');

$pdf->SetFont('helvetica', '', 9);
foreach ($totales_medios as $mp) {
    list($r, $g, $b) = sscanf($mp['color'], "#%02x%02x%02x");
    $pdf->SetFillColor($r, $g, $b);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->Cell(80, 7, $mp['nombre'], 1, 0, 'L', true);
    $pdf->Cell(40, 7, 'S/ ' . number_format($mp['total'], 2), 1, 1, 'R');
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFillColor(255, 255, 255);
}

// === FIRMA ===
$pdf->Ln(15);
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(90, 10, '_________________________', 0, 0, 'C');
$pdf->Cell(90, 10, '_________________________', 0, 1, 'C');
$pdf->Cell(90, 6, 'Firma del Responsable', 0, 0, 'C');
$pdf->Cell(90, 6, 'Firma del Supervisor', 0, 1, 'C');

// === SALIDA DEL PDF ===
$pdf->Output('reporte_caja_' . date('d-m-Y') . '.pdf', 'I');
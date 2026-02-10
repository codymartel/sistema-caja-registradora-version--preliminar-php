<?php
// Quita límite de tiempo por si toma unos segundos
set_time_limit(0);

// Ejecuta scripts que generan PDF y Excel
$output_pdf = shell_exec("php crear_pdf.php 2>&1");
$output_excel = shell_exec("php exportar_excel.php 2>&1");

// Muestra confirmación
echo "✅ Reportes actualizados a las " . date("H:i:s") . "\n";
echo "PDF: " . substr($output_pdf, 0, 100) . "\n";
echo "Excel: " . substr($output_excel, 0, 100) . "\n";
?>

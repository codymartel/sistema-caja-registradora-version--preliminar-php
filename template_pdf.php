<?php
// template_pdf.php
function formatMoney($num) {
  return number_format($num, 2, '.', ',');
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Reporte de Caja</title>
  <style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 12px; margin: 20px; color: #333; }
    h2 { text-align: center; margin-bottom: 20px; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
    th, td { border: 1px solid #bbb; padding: 6px; }
    th { background: #f4f4f4; }
    .text-end { text-align: right; }
    .text-center { text-align: center; }
    .fw-bold { font-weight: bold; }
  </style>
</head>
<body>

<h2>Reporte de Caja - <?= date('d/m/Y') ?></h2>

<!-- Aquí pones todo el contenido que ya tienes del index -->
<!-- Por Tipo de Pago, Punto de Venta, Totales Generales, Conteo de Monedas, etc. -->
<!-- Puedes copiar y pegar tu HTML del index directamente -->
<?= $html_tablas ?>

</body>
</html>

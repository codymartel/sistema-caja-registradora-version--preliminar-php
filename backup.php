<?php
// 📦 Configuración de conexión
$host = 'localhost';
$user = 'root';
$pass = '';
$dbname = 'sistema_caja'; // 🔹 Cambia si tu base tiene otro nombre

// 📅 Nombre del archivo con fecha
$backupFile = 'respaldo_' . $dbname . '_' . date('Y-m-d_H-i-s') . '.sql';

// 📂 Carpeta donde guardar el backup
$backupPath = __DIR__ . '/respaldos/';

// Crear carpeta si no existe
if (!is_dir($backupPath)) {
    mkdir($backupPath, 0777, true);
}

// 📤 Comando para exportar la base de datos
$command = "\"C:\\xampp\\mysql\\bin\\mysqldump.exe\" -h $host -u $user " .
    ($pass ? "-p$pass " : "") . "$dbname > \"$backupPath$backupFile\"";

// Ejecutar el comando
system($command, $output);

// 📢 Verificación
if (file_exists($backupPath . $backupFile)) {
    echo "<h3 style='color:green;text-align:center;'>✅ Respaldo creado con éxito</h3>";
    echo "<p style='text-align:center;'>
            Archivo: <strong>$backupFile</strong><br>
            Carpeta: <em>$backupPath</em><br><br>
            <a href='index.php'>⬅️ Volver al panel</a>
          </p>";
} else {
    echo "<h3 style='color:red;text-align:center;'>❌ Error al crear respaldo</h3>";
    echo "<p style='text-align:center;'>Verifica la ruta de MySQL o los permisos de la carpeta.</p>";
}
?>
w
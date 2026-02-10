<?php
require 'db.php';

$id = $_POST['id'] ?? 0;
$nombre = $_POST['nombre'] ?? '';
$categoria = $_POST['categoria'] ?? '';
$precio = $_POST['precio'] ?? 0;
$stock = $_POST['stock'] ?? 0;

// Configuración para upload de imágenes
$uploadDir = 'uploads/';
$imagenNombre = '';

// Crear directorio si no existe
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Procesar imagen si se subió
if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
    $fileTmpPath = $_FILES['imagen']['tmp_name'];
    $fileName = $_FILES['imagen']['name'];
    $fileSize = $_FILES['imagen']['size'];
    $fileType = $_FILES['imagen']['type'];
    
    // Validar tipo de archivo
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (in_array($fileType, $allowedTypes)) {
        
        // Validar tamaño (máximo 5MB)
        if ($fileSize <= 5 * 1024 * 1024) {
            
            // Generar nombre único
            $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);
            $imagenNombre = uniqid() . '.' . $fileExtension;
            $dest_path = $uploadDir . $imagenNombre;
            
            if (move_uploaded_file($fileTmpPath, $dest_path)) {
                // Imagen subida correctamente
            } else {
                echo "Error al subir la imagen";
                $imagenNombre = '';
            }
        } else {
            echo "La imagen es demasiado grande (máximo 5MB)";
        }
    } else {
        echo "Tipo de archivo no permitido";
    }
}

if ($id) {
    if (!empty($imagenNombre)) {
        // Actualizar con nueva imagen
        query("UPDATE productos SET nombre = ?, categoria = ?, precio = ?, stock = ?, imagen = ? WHERE id = ?", 
              [$nombre, $categoria, $precio, $stock, $imagenNombre, $id]);
    } else {
        // Mantener imagen existente
        query("UPDATE productos SET nombre = ?, categoria = ?, precio = ?, stock = ? WHERE id = ?", 
              [$nombre, $categoria, $precio, $stock, $id]);
    }
}
?>
<?php
require 'db.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Gestor de Productos - Sistema POS</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
  <style>
    :root {
      --primary-color: #2563eb;
      --success-color: #10b981;
      --warning-color: #f59e0b;
      --danger-color: #ef4444;
      --dark-color: #1f2937;
      --light-color: #f8fafc;
      --indigo-color: #4f46e5;
      --pink-color: #ec4899;
      --teal-color: #14b8a6;
      --purple-color: #a855f7;
      --orange-color: #f97316;
      --cyan-color: #06b6d4;
      --red-color: #ef4444;
      --gray-color: #6b7280;
    }
   
    body {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      min-height: 100vh;
    }
   
    .glass-card {
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(10px);
      border-radius: 20px;
      border: 1px solid rgba(255, 255, 255, 0.2);
      box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
    }
   
    .nav-glass {
      background: rgba(255, 255, 255, 0.9) !important;
      backdrop-filter: blur(10px);
      border-bottom: 1px solid rgba(255, 255, 255, 0.2);
    }
   
    .stats-card {
      background: linear-gradient(135deg, var(--primary-color), #3b82f6);
      color: white;
      border-radius: 16px;
      border: none;
      transition: transform 0.3s ease;
    }
   
    .stats-card:hover {
      transform: translateY(-5px);
    }
   
    .stats-card.success {
      background: linear-gradient(135deg, var(--success-color), #34d399);
    }
   
    .stats-card.warning {
      background: linear-gradient(135deg, var(--warning-color), #fbbf24);
    }
   
    .table-container {
      background: white;
      border-radius: 16px;
      overflow: hidden;
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    }
   
    .table th {
      background: var(--dark-color);
      color: white;
      font-weight: 600;
      border: none;
      padding: 1rem;
    }
   
    .table td {
      padding: 1rem;
      vertical-align: middle;
      border-color: #e5e7eb;
    }
   
    .img-thumbnail {
      max-height: 60px;
      object-fit: cover;
      border-radius: 12px;
      transition: all 0.3s ease;
      border: 2px solid #e5e7eb;
    }
   
    .img-thumbnail:hover {
      transform: scale(1.2);
      border-color: var(--primary-color);
      box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
    }
   
    .btn-ampliar {
      font-size: 0.75rem;
      padding: 4px 10px;
      border-radius: 8px;
    }
   
    .badge-stock {
      font-size: 0.75rem;
      padding: 4px 8px;
      border-radius: 8px;
    }
   
    .floating-btn {
      position: fixed;
      bottom: 2rem;
      right: 2rem;
      width: 60px;
      height: 60px;
      border-radius: 50%;
      background: linear-gradient(135deg, var(--primary-color), #3b82f6);
      color: white;
      border: none;
      box-shadow: 0 4px 20px rgba(37, 99, 235, 0.3);
      display: flex;
      align-items: center;
      justify-content: center;
      text-decoration: none;
      transition: all 0.3s ease;
      z-index: 1000;
    }
   
    .floating-btn:hover {
      transform: scale(1.1);
      color: white;
    }
   
    .modal-header {
      border-radius: 15px 15px 0 0;
    }
   
    .form-control, .form-select {
      border-radius: 10px;
      border: 1px solid #d1d5db;
      padding: 0.75rem;
    }
   
    .form-control:focus, .form-select:focus {
      border-color: var(--primary-color);
      box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
    }
   
    .action-buttons .btn {
      border-radius: 8px;
      margin: 2px;
    }
   
    .search-box {
      border-radius: 12px;
      border: 1px solid #d1d5db;
      padding: 0.75rem;
    }
   
    .search-box:focus {
      border-color: var(--primary-color);
      box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
    }
   
    /* Estilos para badges de categorías */
    .categoria-badge {
      font-size: 0.8rem;
      padding: 0.4rem 0.8rem;
      border-radius: 20px;
      color: white;
    }
   
    .categoria-badge-badge-1 { background-color: var(--indigo-color); }
    .categoria-badge-2 { background-color: var(--pink-color); }
    .categoria-badge-3 { background-color: var(--teal-color); }
    .categoria-badge-4 { background-color: var(--purple-color); }
    .categoria-badge-5 { background-color: var(--orange-color); }
    .categoria-badge-6 { background-color: var(--cyan-color); }
    .categoria-badge-7 { background-color: var(--red-color); }
    .categoria-badge-8 { background-color: var(--gray-color); }
  </style>
</head>
<body>
<!-- Navigation -->
<nav class="navbar navbar-expand-lg navbar-light nav-glass mb-4">
  <div class="container">
    <a class="navbar-brand fw-bold text-dark" href="#">
      <i class="bi bi-box-seam me-2"></i>Sistema POS
    </a>
   
    <div class="navbar-nav ms-auto">
      <ul class="navbar-nav">
        <li class="nav-item">
          <a class="nav-link" href="index.php">
            <i class="bi bi-house"></i> Inicio
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link active" href="#">
            <i class="bi bi-box-seam"></i> Productos
          </a>
        </li>
        <li class="nav-item">
          <button class="nav-link btn btn-link" data-bs-toggle="modal" data-bs-target="#modalCambiarPassword">
            <i class="bi bi-key"></i> Cambiar Contraseña
          </button>
        </li>
      </ul>
    </div>
  </div>
</nav>
<div class="container py-4">
  <!-- Header -->
  <div class="row mb-4">
    <div class="col">
      <div class="d-flex justify-content-between align-items-center">
        <div>
          <h1 class="h2 text-white fw-bold mb-1">
            <i class="bi bi-box-seam me-2"></i>Gestor de Productos
          </h1>
          <p class="text-white-50 mb-0">Administra tu inventario de forma profesional</p>
        </div>
        <div class="text-end text-white">
          <div class="fw-light">Total Productos</div>
          <div class="h4 mb-0 fw-bold" id="total-productos">0</div>
        </div>
      </div>
    </div>
  </div>
  <!-- Stats Cards -->
  <div class="row mb-4">
    <div class="col-md-3 mb-3">
      <div class="stats-card p-4">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <h6 class="text-white-50 mb-2">TOTAL PRODUCTOS</h6>
            <h3 class="text-white fw-bold mb-0" id="stats-total">0</h3>
          </div>
          <i class="bi bi-box display-6 text-white-50"></i>
        </div>
      </div>
    </div>
    <div class="col-md-3 mb-3">
      <div class="stats-card success p-4">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <h6 class="text-white-50 mb-2">STOCK ALTO</h6>
            <h3 class="text-white fw-bold mb-0" id="stats-alto">0</h3>
          </div>
          <i class="bi bi-check-circle display-6 text-white-50"></i>
        </div>
      </div>
    </div>
    <div class="col-md-3 mb-3">
      <div class="stats-card warning p-4">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <h6 class="text-white-50 mb-2">STOCK BAJO</h6>
            <h3 class="text-white fw-bold mb-0" id="stats-bajo">0</h3>
          </div>
          <i class="bi bi-exclamation-triangle display-6 text-white-50"></i>
        </div>
      </div>
    </div>
    <div class="col-md-3 mb-3">
      <div class="stats-card p-4">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <h6 class="text-white-50 mb-2">SIN STOCK</h6>
            <h3 class="text-white fw-bold mb-0" id="stats-sin">0</h3>
          </div>
          <i class="bi bi-x-circle display-6 text-white-50"></i>
        </div>
      </div>
    </div>
  </div>
  <!-- Main Card -->
  <div class="glass-card">
    <div class="card-header d-flex justify-content-between align-items-center border-0 py-4">
      <h4 class="mb-0 text-dark fw-bold">
        <i class="bi bi-list-check me-2"></i>Lista de Productos
      </h4>
      <div class="d-flex gap-2">
        <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalAgregarMultiple">
          <i class="bi bi-plus-circle-dotted me-2"></i>Agregar Múltiples
        </button>
        <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalAgregar">
          <i class="bi bi-plus-circle me-2"></i>Agregar Producto
        </button>
        <button type="button" class="btn btn-info" id="descargar-pdf-general">
          <i class="bi bi-file-pdf me-2"></i>PDF General
        </button>
        <button type="button" class="btn btn-info" id="descargar-pdf-seleccion">
          <i class="bi bi-file-pdf me-2"></i>PDF Seleccionados
        </button>
      </div>
    </div>
   
    <div class="card-body">
      <!-- Search and Filters -->
      <div class="row mb-4">
        <div class="col-md-8">
          <div class="input-group search-box">
            <span class="input-group-text bg-transparent border-0"><i class="bi bi-search text-muted"></i></span>
            <input type="text" id="buscar" class="form-control border-0" placeholder="Buscar productos por nombre, categoría...">
          </div>
        </div>
        <div class="col-md-4">
          <select class="form-select" id="filtro-categoria">
            <option value="">Todas las categorías</option>
          </select>
        </div>
      </div>
      <!-- Products Table -->
      <div class="table-container">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead>
              <tr>
                <th width="40"><input type="checkbox" id="select-all"></th>
                <th width="60">ID</th>
                <th width="100">Imagen</th>
                <th>Nombre</th>
                <th width="150">Categoría</th>
                <th width="120" class="text-end">Precio (S/)</th>
                <th width="100" class="text-center">Stock</th>
                <th width="200" class="text-center">Acciones</th>
              </tr>
            </thead>
            <tbody id="tabla-productos">
              <!-- AJAX carga aquí -->
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
<!-- Modal Agregar Producto Individual -->
<div class="modal fade" id="modalAgregar" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form id="formAgregar" enctype="multipart/form-data">
        <div class="modal-header bg-success text-white">
          <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Agregar Producto</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label fw-semibold">Nombre del Producto</label>
            <input type="text" class="form-control" name="nombre" required placeholder="Ingrese nombre del producto">
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Categoría</label>
            <select class="form-select" name="categoria" required>
              <option value="">Seleccionar categoría</option>
            </select>
          </div>
          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label fw-semibold">Precio (S/)</label>
              <input type="number" step="0.01" class="form-control" name="precio" required placeholder="0.00">
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label fw-semibold">Stock Inicial</label>
              <input type="number" class="form-control" name="stock" value="0" required>
            </div>
          </div>
          <div class="mb-3 form-check">
            <input type="checkbox" class="form-check-input" name="stock_infinito" id="add_stock_infinito">
            <label class="form-check-label fw-semibold" for="add_stock_infinito">Stock Infinito</label>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Imagen del Producto</label>
            <input type="file" class="form-control" name="imagen" accept="image/*">
            <div class="mt-2 text-center">
              <img id="preview" src="#" alt="Preview" style="display:none; max-height:150px; border-radius:10px; border:2px dashed #ddd;">
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-success">
            <i class="bi bi-check-circle me-2"></i>Guardar Producto
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
<!-- Modal Agregar Múltiples Productos -->
<div class="modal fade" id="modalAgregarMultiple" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title"><i class="bi bi-plus-circle-dotted me-2"></i>Agregar Múltiples Productos</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div id="productos-multiples">
          <div class="producto-multiple mb-3 p-3 border rounded">
            <div class="row">
              <div class="col-md-4">
                <label>Nombre</label>
                <input type="text" class="form-control" name="nombres[]" required>
              </div>
              <div class="col-md-3">
                <label>Categoría</label>
                <select class="form-select" name="categorias[]" required>
                  <option value="">Seleccionar</option>
                </select>
              </div>
              <div class="col-md-2">
                <label>Precio</label>
                <input type="number" step="0.01" class="form-control" name="precios[]" required>
              </div>
              <div class="col-md-2">
                <label>Stock</label>
                <input type="number" class="form-control" name="stocks[]" value="0" required>
              </div>
              <div class="col-md-1 d-flex align-items-end">
                <button type="button" class="btn btn-danger btn-sm quitar-producto">
                  <i class="bi bi-trash"></i>
                </button>
              </div>
            </div>
            <div class="form-check mt-2">
              <input type="checkbox" class="form-check-input" name="stock_infinito_multi[]">
              <label class="form-check-label">Stock Infinito</label>
            </div>
          </div>
        </div>
        <button type="button" class="btn btn-outline-primary w-100 mt-3" id="agregar-campo">
          <i class="bi bi-plus-circle me-2"></i>Agregar Otro Producto
        </button>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-primary" id="guardar-multiples">
          <i class="bi bi-check-circle me-2"></i>Guardar Todos
        </button>
      </div>
    </div>
  </div>
</div>
<!-- Modal Contraseña (para editar/eliminar) -->
<div class="modal fade" id="modalPassword" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-warning text-dark">
        <h5 class="modal-title"><i class="bi bi-shield-lock me-2"></i>Verificación Requerida</h5>
      </div>
      <div class="modal-body">
        <p class="mb-3" id="passwordMessage">Ingrese la contraseña para continuar:</p>
        <input type="password" id="passwordInput" class="form-control" placeholder="Ingrese contraseña" autocomplete="off">
        <small class="text-muted mt-2 d-block">Contraseña actual: <code id="currentPassHint">1234</code></small>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-warning" id="confirmarPass">
          <i class="bi bi-key me-2"></i>Verificar
        </button>
      </div>
    </div>
  </div>
</div>
<!-- Modal Cambiar Contraseña -->
<div class="modal fade" id="modalCambiarPassword" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-info text-white">
        <h5 class="modal-title"><i class="bi bi-key-fill me-2"></i>Cambiar Contraseña</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label fw-semibold">Contraseña Actual</label>
          <input type="password" id="oldPass" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold">Nueva Contraseña</label>
          <input type="password" id="newPass" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label fw-semibold">Confirmar Nueva Contraseña</label>
          <input type="password" id="confirmNewPass" class="form-control" required>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-info" id="cambiarPass">
          <i class="bi bi-check-circle me-2"></i>Cambiar
        </button>
      </div>
    </div>
  </div>
</div>
<!-- Modal Editar -->
<div class="modal fade" id="modalEditar" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <form id="formEditar" enctype="multipart/form-data">
        <div class="modal-header bg-warning text-dark">
          <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Editar Producto</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="id" id="edit_id">
          <div class="mb-3">
            <label class="form-label fw-semibold">Nombre</label>
            <input type="text" class="form-control" name="nombre" id="edit_nombre" required>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Categoría</label>
            <select class="form-select" name="categoria" id="edit_categoria" required>
              <option value="">Seleccionar categoría</option>
            </select>
          </div>
          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label fw-semibold">Precio (S/)</label>
              <input type="number" step="0.01" class="form-control" name="precio" id="edit_precio" required>
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label fw-semibold">Stock</label>
              <input type="number" class="form-control" name="stock" id="edit_stock" required>
            </div>
          </div>
          <div class="mb-3 form-check">
            <input type="checkbox" class="form-check-input" name="stock_infinito" id="edit_stock_infinito">
            <label class="form-check-label fw-semibold" for="edit_stock_infinito">Stock Infinito</label>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Imagen Actual</label>
            <div class="text-center">
              <img id="edit_preview" src="" alt="Imagen actual" style="max-height:150px; border-radius:10px; border:2px solid #e5e7eb;">
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Nueva Imagen (opcional)</label>
            <input type="file" class="form-control" name="imagen" accept="image/*">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-warning">
            <i class="bi bi-check-circle me-2"></i>Guardar Cambios
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
<!-- Modal Eliminar -->
<div class="modal fade" id="modalEliminar" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title"><i class="bi bi-trash me-2"></i>Eliminar Producto</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p>¿Está seguro de que desea eliminar este producto?</p>
        <input type="hidden" id="delete_id">
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-danger" id="confirmarEliminar">
          <i class="bi bi-trash me-2"></i>Eliminar
        </button>
      </div>
    </div>
  </div>
</div>
<!-- Modal Ampliar Imagen -->
<div class="modal fade" id="modalAmpliarImagen" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-dark text-white">
        <h5 class="modal-title"><i class="bi bi-zoom-in me-2"></i>Vista Previa de Imagen</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body text-center bg-light">
        <img id="imagenAmpliada" src="" class="img-fluid rounded shadow" style="max-height: 70vh;">
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>
<!-- Floating Action Button -->
<a href="index.php" class="floating-btn">
  <i class="bi bi-arrow-left fs-5"></i>
</a>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(document).ready(function(){
  let PASSWORD_CORRECTA = "1234"; // Contraseña inicial (puede cambiarse)
  let productoEditar = null;
  let productoEliminar = null;
  let passwordAction = ''; // 'editar' o 'eliminar'

  // === CATEGORÍAS ESTÁTICAS PARA ÉNFASIS ===
  const CATEGORIAS = [
    { id: 1, nombre: "Librería", clase: "categoria-badge-1" },
    { id: 2, nombre: "Golosinas", clase: "categoria-badge-2" },
    { id: 3, nombre: "Accesorios", clase: "categoria-badge-3" },
    { id: 4, nombre: "Apoyo Técnico", clase: "categoria-badge-4" },
    { id: 5, nombre: "Agente", clase: "categoria-badge-5" },
    { id: 6, nombre: "Comisión", clase: "categoria-badge-6" },
    { id: 7, nombre: "Gastos", clase: "categoria-badge-7" },
    { id: 8, nombre: "Otros", clase: "categoria-badge-8" }
  ];

  function cargarCategorias() {
    let options = '<option value="">Todas las categorías</option>';
    CATEGORIAS.forEach(cat => {
      options += `<option value="${cat.id}">${cat.id}: ${cat.nombre}</option>`;
    });
    $('#filtro-categoria, select[name="categoria"], #edit_categoria, select[name="categorias[]"]').html(options);
  }

  function cargarProductos(termino = '', categoria = '') {
    $.ajax({
      url: 'productos_ajax.php',
      type: 'GET',
      data: { buscar: termino, categoria: categoria },
      success: function(data){
        $('#tabla-productos').html(data);
        // Añadir checkboxes y badges de categorías después de cargar
        $('#tabla-productos tr').each(function() {
          const catId = $(this).data('categoria-id');
          const cat = CATEGORIAS.find(c => c.id === parseInt(catId));
          if (cat) {
            const badge = `<span class="badge categoria-badge ${cat.clase}">${cat.id}: ${cat.nombre}</span>`;
            $(this).find('td:eq(3)').html(badge);
          }
          $(this).prepend('<td><input type="checkbox" class="select-row" data-id="' + $(this).data('id') + '"></td>');
        });
        actualizarEstadisticas();
      }
    });
  }

  function actualizarEstadisticas() {
    const total = $('#tabla-productos tr').length;
    $('#total-productos, #stats-total').text(total);
    let alto = 0, bajo = 0, sin = 0;
    $('#tabla-productos tr').each(function() {
      const stockText = $(this).find('td:eq(5)').text();
      if (stockText === '∞') {
        alto++;
      } else {
        const stock = parseInt(stockText);
        if (stock > 10) alto++;
        else if (stock > 0) bajo++;
        else sin++;
      }
    });
    $('#stats-alto').text(alto);
    $('#stats-bajo').text(bajo);
    $('#stats-sin').text(sin);
  }

  // Inicialización
  cargarProductos();
  cargarCategorias();

  // Búsqueda y filtros
  $('#buscar').on('input', function(){
    cargarProductos($(this).val(), $('#filtro-categoria').val());
  });
  $('#filtro-categoria').change(function(){
    cargarProductos($('#buscar').val(), $(this).val());
  });

  // Seleccionar todos
  $('#select-all').change(function() {
    $('.select-row').prop('checked', this.checked);
  });

  // Preview imagen
  $('input[type="file"]').change(function(){
    const previewId = $(this).attr('name') === 'imagen' ? '#preview' : '#edit_preview';
    if (this.files[0]) {
      var reader = new FileReader();
      reader.onload = function(e) {
        $(previewId).attr('src', e.target.result).show();
      }
      reader.readAsDataURL(this.files[0]);
    }
  });

  // Agregar producto individual
  $('#formAgregar').submit(function(e){
    e.preventDefault();
    var formData = new FormData(this);
    formData.append('stock_infinito', $('#add_stock_infinito').is(':checked') ? 1 : 0);  // ← Mejora: Manejo de stock infinito
    $.ajax({
      url: 'productos_ajax.php',
      type: 'POST',
      data: formData,
      contentType: false,
      processData: false,
      success: function(){
        $('#modalAgregar').modal('hide');
        $('#formAgregar')[0].reset();
        $('#preview').hide();
        cargarProductos();
        alert('✅ Producto agregado correctamente');
      }
    });
  });

  // Agregar múltiples productos
  $('#agregar-campo').click(function(){
    const nuevoCampo = `
      <div class="producto-multiple mb-3 p-3 border rounded">
        <div class="row">
          <div class="col-md-4">
            <input type="text" class="form-control" name="nombres[]" required placeholder="Nombre del producto">
          </div>
          <div class="col-md-3">
            <select class="form-select" name="categorias[]" required>
              <option value="">Seleccionar</option>
              ${$('select[name="categorias[]"]').first().html()}
            </select>
          </div>
          <div class="col-md-2">
            <input type="number" step="0.01" class="form-control" name="precios[]" required placeholder="0.00">
          </div>
          <div class="col-md-2">
            <input type="number" class="form-control" name="stocks[]" value="0" required>
          </div>
          <div class="col-md-1 d-flex align-items-end">
            <button type="button" class="btn btn-danger btn-sm quitar-producto">
              <i class="bi bi-trash"></i>
            </button>
          </div>
        </div>
        <div class="form-check mt-2">
          <input type="checkbox" class="form-check-input" name="stock_infinito_multi[]">
          <label class="form-check-label">Stock Infinito</label>
        </div>
      </div>
    `;
    $('#productos-multiples').append(nuevoCampo);
  });

  $(document).on('click', '.quitar-producto', function(){
    if ($('.producto-multiple').length > 1) {
      $(this).closest('.producto-multiple').remove();
    } else {
      alert('Debe haber al menos un producto');
    }
  });

  $('#guardar-multiples').click(function(){
    const productos = [];
    let isValid = true;
    $('.producto-multiple').each(function(index){
      const nombre = $(this).find('input[name="nombres[]"]').val();
      const categoria = $(this).find('select[name="categorias[]"]').val();
      const precio = $(this).find('input[name="precios[]"]').val();
      const stock = $(this).find('input[name="stocks[]"]').val();
      const stock_infinito = $(this).find('input[name="stock_infinito_multi[]"]').is(':checked') ? 1 : 0;  // ← Mejora: Manejo de stock infinito en múltiples
      if (!nombre || !categoria || !precio) {
        isValid = false;
        return false;
      }
      productos.push({ nombre, categoria, precio, stock, stock_infinito });
    });
    if (!isValid) {
      alert('❌ Complete todos los campos obligatorios');
      return;
    }
    $.ajax({
      url: 'productos_ajax.php',
      type: 'POST',
      data: {
        action: 'agregar_multiples',
        productos: JSON.stringify(productos)
      },
      success: function(response){
        $('#modalAgregarMultiple').modal('hide');
        $('#productos-multiples').html($('#productos-multiples').html().split('</div>')[0] + '</div>');
        cargarProductos();
        alert('✅ Productos agregados correctamente');
      }
    });
  });

  // Editar con contraseña
  $(document).on('click', '.btn-editar', function(){
    productoEditar = {
      id: $(this).data('id'),
      nombre: $(this).data('nombre'),
      categoria: $(this).data('categoria'),
      precio: $(this).data('precio'),
      stock: $(this).data('stock'),
      stock_infinito: $(this).data('stock-infinito'),  // ← Mejora: Pasar stock infinito al editar
      imagen: $(this).data('imagen')
    };
    passwordAction = 'editar';
    $('#passwordMessage').text('Ingrese la contraseña para editar:');
    $('#modalPassword').modal('show');
  });

  // Eliminar con contraseña
  $(document).on('click', '.btn-eliminar', function(){
    productoEliminar = {
      id: $(this).data('id')
    };
    passwordAction = 'eliminar';
    $('#passwordMessage').text('Ingrese la contraseña para eliminar:');
    $('#delete_id').val(productoEliminar.id);
    $('#modalPassword').modal('show');
  });

  $('#confirmarPass').click(function(){
    if ($('#passwordInput').val() === PASSWORD_CORRECTA) {
      $('#modalPassword').modal('hide');
      $('#passwordInput').val('');
      if (passwordAction === 'editar') {
        $('#edit_id').val(productoEditar.id);
        $('#edit_nombre').val(productoEditar.nombre);
        $('#edit_categoria').val(productoEditar.categoria);
        $('#edit_precio').val(productoEditar.precio);
        $('#edit_stock').val(productoEditar.stock);
        $('#edit_stock_infinito').prop('checked', productoEditar.stock_infinito == 1);  // ← Mejora: Marcar checkbox si stock infinito
        if (productoEditar.imagen) {
          $('#edit_preview').attr('src', 'uploads/' + productoEditar.imagen).show();
        } else {
          $('#edit_preview').hide();
        }
        $('#modalEditar').modal('show');
      } else if (passwordAction === 'eliminar') {
        $('#modalEliminar').modal('show');
      }
    } else {
      alert('❌ Contraseña incorrecta');
    }
  });

  // Guardar edición
  $('#formEditar').submit(function(e){
    e.preventDefault();
    var formData = new FormData(this);
    formData.append('stock_infinito', $('#edit_stock_infinito').is(':checked') ? 1 : 0);  // ← Mejora: Manejo de stock infinito al guardar edición
    $.ajax({
      url: 'editar_producto.php',
      type: 'POST',
      data: formData,
      contentType: false,
      processData: false,
      success: function(){
        $('#modalEditar').modal('hide');
        cargarProductos();
        alert('✅ Producto actualizado correctamente');
      }
    });
  });

  // Confirmar eliminación
  $('#confirmarEliminar').click(function(){
    const id = $('#delete_id').val();
    $.ajax({
      url: 'productos_ajax.php',
      type: 'POST',
      data: { action: 'eliminar', id: id },
      success: function(){
        $('#modalEliminar').modal('hide');
        cargarProductos();
        alert('✅ Producto eliminado correctamente');
      }
    });
  });

  // Cambiar contraseña
  $('#cambiarPass').click(function(){
    const oldPass = $('#oldPass').val();
    const newPass = $('#newPass').val();
    const confirmNewPass = $('#confirmNewPass').val();
    if (oldPass !== PASSWORD_CORRECTA) {
      alert('❌ Contraseña actual incorrecta');
      return;
    }
    if (newPass !== confirmNewPass) {
      alert('❌ Las nuevas contraseñas no coinciden');
      return;
    }
    if (newPass.length < 4) {
      alert('❌ La nueva contraseña debe tener al menos 4 caracteres');
      return;
    }
    PASSWORD_CORRECTA = newPass;
    $('#currentPassHint').text(newPass);
    $('#modalCambiarPassword').modal('hide');
    $('#oldPass, #newPass, #confirmNewPass').val('');
    alert('✅ Contraseña cambiada correctamente');
  });

  // Ampliar imagen
  $(document).on('click', '.btn-ampliar', function(){
    var imgSrc = $(this).data('imagen');
    if (imgSrc) {
      $('#imagenAmpliada').attr('src', 'uploads/' + imgSrc);
      $('#modalAmpliarImagen').modal('show');
    }
  });

  // Descargar PDF general
  $('#descargar-pdf-general').click(function(){
    generarPDF('general');
  });

  // Descargar PDF seleccionados
  $('#descargar-pdf-seleccion').click(function(){
    generarPDF('seleccion');
  });

  // Función para generar PDF
  function generarPDF(tipo) {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF();
    doc.setFontSize(12);
    doc.text('Lista de Productos', 10, 10);
    let y = 20;
    let productos = [];
    if (tipo === 'general') {
      $('#tabla-productos tr').each(function() {
        const id = $(this).find('td:eq(1)').text();
        const nombre = $(this).find('td:eq(3)').text();
        const categoria = $(this).find('td:eq(4)').text();
        const precio = $(this).find('td:eq(5)').text();
        const stock = $(this).find('td:eq(6)').text();
        productos.push({id, nombre, categoria, precio, stock});
      });
    } else {
      $('.select-row:checked').each(function() {
        const row = $(this).closest('tr');
        const id = row.find('td:eq(1)').text();
        const nombre = row.find('td:eq(3)').text();
        const categoria = row.find('td:eq(4)').text();
        const precio = row.find('td:eq(5)').text();
        const stock = row.find('td:eq(6)').text();
        productos.push({id, nombre, categoria, precio, stock});
      });
      if (productos.length === 0) {
        alert('❌ Seleccione al menos un producto');
        return;
      }
    }
    productos.forEach(p => {
      doc.text(`${p.id} - ${p.nombre} (${p.categoria}) - Precio: ${p.precio} - Stock: ${p.stock}`, 10, y);
      y += 10;
      if (y > 280) {
        doc.addPage();
        y = 10;
      }
    });
    doc.save(`productos_${tipo}.pdf`);
  }
});
</script>
</body>
</html>
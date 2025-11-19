<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$pdo    = get_pdo_or_fail();

if (strcasecmp($method, 'GET') === 0) {
    // Listar productos de un vendedor: GET /api/productos.php?vendedor_id=1
    $vendedorId = isset($_GET['vendedor_id']) ? (int) $_GET['vendedor_id'] : 0;
    if ($vendedorId <= 0) {
        json_error('El parámetro vendedor_id es obligatorio y debe ser numérico.', 422);
    }

    $sql = 'SELECT id_producto, nombre, descripcion, precio, stock, vendedor_id
            FROM productos
            WHERE vendedor_id = :vendedor_id
            ORDER BY id_producto DESC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':vendedor_id' => $vendedorId]);

    $productos = $stmt->fetchAll();

    json_response([
        'success'   => true,
        'productos' => $productos,
    ]);
} elseif (strcasecmp($method, 'POST') === 0) {
    // Crear un nuevo producto para un vendedor: POST JSON
    $body = get_request_body();

    $nombre      = require_field($body, 'nombre', 'El nombre del producto es obligatorio.');
    $descripcion = isset($body['descripcion']) ? trim((string) $body['descripcion']) : null;
    $precio      = require_int_field($body, 'precio', 'El precio del producto es obligatorio y debe ser numérico.');
    $stock       = require_int_field($body, 'stock', 'El stock del producto es obligatorio y debe ser numérico.');
    $vendedorId  = require_int_field($body, 'vendedor_id', 'El vendedor_id es obligatorio.');

    if ($precio < 0) {
        json_error('El precio no puede ser negativo.', 422);
    }
    if ($stock < 0) {
        json_error('El stock no puede ser negativo.', 422);
    }

    // Verificar que el vendedor exista
    $checkVendSql = 'SELECT id_vendedor FROM vendedores WHERE id_vendedor = :id LIMIT 1';
    $checkVendStmt = $pdo->prepare($checkVendSql);
    $checkVendStmt->execute([':id' => $vendedorId]);
    $vendor = $checkVendStmt->fetch();

    if (!$vendor) {
        json_error('El vendedor especificado no existe.', 404);
    }

    $insertSql = 'INSERT INTO productos (nombre, descripcion, precio, stock, vendedor_id)
                  VALUES (:nombre, :descripcion, :precio, :stock, :vendedor_id)';

    $insertStmt = $pdo->prepare($insertSql);

    try {
        $insertStmt->execute([
            ':nombre'      => $nombre,
            ':descripcion' => $descripcion ?: null,
            ':precio'      => $precio,
            ':stock'       => $stock,
            ':vendedor_id' => $vendedorId,
        ]);
    } catch (Throwable $e) {
        error_log('Error insertando producto: ' . $e->getMessage());
        json_error('No se pudo crear el producto.', 500);
    }

    $idProducto = (int) $pdo->lastInsertId();

    $selectSql = 'SELECT id_producto, nombre, descripcion, precio, stock, vendedor_id
                  FROM productos
                  WHERE id_producto = :id
                  LIMIT 1';

    $selectStmt = $pdo->prepare($selectSql);
    $selectStmt->execute([':id' => $idProducto]);

    $producto = $selectStmt->fetch();

    if (!$producto) {
        json_error('Producto creado pero no se pudo recuperar la información.', 500);
    }

    json_response([
        'success'  => true,
        'message'  => 'Producto creado correctamente.',
        'producto' => $producto,
    ], 201);
} else {
    json_error('Método no permitido.', 405);
}
<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

require_method('POST');

$body = get_request_body();

$documento = require_field($body, 'documento', 'El documento del vendedor es obligatorio.');
$nombre    = require_field($body, 'nombre', 'El nombre del vendedor es obligatorio.');
$telefono  = isset($body['telefono']) ? trim((string) $body['telefono']) : null;
$email     = isset($body['email']) ? trim((string) $body['email']) : null;

$pdo = get_pdo_or_fail();

// Verificar si ya existe un vendedor con ese documento
$checkSql = 'SELECT id_vendedor, documento, nombre, telefono, email
             FROM vendedores
             WHERE documento = :documento
             LIMIT 1';
$checkStmt = $pdo->prepare($checkSql);
$checkStmt->execute([':documento' => $documento]);
$existing = $checkStmt->fetch();

if ($existing) {
    json_error('Ya existe un vendedor registrado con ese documento.', 409, [
        'vendedor' => $existing,
    ]);
}

$insertSql = 'INSERT INTO vendedores (documento, nombre, telefono, email)
              VALUES (:documento, :nombre, :telefono, :email)';

$insertStmt = $pdo->prepare($insertSql);

try {
    $insertStmt->execute([
        ':documento' => $documento,
        ':nombre'    => $nombre,
        ':telefono'  => $telefono ?: null,
        ':email'     => $email ?: null,
    ]);
} catch (Throwable $e) {
    error_log('Error insertando vendedor: ' . $e->getMessage());
    json_error('No se pudo registrar el vendedor.', 500);
}

$id = (int) $pdo->lastInsertId();

$selectSql = 'SELECT id_vendedor, documento, nombre, telefono, email
              FROM vendedores
              WHERE id_vendedor = :id
              LIMIT 1';
$selectStmt = $pdo->prepare($selectSql);
$selectStmt->execute([':id' => $id]);

$vendedor = $selectStmt->fetch();

if (!$vendedor) {
    json_error('Vendedor creado pero no se pudo recuperar la información.', 500);
}

json_response([
    'success'  => true,
    'message'  => 'Vendedor registrado correctamente.',
    'vendedor' => $vendedor,
], 201);
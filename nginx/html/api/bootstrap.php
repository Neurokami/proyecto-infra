<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

/**
 * Send a JSON response and terminate.
 *
 * @param array<string,mixed> $data
 */
function json_response(array $data, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Send a JSON error response with standard structure.
 *
 * @param array<string,mixed>|null $extra
 */
function json_error(string $message, int $statusCode = 400, ?array $extra = null): void
{
    $payload = [
        'success' => false,
        'message' => $message,
    ];

    if ($extra) {
        $payload = array_merge($payload, $extra);
    }

    json_response($payload, $statusCode);
}

/**
 * Parse JSON body as associative array.
 *
 * @return array<string,mixed>
 */
function get_request_body(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        return [];
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        json_error('Cuerpo de solicitud JSON inválido.', 400);
    }

    return $data;
}

/**
 * Get a PDO connection or fail with a 500 JSON error.
 */
function get_pdo_or_fail(): PDO
{
    try {
        $pdo = getPDO();
        ensureDatabaseInitialized($pdo);
        return $pdo;
    } catch (Throwable $e) {
        error_log('DB connection error: ' . $e->getMessage());
        // Incluimos el mensaje real del error en la respuesta para depurar.
        $host = getenv('DB_HOST') ?: DB_HOST;
    $port = getenv('DB_PORT') ?: (string) DB_PORT;
    $db   = getenv('DB_NAME') ?: DB_NAME;
    $user = getenv('DB_USER') ?: DB_USER;
    $pass = getenv('DB_PASS') ?: DB_PASS;

    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $db);
        json_error('Error interno de base de datos.', 500, [
            'error' => $e->getMessage(),
            'msg' => $dsn
        ]);
    }
}

/**
 * Ensure the request method matches the expected one.
 */
function require_method(string $method): void
{
    if (strcasecmp($_SERVER['REQUEST_METHOD'] ?? '', $method) !== 0) {
        json_error('Método no permitido.', 405);
    }
}

/**
 * Simple helper to fetch a required string field from an array.
 *
 * @param array<string,mixed> $source
 */
function require_field(array $source, string $key, string $errorMessage): string
{
    $value = isset($source[$key]) ? trim((string) $source[$key]) : '';
    if ($value === '') {
        json_error($errorMessage, 422);
    }

    return $value;
}

/**
 * Simple helper to fetch an integer field from an array.
 *
 * @param array<string,mixed> $source
 */
function require_int_field(array $source, string $key, string $errorMessage): int
{
    if (!isset($source[$key]) || $source[$key] === '') {
        json_error($errorMessage, 422);
    }

    if (!is_numeric($source[$key])) {
        json_error($errorMessage, 422);
    }

    return (int) $source[$key];
}
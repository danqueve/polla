<?php
// ============================================================
// config/db.example.php - Plantilla de conexion
//
// Copiar a config/db.php y completar con las credenciales
// reales del entorno. config/db.php esta en .gitignore, asi
// que los git pull del VPS no lo pisan.
// ============================================================

define('DB_HOST',    'localhost');
define('DB_PORT',    '3306');
define('DB_NAME',    'polla_quevedo');
define('DB_USER',    'root');
define('DB_PASS',    '');
define('DB_CHARSET', 'utf8mb4');

function getPDO(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            DB_HOST, DB_PORT, DB_NAME, DB_CHARSET
        );
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_FOUND_ROWS   => true,
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log('[polla] Error de conexion: ' . $e->getMessage());
            http_response_code(503);
            exit('No se pudo conectar a la base de datos.');
        }
    }

    return $pdo;
}

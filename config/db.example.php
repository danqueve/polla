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
define('DB_NAME',    'c2881399_polla');
define('DB_USER',    'root');
define('DB_PASS',    '');
define('DB_CHARSET', 'utf8mb4');

// Entorno de ESTA maquina. Nunca se deduce del pedido (el header Host
// lo manda el cliente y se puede falsificar).
//   'development' -> errores en pantalla, cookies sin Secure (http llano)
//   'production'  -> errores solo al log, cookies Secure (exige https)
// Si falta, config/bootstrap.php asume 'production'.
define('APP_ENV', 'development');

// URL base del sitio, sin barra final. En produccion, el dominio
// canonico: define('APP_URL', 'https://decenadeoro.lol');
define('APP_URL', 'http://localhost/polla');

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

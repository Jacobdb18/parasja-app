<?php
define('DB_HOST', getenv('MYSQLHOST')     ?: getenv('DB_HOST') ?: 'localhost');
define('DB_PORT', getenv('MYSQLPORT')     ?: getenv('DB_PORT') ?: '3306');
define('DB_USER', getenv('MYSQLUSER')     ?: getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('MYSQLPASSWORD') ?: getenv('DB_PASS') ?: '');
define('DB_NAME', getenv('MYSQLDATABASE') ?: getenv('DB_NAME') ?: 'parasja_app');

define('BASE_URL', getenv('APP_BASE_URL') ?: '/parasja');
define('UPLOAD_DIR', __DIR__ . '/../uploads/fotos/');
define('UPLOAD_URL', BASE_URL . '/uploads/fotos/');
define('TESSERACT_PATH', 'tesseract');

try {
    // Verbind zonder database naam eerst om hem aan te kunnen maken
    $dsn_base = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";charset=utf8mb4";
    $pdoBase = new PDO($dsn_base, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdoBase->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );

    // Auto-install tabellen als ze niet bestaan
    $tables = $pdo->query("SHOW TABLES LIKE 'parasjot'")->fetchColumn();
    if (!$tables) {
        $sql = file_get_contents(__DIR__ . '/install.sql');
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
            if ($stmt) try { $pdo->exec($stmt); } catch (PDOException $e) { /* skip duplicate */ }
        }
    }
} catch (PDOException $e) {
    http_response_code(500);
    die('<h1>Database fout</h1><p>' . htmlspecialchars($e->getMessage()) . '</p>');
}

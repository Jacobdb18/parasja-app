<!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<title>Parasja App Setup</title>
<style>
body { font-family: sans-serif; max-width: 700px; margin: 40px auto; padding: 0 20px; }
.ok { color: green; } .err { color: red; }
pre { background: #f0f0f0; padding: 12px; border-radius: 6px; font-size: 0.85rem; overflow-x: auto; }
.btn { display:inline-block; padding:10px 20px; background:#2c5f2e; color:white; border:none; border-radius:6px; cursor:pointer; text-decoration:none; font-size:1rem; margin-top:1rem; }
</style>
</head>
<body>
<h1>🔧 Parasja App Setup</h1>

<?php
$step = $_GET['step'] ?? 'check';

if ($step === 'install') {
    try {
        $pdo = new PDO("mysql:host=localhost;charset=utf8mb4", 'root', '', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);

        $sql = file_get_contents(__DIR__ . '/install.sql');
        $statements = array_filter(array_map('trim', explode(';', $sql)));

        $ok = 0;
        $errors = [];
        foreach ($statements as $stmt) {
            if (!$stmt) continue;
            try {
                $pdo->exec($stmt);
                $ok++;
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), 'Duplicate entry') !== false ||
                    strpos($e->getMessage(), 'already exists') !== false) {
                    $ok++;
                } else {
                    $errors[] = $e->getMessage();
                }
            }
        }

        echo "<h2 class='ok'>✅ Database aangemaakt!</h2>";
        echo "<p>$ok statements uitgevoerd.</p>";
        if ($errors) {
            echo "<p class='err'>Waarschuwingen:</p><pre>" . implode("\n", $errors) . "</pre>";
        }
        echo "<a href='/parasja/' class='btn'>→ Naar de Parasja App</a>";

    } catch (PDOException $e) {
        echo "<h2 class='err'>❌ Verbinding mislukt</h2><pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
        echo "<p>Controleer of XAMPP MySQL draait en de instellingen in <code>config/db.php</code> kloppen.</p>";
    }
} else {
    // Check status
    echo "<h2>Systeemcheck</h2><ul>";

    // MySQL
    try {
        $pdo = new PDO("mysql:host=localhost;charset=utf8mb4", 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        echo "<li class='ok'>✅ MySQL verbinding OK</li>";
    } catch (PDOException $e) {
        echo "<li class='err'>❌ MySQL: " . htmlspecialchars($e->getMessage()) . "</li>";
    }

    // Upload map
    $uploadDir = __DIR__ . '/../uploads/fotos/';
    if (is_writable($uploadDir)) {
        echo "<li class='ok'>✅ Upload map schrijfbaar</li>";
    } else {
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        echo "<li class='" . (is_writable($uploadDir) ? 'ok' : 'err') . "'>"
           . (is_writable($uploadDir) ? '✅' : '❌') . " Upload map: $uploadDir</li>";
    }

    // Tesseract
    $output = []; $ret = 0;
    exec('tesseract --version 2>&1', $output, $ret);
    if ($ret === 0) {
        echo "<li class='ok'>✅ Tesseract OCR: " . htmlspecialchars($output[0] ?? 'gevonden') . "</li>";
    } else {
        echo "<li style='color:orange'>⚠️ Tesseract niet gevonden — OCR werkt niet. <a href='https://github.com/UB-Mannheim/tesseract/wiki' target='_blank'>Download hier</a></li>";
    }

    echo "</ul>";

    // Database check
    try {
        $pdo2 = new PDO("mysql:host=localhost;dbname=parasja_app;charset=utf8mb4", 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $count = $pdo2->query("SELECT COUNT(*) FROM parasjot")->fetchColumn();
        echo "<p class='ok'>✅ Database bestaat al met $count parasjot.</p>";
        echo "<p><a href='/parasja/' class='btn'>→ Naar de app</a></p>";
        echo "<p style='margin-top:10px;font-size:0.85rem;color:#888'>Of: <a href='?step=install'>Herinstalleer database</a> (overschrijft bestaande data)</p>";
    } catch (PDOException $e) {
        echo "<p>Database bestaat nog niet.</p>";
        echo "<a href='?step=install' class='btn'>▶ Database aanmaken</a>";
    }
}
?>
</body>
</html>

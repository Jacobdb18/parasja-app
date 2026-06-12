<?php
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

// JSON actie (ocr-opslaan, verwijderen)
$rawInput = file_get_contents('php://input');
$jsonInput = json_decode($rawInput, true);

if ($jsonInput && isset($jsonInput['actie'])) {
    $actie = $jsonInput['actie'];

    if ($actie === 'ocr-opslaan') {
        $id   = (int)($jsonInput['id'] ?? 0);
        $tekst = $jsonInput['ocr_bewerkt'] ?? '';
        if (!$id) { echo json_encode(['success' => false, 'error' => 'Geen ID']); exit; }
        $pdo->prepare("UPDATE foto_notities SET ocr_bewerkt = ? WHERE id = ?")->execute([$tekst, $id]);
        echo json_encode(['success' => true]);
        exit;
    }

    if ($actie === 'verwijderen') {
        $id = (int)($jsonInput['id'] ?? 0);
        if (!$id) { echo json_encode(['success' => false, 'error' => 'Geen ID']); exit; }
        $stmt = $pdo->prepare("SELECT bestandsnaam FROM foto_notities WHERE id = ?");
        $stmt->execute([$id]);
        $rij = $stmt->fetch();
        if ($rij) {
            $pad = UPLOAD_DIR . $rij['bestandsnaam'];
            if (file_exists($pad)) unlink($pad);
        }
        $pdo->prepare("DELETE FROM foto_notities WHERE id = ?")->execute([$id]);
        echo json_encode(['success' => true]);
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Onbekende actie']);
    exit;
}

// Multipart upload
$parasjaId       = (int)($_POST['parasja_id'] ?? 0);
$parasjaSchemaId = (int)($_POST['parasja_schema_id'] ?? 0) ?: null;
$notitie         = trim($_POST['notitie'] ?? '') ?: null;

if (!$parasjaId) {
    echo json_encode(['success' => false, 'error' => 'Geen parasja_id']);
    exit;
}

if (empty($_FILES['foto']) || $_FILES['foto']['error'] !== UPLOAD_ERR_OK) {
    $foutCode = $_FILES['foto']['error'] ?? -1;
    echo json_encode(['success' => false, 'error' => 'Upload fout: code ' . $foutCode]);
    exit;
}

$file = $_FILES['foto'];

// Valideer type
$allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/heic'];
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime  = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mime, $allowedTypes) && !in_array($file['type'], $allowedTypes)) {
    echo json_encode(['success' => false, 'error' => 'Ongeldig bestandstype: ' . $mime]);
    exit;
}

// Max 10 MB
if ($file['size'] > 10 * 1024 * 1024) {
    echo json_encode(['success' => false, 'error' => 'Bestand te groot (max 10 MB)']);
    exit;
}

// Unieke bestandsnaam
$ext = pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'jpg';
$bestandsnaam = 'foto_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . strtolower($ext);
$doelpad = UPLOAD_DIR . $bestandsnaam;

if (!move_uploaded_file($file['tmp_name'], $doelpad)) {
    echo json_encode(['success' => false, 'error' => 'Opslaan mislukt']);
    exit;
}

// OCR met Tesseract
$ocrTekst = '';
$tesseractBeschikbaar = false;

// Probeer Tesseract
$tmpOutput = sys_get_temp_dir() . '/parasja_ocr_' . uniqid();
$cmd = TESSERACT_PATH . ' ' . escapeshellarg($doelpad) . ' ' . escapeshellarg($tmpOutput) . ' -l nld+eng 2>&1';
$output = [];
$returnCode = 0;
exec($cmd, $output, $returnCode);

if ($returnCode === 0 && file_exists($tmpOutput . '.txt')) {
    $ocrTekst = trim(file_get_contents($tmpOutput . '.txt'));
    unlink($tmpOutput . '.txt');
    $tesseractBeschikbaar = true;
}

// Sla op in database
$stmt = $pdo->prepare("
    INSERT INTO foto_notities (parasja_id, parasja_schema_id, bestandsnaam, origineel_naam, ocr_tekst, notitie)
    VALUES (?, ?, ?, ?, ?, ?)
");
$stmt->execute([$parasjaId, $parasjaSchemaId, $bestandsnaam, $file['name'], $ocrTekst ?: null, $notitie]);
$id = $pdo->lastInsertId();

echo json_encode([
    'success'   => true,
    'id'        => $id,
    'ocr_tekst' => $ocrTekst,
    'ocr_actief'=> $tesseractBeschikbaar,
    'bericht'   => $tesseractBeschikbaar ? '' : 'Tesseract niet gevonden — tekst handmatig invoeren.'
]);

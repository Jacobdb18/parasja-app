<?php
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$actie = $input['actie'] ?? '';

try {
    switch ($actie) {
        case 'opslaan':
            $parasjaId       = (int)($input['parasja_id'] ?? 0);
            $parasjaSchemaId = (int)($input['parasja_schema_id'] ?? 0) ?: null;
            $tekst           = trim($input['tekst'] ?? '');
            $titel           = trim($input['titel'] ?? '') ?: null;

            if (!$parasjaId || !$tekst) {
                echo json_encode(['success' => false, 'error' => 'Ontbrekende velden']);
                exit;
            }

            $stmt = $pdo->prepare("
                INSERT INTO notities (parasja_id, parasja_schema_id, titel, tekst)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$parasjaId, $parasjaSchemaId, $titel, $tekst]);
            echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
            break;

        case 'verwijderen':
            $id = (int)($input['id'] ?? 0);
            if (!$id) { echo json_encode(['success' => false, 'error' => 'Geen ID']); exit; }
            $pdo->prepare("DELETE FROM notities WHERE id = ?")->execute([$id]);
            echo json_encode(['success' => true]);
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Onbekende actie']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

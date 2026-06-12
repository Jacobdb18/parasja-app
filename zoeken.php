<?php
require_once __DIR__ . '/config/db.php';

$pageTitle = 'Zoeken';
$activePage = 'zoeken';

$q = trim($_GET['q'] ?? '');
$resultaten = [];

if (strlen($q) >= 2) {
    $zoek = "%$q%";

    // Parasjot op naam
    $s1 = $pdo->prepare("
        SELECT 'parasja' AS type, p.id, p.naam_nl, p.naam_transliteratie, p.naam_hebreeuws,
               p.samenvatting AS tekst, NULL AS datum
        FROM parasjot p
        WHERE p.naam_nl LIKE ? OR p.naam_transliteratie LIKE ? OR p.naam_hebreeuws LIKE ? OR p.samenvatting LIKE ?
        ORDER BY p.volgorde LIMIT 10
    ");
    $s1->execute([$zoek, $zoek, $zoek, $zoek]);
    $resultaten = array_merge($resultaten, $s1->fetchAll());

    // Notities
    $s2 = $pdo->prepare("
        SELECT 'notitie' AS type, n.id, p.naam_nl, p.naam_transliteratie, p.naam_hebreeuws,
               n.tekst, n.aangemaakt_op AS datum, p.id AS parasja_id
        FROM notities n
        JOIN parasjot p ON n.parasja_id = p.id
        WHERE n.tekst LIKE ? OR n.titel LIKE ?
        ORDER BY n.aangemaakt_op DESC LIMIT 20
    ");
    $s2->execute([$zoek, $zoek]);
    $resultaten = array_merge($resultaten, $s2->fetchAll());

    // OCR teksten
    $s3 = $pdo->prepare("
        SELECT 'foto' AS type, f.id, p.naam_nl, p.naam_transliteratie, p.naam_hebreeuws,
               COALESCE(f.ocr_bewerkt, f.ocr_tekst) AS tekst, f.aangemaakt_op AS datum, p.id AS parasja_id,
               f.bestandsnaam
        FROM foto_notities f
        JOIN parasjot p ON f.parasja_id = p.id
        WHERE f.ocr_tekst LIKE ? OR f.ocr_bewerkt LIKE ? OR f.notitie LIKE ?
        ORDER BY f.aangemaakt_op DESC LIMIT 20
    ");
    $s3->execute([$zoek, $zoek, $zoek]);
    $resultaten = array_merge($resultaten, $s3->fetchAll());
}

function highlight($tekst, $q) {
    if (!$q) return htmlspecialchars($tekst);
    return preg_replace('/(' . preg_quote(htmlspecialchars($q), '/') . ')/iu', '<mark>$1</mark>', htmlspecialchars($tekst));
}

function snippet($tekst, $q, $len = 150) {
    $pos = mb_stripos($tekst, $q);
    if ($pos === false) return mb_substr($tekst, 0, $len) . (mb_strlen($tekst) > $len ? '...' : '');
    $start = max(0, $pos - 50);
    $excerpt = ($start > 0 ? '...' : '') . mb_substr($tekst, $start, $len);
    if ($start + $len < mb_strlen($tekst)) $excerpt .= '...';
    return $excerpt;
}

include __DIR__ . '/includes/header.php';
?>

<div class="section-header" style="margin-bottom:1.25rem;">
    <div class="section-title">Zoeken</div>
</div>

<form method="GET" style="margin-bottom:1.5rem;">
    <div class="search-bar">
        <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <input type="search" name="q" value="<?= htmlspecialchars($q) ?>"
               placeholder="Zoek in parasjot, notities en foto teksten..."
               autofocus autocomplete="off">
    </div>
</form>

<?php if ($q && strlen($q) < 2): ?>
<p style="color:var(--text-muted); font-size:0.9rem;">Voer minimaal 2 tekens in.</p>

<?php elseif ($q && empty($resultaten)): ?>
<div class="card">
    <div class="empty-state">
        <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <p>Geen resultaten gevonden voor <strong>"<?= htmlspecialchars($q) ?>"</strong></p>
    </div>
</div>

<?php elseif ($q): ?>
<div style="font-size:0.85rem; color:var(--text-muted); margin-bottom:1rem;">
    <?= count($resultaten) ?> resultaat<?= count($resultaten) != 1 ? 'en' : '' ?> voor <strong>"<?= htmlspecialchars($q) ?>"</strong>
</div>

<div class="card" style="padding:0; overflow:hidden;">
    <?php foreach ($resultaten as $r): ?>
    <a href="/parasja/parasja.php?id=<?= $r['parasja_id'] ?? $r['id'] ?>"
       class="search-result">
        <div class="result-type type-<?= $r['type'] ?>">
            <?= $r['type'] === 'parasja' ? 'Parasja' : ($r['type'] === 'notitie' ? 'Notitie' : 'Foto OCR') ?>
        </div>
        <div class="result-title">
            <?= highlight($r['naam_transliteratie'], $q) ?>
            <span style="font-family:'Frank Ruhl Libre',serif; color:var(--primary); margin-left:8px; font-size:1.1em;">
                <?= htmlspecialchars($r['naam_hebreeuws']) ?>
            </span>
        </div>
        <?php if ($r['tekst']): ?>
        <div class="result-snippet">
            <?= highlight(snippet($r['tekst'], $q), $q) ?>
        </div>
        <?php endif; ?>
        <?php if ($r['datum']): ?>
        <div style="font-size:0.72rem; color:var(--text-muted); margin-top:4px;">
            <?= date('d-m-Y', strtotime($r['datum'])) ?>
        </div>
        <?php endif; ?>
    </a>
    <?php endforeach; ?>
</div>

<?php else: ?>
<div class="card">
    <div style="padding:2rem; text-align:center; color:var(--text-muted);">
        <p style="font-family:'Frank Ruhl Libre',serif; font-size:2rem; direction:rtl; color:var(--primary); margin-bottom:0.75rem;">חִפּוּשׂ</p>
        <p>Zoek door alle 54 parasjot, je studienotities en herkende fototeksten.</p>
    </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>

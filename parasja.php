<?php
require_once __DIR__ . '/config/db.php';

$parasjaId = (int)($_GET['id'] ?? 0);
$schemaId  = (int)($_GET['schema'] ?? 0);

if (!$parasjaId) { header('Location: ' . BASE_URL . '/archief.php'); exit; }

// Parasja ophalen
$stmt = $pdo->prepare("SELECT * FROM parasjot WHERE id = ?");
$stmt->execute([$parasjaId]);
$parasja = $stmt->fetch();
if (!$parasja) { header('Location: ' . BASE_URL . '/archief.php'); exit; }

// Schema (datum) ophalen
$schema = null;
if ($schemaId) {
    $s = $pdo->prepare("SELECT ps.*, p2.naam_nl AS gecombineerd_nl, p2.naam_transliteratie AS gecombineerd_trans
                         FROM parasja_schema ps LEFT JOIN parasjot p2 ON ps.gecombineerd_met = p2.id
                         WHERE ps.id = ?");
    $s->execute([$schemaId]);
    $schema = $s->fetch();
}

// Alle schema's voor deze parasja (per jaar)
$alleSchema = $pdo->prepare("
    SELECT ps.*, p2.naam_nl AS gecombineerd_nl
    FROM parasja_schema ps LEFT JOIN parasjot p2 ON ps.gecombineerd_met = p2.id
    WHERE ps.parasja_id = ? ORDER BY ps.shabbat_datum DESC
");
$alleSchema->execute([$parasjaId]);
$schemas = $alleSchema->fetchAll();

// Notities per jaar
$stmt2 = $pdo->prepare("
    SELECT n.*, ps.shabbat_datum, ps.joods_jaar
    FROM notities n
    LEFT JOIN parasja_schema ps ON n.parasja_schema_id = ps.id
    WHERE n.parasja_id = ?
    ORDER BY n.aangemaakt_op DESC
");
$stmt2->execute([$parasjaId]);
$notities = $stmt2->fetchAll();

// Fotos
$stmt3 = $pdo->prepare("
    SELECT f.*, ps.shabbat_datum, ps.joods_jaar
    FROM foto_notities f
    LEFT JOIN parasja_schema ps ON f.parasja_schema_id = ps.id
    WHERE f.parasja_id = ?
    ORDER BY f.aangemaakt_op DESC
");
$stmt3->execute([$parasjaId]);
$fotos = $stmt3->fetchAll();

// Vorige en volgende
$vorigeP = $pdo->prepare("SELECT * FROM parasjot WHERE id = ?");
$vorigeP->execute([$parasjaId - 1]);
$vorige = $vorigeP->fetch();

$volgendeP = $pdo->prepare("SELECT * FROM parasjot WHERE id = ?");
$volgendeP->execute([$parasjaId + 1]);
$volgende = $volgendeP->fetch();

$maandNamen = ['','januari','februari','maart','april','mei','juni','juli','augustus','september','oktober','november','december'];
function formatDatum($d) {
    global $maandNamen;
    $ts = strtotime($d);
    return date('j', $ts) . ' ' . $maandNamen[(int)date('n', $ts)] . ' ' . date('Y', $ts);
}

$pageTitle = 'Parasja ' . $parasja['naam_transliteratie'];
$activePage = 'archief';

include __DIR__ . '/includes/header.php';
?>

<!-- Breadcrumb -->
<div style="margin-bottom:1.25rem; font-size:0.85rem; color:var(--text-muted); display:flex; gap:6px; align-items:center;">
    <a href="<?= BASE_URL ?>/archief.php" style="color:var(--primary); text-decoration:none;">Archief</a>
    <svg viewBox="0 0 24 24" style="width:14px;height:14px;stroke:currentColor;fill:none;stroke-width:2;"><polyline points="9,18 15,12 9,6"/></svg>
    <span><?= htmlspecialchars($parasja['naam_transliteratie']) ?></span>
</div>

<!-- Hero -->
<div class="parasja-hero" style="margin-bottom:1.5rem;">
    <div class="label"><?= htmlspecialchars($parasja['boek']) ?></div>
    <div class="hebrew-name"><?= htmlspecialchars($parasja['naam_hebreeuws']) ?></div>
    <div class="dutch-name"><?= htmlspecialchars($parasja['naam_nl']) ?></div>
    <div class="transliteration">Parasja <?= htmlspecialchars($parasja['naam_transliteratie']) ?></div>

    <div class="meta-row">
        <span class="meta-badge">
            <svg viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
            <?= htmlspecialchars($parasja['verzen_van']) ?> &ndash; <?= htmlspecialchars($parasja['verzen_tot']) ?>
        </span>
        <span class="meta-badge">Parasja <?= $parasja['volgorde'] ?> / 54</span>
    </div>

    <?php if ($schema): ?>
    <div style="margin-bottom:1rem; font-size:0.9rem; opacity:0.85;">
        Gelezen op: <?= formatDatum($schema['shabbat_datum']) ?> (jaar <?= $schema['joods_jaar'] ?>)
    </div>
    <?php endif; ?>

    <p class="samenvatting"><?= nl2br(htmlspecialchars($parasja['samenvatting'])) ?></p>

    <div class="hero-actions">
        <button class="btn btn-primary" onclick="openModal('modal-notitie')">
            <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Notitie toevoegen
        </button>
        <button class="btn btn-white" onclick="openModal('modal-upload')">
            <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17,8 12,3 7,8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
            Foto uploaden
        </button>
    </div>

    <!-- Navigatie -->
    <div style="display:flex; gap:10px; margin-top:1.25rem; padding-top:1.25rem; border-top:1px solid rgba(255,255,255,0.15);">
        <?php if ($vorige): ?>
        <a href="<?= BASE_URL ?>/parasja.php?id=<?= $vorige['id'] ?>" class="btn btn-outline btn-sm">
            <svg viewBox="0 0 24 24"><polyline points="15,18 9,12 15,6"/></svg>
            <?= htmlspecialchars($vorige['naam_transliteratie']) ?>
        </a>
        <?php endif; ?>
        <?php if ($volgende): ?>
        <a href="<?= BASE_URL ?>/parasja.php?id=<?= $volgende['id'] ?>" class="btn btn-outline btn-sm" style="margin-left:auto;">
            <?= htmlspecialchars($volgende['naam_transliteratie']) ?>
            <svg viewBox="0 0 24 24"><polyline points="9,18 15,12 9,6"/></svg>
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- Data datums -->
<?php if (count($schemas) > 1): ?>
<div class="card" style="margin-bottom:1.5rem;">
    <div class="card-header">
        <div class="card-title">
            <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            Leesdatums
        </div>
    </div>
    <div style="display:flex; gap:10px; flex-wrap:wrap;">
        <?php foreach ($schemas as $sc): ?>
        <a href="<?= BASE_URL ?>/parasja.php?id=<?= $parasjaId ?>&schema=<?= $sc['id'] ?>"
           style="padding:8px 16px; border:1.5px solid var(--border); border-radius:20px; font-size:0.85rem; text-decoration:none; color:var(--text);
                  <?= ($schema && $sc['id'] == $schema['id']) ? 'background:var(--primary);color:white;border-color:var(--primary);' : '' ?>">
            <?= formatDatum($sc['shabbat_datum']) ?> &middot; <?= $sc['joods_jaar'] ?>
        </a>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Notities & Fotos -->
<div class="grid-2">

<!-- Notities -->
<div class="card">
    <div class="card-header">
        <div class="card-title">
            <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14,2 14,8 20,8"/></svg>
            Studie Notities (<?= count($notities) ?>)
        </div>
        <button class="btn btn-green btn-sm" onclick="openModal('modal-notitie')">
            <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Nieuw
        </button>
    </div>

    <?php if (empty($notities)): ?>
    <div class="empty-state">
        <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14,2 14,8 20,8"/></svg>
        <p>Nog geen notities &mdash; begin met studeren!</p>
    </div>
    <?php else: ?>
    <?php
    $huidigJaar = null;
    foreach ($notities as $n):
        $jaar = $n['joods_jaar'] ?? date('Y', strtotime($n['aangemaakt_op']));
        if ($jaar !== $huidigJaar):
            $huidigJaar = $jaar;
    ?>
    <div style="font-size:0.72rem; font-weight:600; text-transform:uppercase; letter-spacing:0.1em; color:var(--text-muted); margin:10px 0 6px; padding-top:<?= $n !== reset($notities) ? '10px' : '0' ?>; border-top:<?= $n !== reset($notities) ? '1px solid var(--border)' : 'none' ?>;">
        <?= $jaar ? 'Jaar ' . $jaar : 'Datum onbekend' ?>
    </div>
    <?php endif; ?>
    <div class="notitie-item">
        <div class="notitie-actions">
            <button class="btn btn-sm btn-danger" onclick="notitieVerwijderen(<?= $n['id'] ?>)">
                <svg viewBox="0 0 24 24"><polyline points="3,6 5,6 21,6"/><path d="M19,6v14a2,2 0 0,1-2,2H7a2,2 0 0,1-2-2V6m3,0V4a2,2 0 0,1 2-2h4a2,2 0 0,1 2,2v2"/></svg>
            </button>
        </div>
        <?php if ($n['titel']): ?>
        <div style="font-weight:600; font-size:0.9rem; margin-bottom:5px;"><?= htmlspecialchars($n['titel']) ?></div>
        <?php endif; ?>
        <div class="notitie-meta"><?= date('d-m-Y H:i', strtotime($n['aangemaakt_op'])) ?></div>
        <div class="notitie-tekst"><?= nl2br(htmlspecialchars($n['tekst'])) ?></div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Fotos -->
<div class="card">
    <div class="card-header">
        <div class="card-title">
            <svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21,15 16,10 5,21"/></svg>
            Foto Notities (<?= count($fotos) ?>)
        </div>
        <button class="btn btn-green btn-sm" onclick="openModal('modal-upload')">
            <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Foto
        </button>
    </div>

    <?php if (empty($fotos)): ?>
    <div class="empty-state">
        <svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21,15 16,10 5,21"/></svg>
        <p>Nog geen foto notities</p>
    </div>
    <?php else: ?>
    <div class="foto-grid">
        <?php foreach ($fotos as $f): ?>
        <div class="foto-card" onclick="openFoto(<?= $f['id'] ?>, '<?= htmlspecialchars(UPLOAD_URL . $f['bestandsnaam']) ?>', <?= json_encode($f['ocr_bewerkt'] ?? $f['ocr_tekst'] ?? '') ?>, '<?= date('d-m-Y', strtotime($f['aangemaakt_op'])) ?>')">
            <img src="<?= htmlspecialchars(UPLOAD_URL . $f['bestandsnaam']) ?>" alt="" loading="lazy">
            <div class="foto-card-body">
                <?php if ($f['notitie']): ?>
                <div style="font-size:0.8rem;font-weight:500;margin-bottom:3px;"><?= htmlspecialchars($f['notitie']) ?></div>
                <?php endif; ?>
                <div class="foto-ocr-preview"><?= htmlspecialchars(substr($f['ocr_bewerkt'] ?? $f['ocr_tekst'] ?? 'Geen tekst', 0, 80)) ?></div>
                <div class="foto-date"><?= date('d-m-Y', strtotime($f['aangemaakt_op'])) ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

</div>

<!-- MODALS (zelfde als index.php) -->
<div class="modal-overlay" id="modal-notitie">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">Notitie &mdash; <?= htmlspecialchars($parasja['naam_transliteratie']) ?></div>
            <button class="btn-close" onclick="closeModal('modal-notitie')"><svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label>Titel (optioneel)</label>
                <input type="text" id="notitie-titel" placeholder="Bijv. Uitleg van rabbi...">
            </div>
            <div class="form-group">
                <label>Notitie</label>
                <textarea id="notitie-tekst" rows="8" placeholder="Schrijf hier je studienotities..."></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn" onclick="closeModal('modal-notitie')">Annuleren</button>
            <button class="btn btn-green" onclick="notitieOpslaan(<?= $parasjaId ?>, <?= $schemaId ?>)">
                <svg viewBox="0 0 24 24"><polyline points="20,6 9,17 4,12"/></svg> Opslaan
            </button>
        </div>
    </div>
</div>

<div class="modal-overlay" id="modal-upload">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">Foto Notitie uploaden</div>
            <button class="btn-close" onclick="closeModal('modal-upload')"><svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="foto-parasja-id" value="<?= $parasjaId ?>">
            <input type="hidden" id="foto-schema-id" value="<?= $schemaId ?>">
            <div class="form-group">
                <label>Omschrijving (optioneel)</label>
                <input type="text" id="foto-notitie" placeholder="Bijv. Aantekeningen shiur">
            </div>
            <div class="upload-zone" id="upload-zone">
                <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17,8 12,3 7,8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                <p><strong>Klik of sleep</strong> een foto hierheen</p>
                <p style="font-size:0.8rem;margin-top:5px">JPG, PNG &middot; max. 10 MB</p>
                <input type="file" id="foto-input" accept="image/*" style="display:none">
            </div>
            <img id="upload-preview" style="display:none; max-height:200px; margin-top:1rem; border-radius:8px; width:100%; object-fit:contain;">
            <div id="ocr-status" style="margin-top:1rem;"></div>
        </div>
    </div>
</div>

<div class="modal-overlay" id="modal-foto-viewer">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">Foto Notitie</div>
            <button class="btn-close" onclick="closeModal('modal-foto-viewer')"><svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="viewer-foto-id">
            <img id="viewer-img" src="" alt="" class="foto-viewer-img" style="margin-bottom:1rem;">
            <div style="font-size:0.78rem;color:var(--text-muted);margin-bottom:0.5rem;" id="viewer-datum"></div>
            <div class="form-group">
                <label>Herkende tekst (bewerkbaar)</label>
                <textarea id="viewer-tekst" rows="7"></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-danger btn-sm" onclick="fotoVerwijderen(document.getElementById('viewer-foto-id').value)">Verwijderen</button>
            <button class="btn" onclick="closeModal('modal-foto-viewer')">Sluiten</button>
            <button class="btn btn-green" onclick="ocrBewerktOpslaan()">
                <svg viewBox="0 0 24 24"><polyline points="20,6 9,17 4,12"/></svg> Opslaan
            </button>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

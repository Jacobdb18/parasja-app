<?php
require_once __DIR__ . '/config/db.php';

$pageTitle = 'Parasja van de Week';
$activePage = 'home';

// Huidige parasja: dichtstbijzijnde shabbat (aankomende of afgelopen)
$vandaag = date('Y-m-d');

$stmt = $pdo->prepare("
    SELECT ps.*, p.naam_hebreeuws, p.naam_nl, p.naam_transliteratie, p.boek,
           p.verzen_van, p.verzen_tot, p.samenvatting, p.volgorde,
           p2.naam_nl AS gecombineerd_nl, p2.naam_transliteratie AS gecombineerd_trans,
           (SELECT COUNT(*) FROM notities n WHERE n.parasja_id = p.id) AS notitie_count,
           (SELECT COUNT(*) FROM foto_notities f WHERE f.parasja_id = p.id) AS foto_count
    FROM parasja_schema ps
    JOIN parasjot p ON ps.parasja_id = p.id
    LEFT JOIN parasjot p2 ON ps.gecombineerd_met = p2.id
    WHERE ps.shabbat_datum >= DATE_SUB(?, INTERVAL 6 DAY)
    ORDER BY ps.shabbat_datum ASC
    LIMIT 1
");
$stmt->execute([$vandaag]);
$parasja = $stmt->fetch();

// Vorige en volgende parasja
if ($parasja) {
    $stmt2 = $pdo->prepare("
        SELECT ps.id, ps.shabbat_datum, p.naam_nl, p.naam_transliteratie, p.volgorde
        FROM parasja_schema ps JOIN parasjot p ON ps.parasja_id = p.id
        WHERE ps.shabbat_datum < ? ORDER BY ps.shabbat_datum DESC LIMIT 1
    ");
    $stmt2->execute([$parasja['shabbat_datum']]);
    $vorige = $stmt2->fetch();

    $stmt3 = $pdo->prepare("
        SELECT ps.id, ps.shabbat_datum, p.naam_nl, p.naam_transliteratie, p.volgorde
        FROM parasja_schema ps JOIN parasjot p ON ps.parasja_id = p.id
        WHERE ps.shabbat_datum > ? ORDER BY ps.shabbat_datum ASC LIMIT 1
    ");
    $stmt3->execute([$parasja['shabbat_datum']]);
    $volgende = $stmt3->fetch();
}

// Notities van deze parasja (dit jaar)
$notities = [];
$fotos = [];
if ($parasja) {
    $stmt4 = $pdo->prepare("
        SELECT * FROM notities WHERE parasja_id = ? ORDER BY aangemaakt_op DESC
    ");
    $stmt4->execute([$parasja['parasja_id']]);
    $notities = $stmt4->fetchAll();

    $stmt5 = $pdo->prepare("
        SELECT * FROM foto_notities WHERE parasja_id = ? ORDER BY aangemaakt_op DESC
    ");
    $stmt5->execute([$parasja['parasja_id']]);
    $fotos = $stmt5->fetchAll();
}

$dagNamen = ['Zondag','Maandag','Dinsdag','Woensdag','Donderdag','Vrijdag','Zaterdag'];
$maandNamen = ['','januari','februari','maart','april','mei','juni','juli','augustus','september','oktober','november','december'];

function formatDatum($datum) {
    global $dagNamen, $maandNamen;
    $ts = strtotime($datum);
    return $dagNamen[date('w', $ts)] . ' ' . date('j', $ts) . ' ' . $maandNamen[(int)date('n', $ts)] . ' ' . date('Y', $ts);
}

include __DIR__ . '/includes/header.php';
?>

<?php if (!$parasja): ?>
<div class="card" style="text-align:center; padding:3rem;">
    <p style="color:var(--text-muted)">Geen parasja gevonden. Controleer of de database correct is gevuld.</p>
    <a href="<?= BASE_URL ?>/config/setup.php" class="btn btn-green" style="margin-top:1rem">Database instellen</a>
</div>
<?php else: ?>

<!-- HERO -->
<div class="parasja-hero">
    <div class="label">Parasja van deze week &middot; <?= htmlspecialchars($parasja['boek']) ?></div>
    <div class="hebrew-name"><?= htmlspecialchars($parasja['naam_hebreeuws']) ?></div>
    <div class="dutch-name"><?= htmlspecialchars($parasja['naam_nl']) ?></div>
    <div class="transliteration">Parasja <?= htmlspecialchars($parasja['naam_transliteratie']) ?>
        <?php if ($parasja['gecombineerd_nl']): ?>
            &ndash; <?= htmlspecialchars($parasja['gecombineerd_nl']) ?>
        <?php endif; ?>
    </div>

    <div class="meta-row">
        <span class="meta-badge">
            <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            <?= formatDatum($parasja['shabbat_datum']) ?>
        </span>
        <span class="meta-badge">
            <svg viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
            <?= htmlspecialchars($parasja['verzen_van']) ?> &ndash; <?= htmlspecialchars($parasja['verzen_tot']) ?>
        </span>
        <span class="meta-badge">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12,6 12,12 16,14"/></svg>
            Joods jaar <?= $parasja['joods_jaar'] ?>
        </span>
        <?php if ($parasja['notitie_count'] > 0 || $parasja['foto_count'] > 0): ?>
        <span class="meta-badge">
            <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14,2 14,8 20,8"/></svg>
            <?= $parasja['notitie_count'] ?> notitie<?= $parasja['notitie_count'] != 1 ? 's' : '' ?>
            <?php if ($parasja['foto_count'] > 0): ?>&middot; <?= $parasja['foto_count'] ?> foto<?= $parasja['foto_count'] != 1 ? "'s" : '' ?><?php endif; ?>
        </span>
        <?php endif; ?>
    </div>

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
        <a href="<?= BASE_URL ?>/parasja.php?id=<?= $parasja['parasja_id'] ?>" class="btn btn-outline">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            Volledig archief
        </a>
    </div>

    <!-- Navigatie vorige/volgende -->
    <div style="display:flex; gap:10px; margin-top:1.25rem; padding-top:1.25rem; border-top:1px solid rgba(255,255,255,0.15);">
        <?php if ($vorige): ?>
        <a href="<?= BASE_URL ?>/parasja.php?id=<?= $vorige['parasja_id'] ?? $vorige['id'] ?>&schema=<?= $vorige['id'] ?>" class="btn btn-outline btn-sm">
            <svg viewBox="0 0 24 24"><polyline points="15,18 9,12 15,6"/></svg>
            <?= htmlspecialchars($vorige['naam_transliteratie']) ?>
        </a>
        <?php endif; ?>
        <?php if ($volgende): ?>
        <a href="<?= BASE_URL ?>/parasja.php?id=<?= $volgende['parasja_id'] ?? $volgende['id'] ?>&schema=<?= $volgende['id'] ?>" class="btn btn-outline btn-sm" style="margin-left:auto">
            <?= htmlspecialchars($volgende['naam_transliteratie']) ?>
            <svg viewBox="0 0 24 24"><polyline points="9,18 15,12 9,6"/></svg>
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- NOTITIES & FOTOS -->
<div class="grid-2">

<!-- Notities -->
<div class="card">
    <div class="card-header">
        <div class="card-title">
            <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14,2 14,8 20,8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
            Notities
        </div>
        <button class="btn btn-green btn-sm" onclick="openModal('modal-notitie')">
            <svg viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Nieuw
        </button>
    </div>

    <?php if (empty($notities)): ?>
    <div class="empty-state">
        <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14,2 14,8 20,8"/></svg>
        <p>Nog geen notities voor deze parasja</p>
    </div>
    <?php else: ?>
    <?php foreach ($notities as $n): ?>
    <div class="notitie-item">
        <div class="notitie-actions">
            <button class="btn btn-sm btn-danger" onclick="notitieVerwijderen(<?= $n['id'] ?>)">
                <svg viewBox="0 0 24 24"><polyline points="3,6 5,6 21,6"/><path d="M19,6v14a2,2 0 0,1-2,2H7a2,2 0 0,1-2-2V6m3,0V4a2,2 0 0,1 2-2h4a2,2 0 0,1 2,2v2"/></svg>
            </button>
        </div>
        <?php if ($n['titel']): ?>
        <div style="font-weight:600; font-size:0.9rem; margin-bottom:5px;"><?= htmlspecialchars($n['titel']) ?></div>
        <?php endif; ?>
        <div class="notitie-meta">
            <svg viewBox="0 0 24 24" style="width:12px;height:12px;stroke:currentColor;fill:none;stroke-width:2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            <?= date('d-m-Y H:i', strtotime($n['aangemaakt_op'])) ?>
        </div>
        <div class="notitie-tekst"><?= nl2br(htmlspecialchars($n['tekst'])) ?></div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Foto Notities -->
<div class="card">
    <div class="card-header">
        <div class="card-title">
            <svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21,15 16,10 5,21"/></svg>
            Foto Notities
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
            <img src="<?= htmlspecialchars(UPLOAD_URL . $f['bestandsnaam']) ?>" alt="Foto notitie" loading="lazy">
            <div class="foto-card-body">
                <?php if ($f['notitie']): ?>
                <div style="font-size:0.8rem;font-weight:500;margin-bottom:3px;"><?= htmlspecialchars($f['notitie']) ?></div>
                <?php endif; ?>
                <div class="foto-ocr-preview"><?= htmlspecialchars(substr($f['ocr_bewerkt'] ?? $f['ocr_tekst'] ?? 'Geen herkende tekst', 0, 80)) ?></div>
                <div class="foto-date"><?= date('d-m-Y', strtotime($f['aangemaakt_op'])) ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

</div><!-- /grid-2 -->

<?php endif; ?>

<!-- MODAL: Notitie toevoegen -->
<div class="modal-overlay" id="modal-notitie">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">Notitie toevoegen &mdash; <?= htmlspecialchars($parasja['naam_transliteratie'] ?? '') ?></div>
            <button class="btn-close" onclick="closeModal('modal-notitie')">
                <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
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
            <button class="btn btn-green" onclick="notitieOpslaan(<?= $parasja['parasja_id'] ?? 0 ?>, <?= $parasja['id'] ?? 0 ?>)">
                <svg viewBox="0 0 24 24"><polyline points="20,6 9,17 4,12"/></svg>
                Opslaan
            </button>
        </div>
    </div>
</div>

<!-- MODAL: Foto upload -->
<div class="modal-overlay" id="modal-upload">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">Foto Notitie uploaden</div>
            <button class="btn-close" onclick="closeModal('modal-upload')">
                <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="foto-parasja-id" value="<?= $parasja['parasja_id'] ?? 0 ?>">
            <input type="hidden" id="foto-schema-id" value="<?= $parasja['id'] ?? 0 ?>">
            <input type="hidden" id="foto-input-hidden">

            <div class="form-group">
                <label>Omschrijving (optioneel)</label>
                <input type="text" id="foto-notitie" placeholder="Bijv. Aantekeningen shiur 12 juni">
            </div>

            <div class="upload-zone" id="upload-zone">
                <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17,8 12,3 7,8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                <p><strong>Klik of sleep</strong> een foto hierheen</p>
                <p style="font-size:0.8rem;margin-top:5px">JPG, PNG, HEIC &middot; max. 10 MB</p>
                <input type="file" id="foto-input" accept="image/*" style="display:none">
            </div>

            <img id="upload-preview" style="display:none; max-height:200px; margin-top:1rem; border-radius:8px; width:100%; object-fit:contain;">

            <div id="ocr-status" style="margin-top:1rem;"></div>
        </div>
    </div>
</div>

<!-- MODAL: Foto viewer -->
<div class="modal-overlay" id="modal-foto-viewer">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">Foto Notitie</div>
            <button class="btn-close" onclick="closeModal('modal-foto-viewer')">
                <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
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
                <svg viewBox="0 0 24 24"><polyline points="20,6 9,17 4,12"/></svg>
                Tekst opslaan
            </button>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

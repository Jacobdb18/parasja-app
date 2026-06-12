<?php
require_once __DIR__ . '/config/db.php';

$pageTitle = 'Parasja Archief';
$activePage = 'archief';

$filterBoek = $_GET['boek'] ?? '';
$filterJaar = $_GET['jaar'] ?? '';
$vandaag = date('Y-m-d');

// Beschikbare jaren
$jaren = $pdo->query("SELECT DISTINCT joods_jaar FROM parasja_schema ORDER BY joods_jaar DESC")->fetchAll(PDO::FETCH_COLUMN);

// Huidig schema id
$stmtHuidig = $pdo->prepare("
    SELECT ps.id FROM parasja_schema ps
    WHERE ps.shabbat_datum >= DATE_SUB(?, INTERVAL 6 DAY)
    ORDER BY ps.shabbat_datum ASC LIMIT 1
");
$stmtHuidig->execute([$vandaag]);
$huidigSchemaId = $stmtHuidig->fetchColumn();

// Query opbouwen
$where = [];
$params = [];

if ($filterBoek) {
    $where[] = "p.boek = ?";
    $params[] = $filterBoek;
}

if ($filterJaar) {
    $where[] = "ps.joods_jaar = ?";
    $params[] = $filterJaar;
}

$whereStr = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Haal parasjot op (gecombineerde entries als één rij)
$sql = "
    SELECT ps.id AS schema_id, ps.shabbat_datum, ps.joods_jaar, ps.gecombineerd_met,
           p.id AS parasja_id, p.naam_hebreeuws, p.naam_nl, p.naam_transliteratie, p.boek, p.volgorde,
           p2.naam_nl AS gecombineerd_nl, p2.naam_transliteratie AS gecombineerd_trans,
           (SELECT COUNT(*) FROM notities n WHERE n.parasja_id = p.id) AS notitie_count,
           (SELECT COUNT(*) FROM foto_notities f WHERE f.parasja_id = p.id) AS foto_count
    FROM parasja_schema ps
    JOIN parasjot p ON ps.parasja_id = p.id
    LEFT JOIN parasjot p2 ON ps.gecombineerd_met = p2.id
    $whereStr
    ORDER BY ps.shabbat_datum DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$parasjot = $stmt->fetchAll();

$dagNamen = ['Zo','Ma','Di','Wo','Do','Vr','Za'];
$maandNamen = ['','jan','feb','mrt','apr','mei','jun','jul','aug','sep','okt','nov','dec'];

function formatKorteDatum($datum) {
    global $maandNamen;
    $ts = strtotime($datum);
    return date('j', $ts) . ' ' . $maandNamen[(int)date('n', $ts)] . ' ' . date('Y', $ts);
}

include __DIR__ . '/includes/header.php';
?>

<div class="section-header">
    <div class="section-title">Parasja Archief</div>
</div>

<!-- Filters -->
<div class="filter-bar">
    <form method="GET" style="display:flex; gap:10px; flex-wrap:wrap; align-items:center; width:100%;">
        <select name="boek" onchange="this.form.submit()">
            <option value="">Alle boeken</option>
            <?php foreach (['Bereshit','Shemot','Vayikra','Bamidbar','Devarim'] as $b): ?>
            <option value="<?= $b ?>" <?= $filterBoek === $b ? 'selected' : '' ?>><?= $b ?></option>
            <?php endforeach; ?>
        </select>
        <select name="jaar" onchange="this.form.submit()">
            <option value="">Alle jaren</option>
            <?php foreach ($jaren as $j): ?>
            <option value="<?= $j ?>" <?= $filterJaar == $j ? 'selected' : '' ?>>Jaar <?= $j ?></option>
            <?php endforeach; ?>
        </select>
        <?php if ($filterBoek || $filterJaar): ?>
        <a href="<?= BASE_URL ?>/archief.php" class="btn btn-sm" style="background:var(--bg);border:1.5px solid var(--border);">Wis filters</a>
        <?php endif; ?>
        <span style="margin-left:auto; font-size:0.85rem; color:var(--text-muted);"><?= count($parasjot) ?> parasjot</span>
    </form>
</div>

<!-- Lijst -->
<div class="card" style="padding:0; overflow:hidden;">
    <?php
    $huidigBoek = null;
    foreach ($parasjot as $p):
        if (!$filterBoek && $p['boek'] !== $huidigBoek):
            $huidigBoek = $p['boek'];
    ?>
    <div class="boek-divider"><?= htmlspecialchars($p['boek']) ?></div>
    <?php endif; ?>

    <a href="<?= BASE_URL ?>/parasja.php?id=<?= $p['parasja_id'] ?>&schema=<?= $p['schema_id'] ?>"
       class="archief-item <?= $p['schema_id'] == $huidigSchemaId ? 'current' : '' ?>">

        <div class="volgorde"><?= $p['volgorde'] ?></div>

        <div class="hebrew"><?= htmlspecialchars($p['naam_hebreeuws']) ?></div>

        <div class="naam-nl">
            <?= htmlspecialchars($p['naam_transliteratie']) ?>
            <?php if ($p['gecombineerd_nl']): ?>
            <span style="color:var(--text-muted); font-weight:400"> &ndash; <?= htmlspecialchars($p['gecombineerd_trans']) ?></span>
            <?php endif; ?>
            <?php if ($p['schema_id'] == $huidigSchemaId): ?>
            <span style="font-size:0.72rem; background:var(--accent); color:var(--primary-dark); padding:2px 8px; border-radius:12px; font-weight:600; margin-left:8px;">DEZE WEEK</span>
            <?php endif; ?>
        </div>

        <span class="boek-badge"><?= htmlspecialchars($p['boek']) ?></span>

        <?php if ($p['notitie_count'] > 0 || $p['foto_count'] > 0): ?>
        <div class="notitie-count">
            <svg viewBox="0 0 24 24" style="width:13px;height:13px;stroke:currentColor;fill:none;stroke-width:2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14,2 14,8 20,8"/></svg>
            <?= $p['notitie_count'] + $p['foto_count'] ?>
        </div>
        <?php else: ?>
        <div style="min-width:40px;"></div>
        <?php endif; ?>

        <div class="datum"><?= formatKorteDatum($p['shabbat_datum']) ?></div>

        <svg viewBox="0 0 24 24" style="width:16px;height:16px;stroke:var(--border);fill:none;stroke-width:2;flex-shrink:0;"><polyline points="9,18 15,12 9,6"/></svg>
    </a>

    <?php endforeach; ?>

    <?php if (empty($parasjot)): ?>
    <div class="empty-state">
        <svg viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
        <p>Geen parasjot gevonden</p>
    </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

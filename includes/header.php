<!DOCTYPE html>
<html lang="nl" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'Parasja App') ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Frank+Ruhl+Libre:wght@300;400;500;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>

<nav class="navbar">
    <div class="nav-brand">
        <span class="hebrew-brand">פָּרָשָׁה</span>
        <span class="brand-sub">Studie App</span>
    </div>
    <ul class="nav-links">
        <li><a href="<?= BASE_URL ?>/" class="<?= ($activePage ?? '') === 'home' ? 'active' : '' ?>">
            <svg viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9,22 9,12 15,12 15,22"/></svg>
            Huidig
        </a></li>
        <li><a href="<?= BASE_URL ?>/archief.php" class="<?= ($activePage ?? '') === 'archief' ? 'active' : '' ?>">
            <svg viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
            Archief
        </a></li>
        <li><a href="<?= BASE_URL ?>/zoeken.php" class="<?= ($activePage ?? '') === 'zoeken' ? 'active' : '' ?>">
            <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            Zoeken
        </a></li>
    </ul>
</nav>

<main class="main-content">

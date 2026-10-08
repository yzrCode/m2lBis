<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?> · <?= e($roleLabel) ?> · M2L Football Club</title>
<meta name="robots" content="noindex">
<meta name="csrf-token" content="<?= e(\App\Core\Csrf::token()) ?>">
<script src="<?= e(asset('js/theme.js')) ?>"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Archivo:wdth,wght@62..125,500..900&family=Figtree:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset('css/base.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/dashboard.css')) ?>">
</head>
<body data-role="<?= e($user['role']) ?>">

<a class="skip-link" href="#contenu">Aller au contenu</a>
<?php require BASE_PATH . '/app/Views/partials/sprite.php'; ?>

<div class="toast-region" id="toasts" role="status" aria-live="polite"></div>
<div class="scrim" id="scrim" hidden></div>
<?php if (!empty($flash)): ?>
  <div hidden data-flash data-type="<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
<?php endif; ?>

<div class="app">

  <!-- ============ Navigation globale (rendue côté serveur selon le rôle) ============ -->
  <aside class="sidebar" id="sidebar" aria-label="Menu principal">
    <div class="brand">
      <svg class="brand__logo" viewBox="0 0 40 46" aria-hidden="true"><use href="#crest-m2l"/></svg>
      <div>
        <div class="brand__name">M2L</div>
        <div class="brand__sub">Football Club</div>
      </div>
      <button class="sidebar__close" type="button" data-nav-close aria-label="Fermer le menu">
        <svg class="ic" aria-hidden="true"><use href="#i-x"/></svg>
      </button>
    </div>

    <nav class="nav" aria-label="Navigation principale">
      <ul id="nav">
        <?php foreach ($menu as $item): ?>
          <?php if (!empty($item['section'])): ?><li class="nav__section" role="presentation"><?= e($item['section']) ?></li><?php endif; ?>
          <li>
            <a href="<?= e($item['href']) ?>" data-icon="<?= e($item['icon']) ?>"<?= $item['current'] ? ' aria-current="page"' : '' ?>>
              <svg class="ic" aria-hidden="true"><use href="#i-<?= e($item['icon']) ?>"/></svg>
              <span><?= e($item['label']) ?></span>
              <?php if (!empty($item['count'])): ?><span class="count" aria-label="<?= (int) $item['count'] ?> en attente"><?= (int) $item['count'] ?></span><?php endif; ?>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
    </nav>

    <div class="season">
      <p class="season__t"><?= e($season['label']) ?></p>
      <p class="season__s"><?= e($season['progress']['label']) ?></p>
      <div class="bar bar--light" role="img" aria-label="<?= (int) $season['progress']['percent'] ?> % de la saison écoulée"><span style="--w:<?= (int) $season['progress']['percent'] ?>%"></span></div>
    </div>

    <!-- Profil : nom, prénom, e-mail et rôle viennent de la session du membre connecté -->
    <div class="user">
      <div class="user__row">
        <span class="avatar" aria-hidden="true"><?= e(initials($fullName)) ?></span>
        <div class="user__txt">
          <p class="user__name"><?= e($fullName) ?></p>
          <p class="user__mail" title="<?= e($user['email']) ?>"><?= e($user['email']) ?></p>
          <p class="user__role"><?= e($roleLabel) ?></p>
        </div>
      </div>
      <form class="logout-form" method="post" action="/deconnexion">
        <?= csrf_field() ?>
        <button class="logout" type="submit"><svg class="ic" aria-hidden="true"><use href="#i-logout"/></svg>Se déconnecter</button>
      </form>
    </div>
  </aside>

  <div class="shell">
    <header class="topbar">
      <button class="icon-btn topbar__burger" type="button" data-nav-open aria-controls="sidebar" aria-expanded="false" aria-label="Ouvrir le menu">
        <svg class="ic" aria-hidden="true"><use href="#i-menu"/></svg>
      </button>
      <span class="topbar__brand">M2L</span>
      <button class="search" type="button" data-palette-open aria-label="Rechercher">
        <svg class="ic" aria-hidden="true"><use href="#i-search"/></svg>
        <span>Rechercher une page ou une action</span>
        <kbd id="kbdHint">Ctrl K</kbd>
      </button>
      <span class="topbar__spacer"></span>
      <button class="icon-btn" type="button" data-theme-toggle aria-label="Changer de thème"></button>
      <button class="icon-btn" type="button" data-goto-alerts aria-label="Aller aux notifications">
        <svg class="ic" aria-hidden="true"><use href="#i-bell"/></svg>
        <span class="badge-n" id="bellCount" hidden></span>
      </button>
      <span class="avatar" title="<?= e($fullName) ?>" aria-hidden="true"><?= e(initials($fullName)) ?></span>
    </header>

    <main class="main" id="contenu" tabindex="-1"><?= $content ?></main>
  </div>
</div>

<!-- Palette de commandes -->
<dialog id="palette" aria-label="Recherche rapide">
  <div class="pal__head">
    <svg class="ic" aria-hidden="true"><use href="#i-search"/></svg>
    <input id="paletteInput" type="text" role="combobox" aria-expanded="true" aria-controls="paletteList" aria-autocomplete="list" placeholder="Rechercher une page ou une action" autocomplete="off">
    <kbd>Échap</kbd>
  </div>
  <ul id="paletteList" role="listbox" aria-label="Résultats"></ul>
</dialog>

<?php if (isset($payload)): ?>
<!-- Données du tableau de bord : uniquement celles du rôle du membre connecté -->
<script id="dashboard-data" type="application/json"><?= json_for_html($payload) ?></script>
<?php endif; ?>
<script src="<?= e(asset('js/dashboard.js')) ?>" defer></script>
</body>
</html>

<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= echapperHtml($club['name']) ?> · Résultats, classements, calendriers et inscriptions</title>
<meta name="description" content="Suivez les résultats, les classements et les calendriers de toutes les équipes du <?= echapperHtml($club['name']) ?>, et inscrivez-vous pour la <?= echapperHtml(mb_strtolower($season['label'])) ?>.">
<script src="<?= echapperHtml(urlRessource('js/theme.js')) ?>"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Archivo:wdth,wght@62..125,500..900&family=Figtree:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= echapperHtml(urlRessource('css/base.css')) ?>">
<link rel="stylesheet" href="<?= echapperHtml(urlRessource('css/site.css')) ?>">
</head>
<body class="site">
<a class="skip-link" href="#contenu">Aller au contenu</a>
<?php require BASE_PATH . '/app/Views/partials/sprite.php'; ?>

<header class="site-header" id="top">
  <div class="wrap site-header__in">
    <a class="site-brand" href="/" aria-label="<?= echapperHtml($club['name']) ?>, accueil">
      <svg class="site-brand__crest" viewBox="0 0 40 46" aria-hidden="true"><use href="#crest-m2l-red"/></svg>
      <span><strong>M2L</strong><small>Football Club</small></span>
    </a>
    <nav class="site-nav" id="siteNav" aria-label="Navigation du site">
      <a href="#resultats">Résultats</a>
      <a href="#classements">Classements</a>
      <a href="#calendriers">Calendriers</a>
      <a href="#actualites">Actualités</a>
      <a href="#evenements">Événements</a>
      <a href="#inscriptions">Inscriptions</a>
    </nav>
    <div class="site-header__actions">
      <button class="icon-btn" type="button" data-theme-toggle aria-label="Changer de thème"></button>
      <?php if (!empty($member)): ?>
        <a class="btn btn--primary btn--sm" href="/tableau-de-bord">Mon tableau de bord</a>
      <?php else: ?>
        <a class="btn btn--primary btn--sm" href="/connexion"><svg class="ic ic--sm" aria-hidden="true"><use href="#i-lock"/></svg>Espace membre</a>
      <?php endif; ?>
      <button class="icon-btn site-burger" type="button" data-site-burger aria-controls="siteNav" aria-expanded="false" aria-label="Ouvrir le menu">
        <svg class="ic" aria-hidden="true"><use href="#i-menu"/></svg>
      </button>
    </div>
  </div>
</header>

<main id="contenu"><?= $content ?></main>

<footer class="site-footer">
  <div class="wrap site-footer__grid">
    <div>
      <a class="site-brand site-brand--light" href="/">
        <svg class="site-brand__crest" viewBox="0 0 40 46" aria-hidden="true"><use href="#crest-m2l"/></svg>
        <span><strong>M2L</strong><small>Football Club</small></span>
      </a>
      <p class="site-footer__about">Club de football depuis <?= (int) $club['founded'] ?>. Du U7 aux vétérans, une école de foot reconnue et un projet sportif ouvert à toutes et tous.</p>
    </div>
    <div>
      <h2>Le club</h2>
      <address>
        <?= echapperHtml($club['address']) ?><br><?= echapperHtml($club['city']) ?><br>
        <a href="tel:<?= echapperHtml(preg_replace('/\s+/', '', $club['phone'])) ?>"><?= echapperHtml($club['phone']) ?></a><br>
        <a href="mailto:<?= echapperHtml($club['email']) ?>"><?= echapperHtml($club['email']) ?></a>
      </address>
    </div>
    <div>
      <h2>Permanence</h2>
      <p><?= echapperHtml($club['permanence']) ?>, au club-house.</p>
      <p>Inscriptions, boutique et renseignements.</p>
    </div>
    <div>
      <h2>Liens utiles</h2>
      <ul>
        <li><a href="#inscriptions">S'inscrire</a></li>
        <li><a href="/connexion">Espace membre</a></li>
      </ul>
    </div>
  </div>
  <div class="wrap site-footer__legal">
    <p>© <?= date('Y') ?> <?= echapperHtml($club['name']) ?>. Tous droits réservés.</p>
  </div>
</footer>

<script src="<?= echapperHtml(urlRessource('js/site.js')) ?>" defer></script>
</body>
</html>

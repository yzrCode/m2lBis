<header class="page-head">
  <div>
    <div class="page-head__row"><h1><?= echapperHtml($item['label']) ?></h1></div>
    <p class="page-head__lead">Section réservée au rôle « <?= echapperHtml($roleLabel) ?> ».</p>
  </div>
</header>
<section class="card module" aria-labelledby="module-title">
  <span class="kpi__icon"><svg class="ic ic--lg" aria-hidden="true"><use href="#i-<?= echapperHtml($item['icon']) ?>"/></svg></span>
  <h2 id="module-title"><?= echapperHtml($item['label']) ?></h2>
  <p>Cette section n'est pas encore branchée sur vos données. Le contrôle d'accès, le menu et la navigation sont en place : il ne reste qu'à créer le contrôleur et la vue de ce module.</p>
  <a class="btn btn--ghost" href="/tableau-de-bord">Retour au tableau de bord</a>
</section>

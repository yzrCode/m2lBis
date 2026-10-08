<header class="page-head">
  <div>
    <div class="page-head__row">
      <h1><?= e($hello) ?>, <?= e($user['prenom']) ?></h1>
      <span class="role-chip"><svg class="ic ic--sm" aria-hidden="true"><use href="#i-shield"/></svg><span><span class="sr-only">Rôle actif : </span><?= e($roleLabel) ?></span></span>
    </div>
    <p class="page-head__lead"><?= e($lead) ?></p>
  </div>
  <div class="page-head__side">
    <p class="page-head__date"><strong><?= e(\App\Core\Fr::long(new DateTimeImmutable('now'))) ?></strong>, <?= e($season['label']) ?></p>
    <a class="btn btn--primary" href="<?= e($primaryAction['href']) ?>" data-primary-action>
      <svg class="ic" aria-hidden="true"><use href="#i-<?= e($primaryAction['icon']) ?>"/></svg><?= e($primaryAction['label']) ?>
    </a>
  </div>
</header>

<div class="hero-row" id="heroRow"><!-- rendu par dashboard.js : hero + agenda --></div>

<section aria-labelledby="kpi-title">
  <h2 class="sr-only" id="kpi-title">Indicateurs clés</h2>
  <ul class="kpis" id="kpis"><!-- rendu par dashboard.js --></ul>
</section>

<div class="workspace" id="workspace"><!-- rendu par dashboard.js : panneaux du rôle + notifications --></div>

<main class="error-page" id="contenu">
  <a class="error-page__brand" href="/"><svg class="crest-sm" viewBox="0 0 40 46" aria-hidden="true"><use href="#crest-m2l-red"/></svg><span>M2L</span></a>
  <p class="error-page__code"><?= (int) $status ?></p>
  <h1><?= e($title) ?></h1>
  <p><?= e($text) ?></p>
  <div class="error-page__cta">
    <a class="btn btn--primary" href="/">Retour à l'accueil</a>
    <?php if (!empty($member)): ?><a class="btn btn--ghost" href="/tableau-de-bord">Mon tableau de bord</a><?php endif; ?>
  </div>
</main>

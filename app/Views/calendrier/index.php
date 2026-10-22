<header class="page-head">
  <div>
    <div class="page-head__row"><h1>Calendrier</h1></div>
    <p class="page-head__lead">Matchs et événements enregistrés pour votre rôle et vos équipes.</p>
  </div>
</header>

<section class="card" aria-labelledby="calendrier-title">
  <div class="card__head">
    <h2 class="card__title" id="calendrier-title"><?= echapperHtml(ucfirst(\App\Core\DatesFrancaises::mois($mois))) ?> <?= echapperHtml($mois->format('Y')) ?></h2>
    <nav class="calendar-nav" aria-label="Changer de mois">
      <a class="btn btn--ghost btn--sm" href="/calendrier?mois=<?= echapperHtml($moisPrecedent) ?>" aria-label="Mois précédent">← Précédent</a>
      <a class="btn btn--ghost btn--sm" href="/calendrier" aria-label="Revenir au mois actuel">Aujourd’hui</a>
      <a class="btn btn--ghost btn--sm" href="/calendrier?mois=<?= echapperHtml($moisSuivant) ?>" aria-label="Mois suivant">Suivant →</a>
    </nav>
  </div>
  <div class="card__body">
    <?php if ($evenements === []): ?>
      <p class="licencies-note">Aucun match ou événement n’est programmé dans la base pour ce mois.</p>
    <?php endif; ?>
    <div class="calendar-grid" role="grid" aria-label="Calendrier du mois">
      <?php foreach (['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'] as $jourSemaine): ?>
        <div class="calendar-grid__weekday" role="columnheader"><?= echapperHtml($jourSemaine) ?></div>
      <?php endforeach; ?>
      <?php foreach ($grille as $jour): ?>
        <?php if ($jour === null): ?>
          <div class="calendar-grid__day calendar-grid__day--empty" role="gridcell" aria-hidden="true"></div>
        <?php else: ?>
          <section class="calendar-grid__day" role="gridcell" aria-label="<?= echapperHtml($jour['date']) ?>">
            <h3 class="calendar-grid__number"><?= (int) $jour['numero'] ?></h3>
            <?php foreach ($jour['evenements'] as $evenement): ?>
              <article class="calendar-event calendar-event--<?= echapperHtml($evenement['type']) ?>">
                <time datetime="<?= echapperHtml(str_replace(' ', 'T', $evenement['date_heure'])) ?>"><?= echapperHtml($evenement['heure']) ?></time>
                <strong><?= echapperHtml($evenement['titre']) ?></strong>
                <?php if ($evenement['lieu'] !== ''): ?><span><?= echapperHtml($evenement['lieu']) ?></span><?php endif; ?>
              </article>
            <?php endforeach; ?>
          </section>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>
  </div>
</section>

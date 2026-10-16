<header class="page-head">
  <div>
    <div class="page-head__row"><h1>Dossiers</h1></div>
    <p class="page-head__lead">Consultez les dossiers des licenciés et l’état de leurs pièces.</p>
  </div>
</header>

<section class="card" aria-labelledby="dossiers-title">
  <div class="card__head">
    <h2 class="card__title" id="dossiers-title">Dossiers déposés</h2>
  </div>
  <div class="card__body">
    <?php if ($dossiers === []): ?>
      <p class="empty">Aucun dossier à afficher.</p>
    <?php else: ?>
      <div class="table-wrap">
        <table class="tbl">
          <thead>
            <tr>
              <th scope="col">Licencié</th>
              <th scope="col">Catégorie</th>
              <th scope="col">Date</th>
              <th scope="col">Pièces</th>
              <th scope="col">État</th>
              <th scope="col"><span class="sr-only">Action</span></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($dossiers as $dossier): ?>
              <tr>
                <?php // Les valeurs issues de la base sont échappées avant l'affichage HTML. ?>
                <td data-label="Licencié">
                  <div class="person">
                    <span><?= echapperHtml($dossier['name']) ?><small><?= echapperHtml($dossier['email']) ?></small></span>
                  </div>
                </td>
                <td data-label="Catégorie"><?= echapperHtml($dossier['category']) ?></td>
                <td data-label="Date"><?= echapperHtml($dossier['date']) ?></td>
                <td data-label="Pièces"><?= echapperHtml($dossier['pieces']) ?></td>
                <td data-label="État">
                  <span class="badge badge--<?= echapperHtml($dossier['status']['tone']) ?>">
                    <?= echapperHtml($dossier['status']['label']) ?>
                  </span>
                </td>
                <td data-label="Action">
                  <?php // La relance n'est proposée que si des pièces manquent et que le dossier est en attente. ?>
                  <?php if ($dossier['canRelance']): ?>
                    <form method="post" action="/dossiers/<?= (int) $dossier['id'] ?>/relancer">
                      <?= champCsrf() ?>
                      <button class="btn btn--ghost btn--sm" type="submit">Relancer</button>
                    </form>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</section>

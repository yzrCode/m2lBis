<header class="page-head">
  <div>
    <div class="page-head__row"><h1>Équipes</h1></div>
    <p class="page-head__lead">
      <?= $estPresident
        ? 'Consultez les effectifs et gérez les éducateurs responsables.'
        : 'Retrouvez les équipes qui vous sont attribuées.' ?>
    </p>
  </div>
</header>

<?php if ($estPresident && $repartition !== []): ?>
  <section class="card" aria-labelledby="repartition-title">
    <div class="card__head">
      <h2 class="card__title" id="repartition-title">Équipes par catégorie</h2>
    </div>
    <div class="card__body">
      <p>
        <?php foreach ($repartition as $categorie => $nombre): ?>
          <strong><?= echapperHtml($categorie) ?></strong> :
          <?= (int) $nombre ?> équipe<?= $nombre > 1 ? 's' : '' ?><?= $categorie !== array_key_last($repartition) ? ' · ' : '' ?>
        <?php endforeach; ?>
      </p>
    </div>
  </section>
<?php endif; ?>

<section class="card" aria-labelledby="equipes-title">
  <div class="card__head">
    <h2 class="card__title" id="equipes-title">
      <?= $estPresident ? 'Toutes les équipes' : 'Mes équipes' ?>
    </h2>
  </div>
  <div class="card__body">
    <?php if ($equipes === []): ?>
      <p class="empty">
        <?= $estPresident
          ? 'Aucune équipe n’est encore enregistrée.'
          : 'Aucune équipe ne vous est actuellement attribuée.' ?>
      </p>
    <?php else: ?>
      <div class="table-wrap">
        <table class="tbl">
          <thead>
            <tr>
              <th scope="col">Équipe</th>
              <th scope="col">Catégorie</th>
              <th scope="col">Saison</th>
              <th scope="col">Effectif</th>
              <th scope="col">Éducateur</th>
              <th scope="col">Dernier match</th>
              <?php if ($estPresident): ?>
                <th scope="col">Modifier l’éducateur</th>
              <?php endif; ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($equipes as $equipe): ?>
              <tr>
                <td data-label="Équipe">
                  <a class="equipe-nom" href="/equipes/<?= (int) $equipe['id_equipes'] ?>">
                    <span><?= echapperHtml($equipe['nom_equipes']) ?></span>
                    <small>Voir l’effectif <span aria-hidden="true">→</span></small>
                  </a>
                </td>
                <td data-label="Catégorie">
                  <?= echapperHtml($equipe['code_categories']) ?>
                  <small><?= echapperHtml($equipe['libelle_categories']) ?></small>
                </td>
                <td data-label="Saison"><?= echapperHtml($equipe['libelle_saisons']) ?></td>
                <td data-label="Effectif">
                  <?= (int) $equipe['total_joueurs'] ?> joueur<?= (int) $equipe['total_joueurs'] > 1 ? 's' : '' ?>
                </td>
                <td data-label="Éducateur">
                  <?= echapperHtml($equipe['nom_educateur'] ?: 'Non attribué') ?>
                </td>
                <td data-label="Dernier match">
                  <?php if ($equipe['dernier_match'] === null): ?>
                    <span class="resultat-point resultat-point--absent" aria-label="Aucun match joué"></span>
                  <?php else: ?>
                    <?php $match = $equipe['dernier_match']; ?>
                    <?php
                      $resultat = 'absent';
                      $libelleResultat = 'Résultat indisponible';
                      if ($match['buts_m2l'] !== null && $match['buts_adversaire'] !== null) {
                          if ((int) $match['buts_m2l'] > (int) $match['buts_adversaire']) {
                              $resultat = 'victoire';
                              $libelleResultat = 'Victoire';
                          } elseif ((int) $match['buts_m2l'] < (int) $match['buts_adversaire']) {
                              $resultat = 'defaite';
                              $libelleResultat = 'Défaite';
                          } else {
                              $resultat = 'nul';
                              $libelleResultat = 'Match nul';
                          }
                      }
                    ?>
                    <span
                      class="resultat-point resultat-point--<?= $resultat ?>"
                      role="img"
                      aria-label="<?= echapperHtml($libelleResultat) ?>"
                      title="<?= echapperHtml($libelleResultat) ?>"
                    ></span>
                  <?php endif; ?>
                </td>
                <?php if ($estPresident): ?>
                  <td data-label="Modifier l’éducateur">
                    <form class="gestion-ligne-form" method="post" action="/equipes/educateur">
                      <?= champCsrf() ?>
                      <input type="hidden" name="id_equipe" value="<?= (int) $equipe['id_equipes'] ?>">
                      <label>
                        <span class="sr-only">Éducateur de <?= echapperHtml($equipe['nom_equipes']) ?></span>
                        <select name="id_educateur">
                          <option value="">Non attribué</option>
                          <?php foreach ($educateurs as $educateur): ?>
                            <option
                              value="<?= (int) $educateur['id_utilisateurs'] ?>"
                              <?= (int) $equipe['educateur_id'] === (int) $educateur['id_utilisateurs'] ? 'selected' : '' ?>
                            >
                              <?= echapperHtml($educateur['nom_complet']) ?>
                            </option>
                          <?php endforeach; ?>
                        </select>
                      </label>
                      <button class="btn btn--ghost btn--sm" type="submit">Enregistrer</button>
                    </form>
                  </td>
                <?php endif; ?>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</section>

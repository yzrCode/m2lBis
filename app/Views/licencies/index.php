<?php $parametresFiltres = ['q' => $filtres['recherche'], 'categorie' => $filtres['categorie'], 'statut' => $filtres['statut']]; ?>

<header class="page-head">
  <div>
    <div class="page-head__row"><h1>Licenciés</h1></div>
    <p class="page-head__lead"><?= count($licencies) ?> résultat<?= count($licencies) > 1 ? 's' : '' ?> dans l’annuaire.</p>
  </div>
  <div class="page-head__side">
    <a class="btn btn--primary" href="/licencies/nouveau">Ajouter un licencié</a>
  </div>
</header>

<section class="card licencies-card" aria-labelledby="licencies-title">
  <div class="card__head">
    <h2 class="card__title" id="licencies-title">Annuaire du club</h2>
    <div class="licencies-export">
      <a class="btn btn--ghost btn--sm" href="/licencies/export.csv?<?= echapperHtml(http_build_query($parametresFiltres)) ?>">Exporter CSV</a>
      <a class="btn btn--ghost btn--sm" href="/licencies/export.xlsx?<?= echapperHtml(http_build_query($parametresFiltres)) ?>">Exporter Excel</a>
      <a class="btn btn--ghost btn--sm" href="/licencies/imprimer?<?= echapperHtml(http_build_query($parametresFiltres)) ?>">Imprimer / PDF</a>
    </div>
  </div>
  <div class="card__body">
    <?php if ($saison === null): ?>
      <p class="licencies-note">Aucune saison n’est définie en base : le statut de cotisation apparaît « Non renseignée » jusqu’à la configuration d’une saison.</p>
    <?php else: ?>
      <p class="licencies-note">Les statuts de cotisation affichés correspondent à la saison <?= echapperHtml($saison['libelle']) ?>.</p>
    <?php endif; ?>

    <form class="licencies-filters" method="get" action="/licencies">
      <label class="field-block">
        <span>Rechercher par nom, prénom ou numéro de licence</span>
        <input type="search" name="q" value="<?= echapperHtml($filtres['recherche']) ?>" placeholder="Ex. Martin ou numéro de licence">
      </label>
      <label class="field-block">
        <span>Catégorie</span>
        <select name="categorie">
          <option value="">Toutes les catégories</option>
          <?php foreach ($categories as $categorie): ?>
            <option value="<?= echapperHtml($categorie) ?>"<?= $filtres['categorie'] === $categorie ? ' selected' : '' ?>><?= echapperHtml($categorie) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="field-block">
        <span>Statut</span>
        <select name="statut">
          <option value="">Tous les statuts</option>
          <option value="actifs"<?= $filtres['statut'] === 'actifs' ? ' selected' : '' ?>>Actifs</option>
          <option value="inactifs"<?= $filtres['statut'] === 'inactifs' ? ' selected' : '' ?>>Inactifs</option>
          <option value="payee"<?= $filtres['statut'] === 'payee' ? ' selected' : '' ?>>Cotisation à jour</option>
          <option value="a_payer"<?= $filtres['statut'] === 'a_payer' ? ' selected' : '' ?>>Cotisation à payer</option>
          <option value="en_retard"<?= $filtres['statut'] === 'en_retard' ? ' selected' : '' ?>>Cotisation en retard</option>
          <option value="sans_cotisation"<?= $filtres['statut'] === 'sans_cotisation' ? ' selected' : '' ?>>Sans cotisation renseignée</option>
        </select>
      </label>
      <div class="licencies-filters__actions">
        <button class="btn btn--primary" type="submit">Filtrer</button>
        <a class="btn btn--ghost" href="/licencies">Effacer</a>
      </div>
    </form>

    <?php if ($licencies === []): ?>
      <p class="empty">Aucun licencié ne correspond à ces critères.</p>
    <?php else: ?>
      <div class="table-wrap">
        <table class="tbl">
          <thead>
            <tr>
              <th scope="col">Nom et prénom</th>
              <th scope="col">Catégorie</th>
              <th scope="col">N° de licence</th>
              <th scope="col">Téléphone / e-mail</th>
              <th scope="col">Cotisation</th>
              <th scope="col">Compte</th>
              <th scope="col">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($licencies as $licencie): ?>
              <tr>
                <td data-label="Nom et prénom">
                  <div class="person">
                    <span><?= echapperHtml($licencie['nom_complet']) ?><small><?= echapperHtml($licencie['email']) ?></small></span>
                  </div>
                </td>
                <td data-label="Catégorie"><?= echapperHtml($licencie['categorie'] ?? '—') ?></td>
                <td data-label="N° de licence"><?= echapperHtml($licencie['num_licence'] ?? '—') ?></td>
                <td data-label="Téléphone / e-mail">
                  <?= echapperHtml($licencie['telephone'] ?? '—') ?>
                  <small class="licencies-email"><?= echapperHtml($licencie['email']) ?></small>
                </td>
                <td data-label="Cotisation">
                  <span class="badge badge--<?= echapperHtml($licencie['cotisation_affichage']['tone']) ?>">
                    <?= echapperHtml($licencie['cotisation_affichage']['label']) ?>
                  </span>
                </td>
                <td data-label="Compte">
                  <span class="badge badge--<?= $licencie['actif'] ? 'ok' : 'neutral' ?>">
                    <?= $licencie['actif'] ? 'Actif' : 'Inactif' ?>
                  </span>
                </td>
                <td data-label="Actions">
                  <div class="licencies-row-actions">
                    <a class="btn btn--ghost btn--sm" href="/licencies/<?= (int) $licencie['id'] ?>">Consulter</a>
                    <a class="btn btn--ghost btn--sm" href="/licencies/<?= (int) $licencie['id'] ?>/modifier">Éditer</a>
                    <form method="post" action="/licencies/<?= (int) $licencie['id'] ?>/<?= $licencie['actif'] ? 'desactiver' : 'reactiver' ?>">
                      <?= champCsrf() ?>
                      <button class="btn btn--ghost btn--sm" type="submit"><?= $licencie['actif'] ? 'Désactiver' : 'Réactiver' ?></button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</section>

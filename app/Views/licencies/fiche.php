<header class="page-head">
  <div>
    <div class="page-head__row"><h1>Fiche licencié</h1></div>
    <p class="page-head__lead"><?= echapperHtml($licencie['nom_complet']) ?></p>
  </div>
  <div class="page-head__side licencies-row-actions">
    <a class="btn btn--ghost" href="/licencies">Retour à l’annuaire</a>
    <a class="btn btn--primary" href="/licencies/<?= (int) $licencie['id'] ?>/modifier">Modifier</a>
  </div>
</header>

<section class="card" aria-labelledby="fiche-title">
  <div class="card__head">
    <h2 class="card__title" id="fiche-title"><?= echapperHtml($licencie['nom_complet']) ?></h2>
  </div>
  <div class="card__body">
    <dl class="licencie-details">
      <div><dt>E-mail de connexion</dt><dd><?= echapperHtml($licencie['email']) ?></dd></div>
      <div><dt>Téléphone</dt><dd><?= echapperHtml($licencie['telephone'] ?: 'Non renseigné') ?></dd></div>
      <div><dt>Numéro de licence</dt><dd><?= echapperHtml($licencie['num_licence'] ?: 'Non renseigné') ?></dd></div>
      <div><dt>Catégorie</dt><dd><?= echapperHtml($licencie['categorie'] ?: 'Non renseignée') ?></dd></div>
      <div><dt>Date de naissance</dt><dd><?= echapperHtml($licencie['date_naissance'] ?: 'Non renseignée') ?></dd></div>
      <div>
        <dt>État du compte</dt>
        <dd><span class="badge badge--<?= $licencie['actif'] ? 'ok' : 'neutral' ?>"><?= $licencie['actif'] ? 'Actif' : 'Inactif' ?></span></dd>
      </div>
      <div>
        <dt>Cotisation<?= $saison !== null ? ' · ' . echapperHtml($saison['libelle']) : '' ?></dt>
        <dd><span class="badge badge--<?= echapperHtml($licencie['cotisation_affichage']['tone']) ?>"><?= echapperHtml($licencie['cotisation_affichage']['label']) ?></span></dd>
      </div>
    </dl>
    <div class="dossier-form__actions">
      <form method="post" action="/licencies/<?= (int) $licencie['id'] ?>/<?= $licencie['actif'] ? 'desactiver' : 'reactiver' ?>">
        <?= champCsrf() ?>
        <button class="btn btn--ghost" type="submit"><?= $licencie['actif'] ? 'Désactiver le compte' : 'Réactiver le compte' ?></button>
      </form>
    </div>
  </div>
</section>

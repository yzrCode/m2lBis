<?php $parametresFiltres = ['q' => $filtres['recherche'], 'categorie' => $filtres['categorie'], 'statut' => $filtres['statut']]; ?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Annuaire des licenciés · M2L Football Club</title>
  <link rel="stylesheet" href="<?= echapperHtml(urlRessource('css/base.css')) ?>">
  <link rel="stylesheet" href="<?= echapperHtml(urlRessource('css/dashboard.css')) ?>">
</head>
<body>
  <main class="print-page">
    <div class="print-actions">
      <button class="btn btn--primary" type="button" data-print>Imprimer / enregistrer en PDF</button>
      <a class="btn btn--ghost" href="/licencies?<?= echapperHtml(http_build_query($parametresFiltres)) ?>">Retour à l’annuaire</a>
    </div>
    <h1>Annuaire des licenciés</h1>
    <p class="print-meta">Liste préparée par <?= echapperHtml($nomComplet) ?> · <?= count($licencies) ?> licencié<?= count($licencies) > 1 ? 's' : '' ?></p>

    <?php if ($licencies === []): ?>
      <p>Aucun licencié ne correspond aux filtres sélectionnés.</p>
    <?php else: ?>
      <div class="table-wrap">
        <table class="tbl">
          <thead>
            <tr>
              <th scope="col">Nom et prénom</th>
              <th scope="col">Catégorie</th>
              <th scope="col">N° de licence</th>
              <th scope="col">Téléphone</th>
              <th scope="col">E-mail</th>
              <th scope="col">Cotisation</th>
              <th scope="col">Compte</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($licencies as $licencie): ?>
              <tr>
                <td><?= echapperHtml($licencie['nom_complet']) ?></td>
                <td><?= echapperHtml($licencie['categorie'] ?? '—') ?></td>
                <td><?= echapperHtml($licencie['num_licence'] ?? '—') ?></td>
                <td><?= echapperHtml($licencie['telephone'] ?? '—') ?></td>
                <td><?= echapperHtml($licencie['email']) ?></td>
                <td><?= echapperHtml($licencie['cotisation_affichage']['label']) ?></td>
                <td><?= $licencie['actif'] ? 'Actif' : 'Inactif' ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </main>
  <script src="<?= echapperHtml(urlRessource('js/licencies-print.js')) ?>" defer></script>
</body>
</html>

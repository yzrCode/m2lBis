<header class="page-head">
  <div>
    <div class="page-head__row"><h1><?= echapperHtml($equipe['nom_equipes']) ?></h1></div>
    <p class="page-head__lead">
      <?= echapperHtml($equipe['code_categories']) ?> ·
      <?= echapperHtml($equipe['libelle_saisons']) ?> ·
      <?= count($joueurs) ?> licencié<?= count($joueurs) > 1 ? 's' : '' ?>
    </p>
  </div>
  <div class="page-head__side">
    <a class="btn btn--ghost" href="/equipes">← Retour aux équipes</a>
  </div>
</header>

<section class="card" aria-labelledby="effectif-title">
  <div class="card__head">
    <h2 class="card__title" id="effectif-title">Effectif de l’équipe</h2>
  </div>
  <div class="card__body">
    <?php if ($joueurs === []): ?>
      <p class="empty">Aucun licencié n’est encore associé à cette équipe.</p>
    <?php else: ?>
      <div class="table-wrap">
        <table class="tbl">
          <thead>
            <tr>
              <th scope="col">Licencié</th>
              <th scope="col">Numéro de licence</th>
              <th scope="col">Statut</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($joueurs as $joueur): ?>
              <tr>
                <td data-label="Licencié" class="effectif-nom">
                  <?= echapperHtml(trim($joueur['prenom'] . ' ' . $joueur['nom_utilisateurs'])) ?>
                </td>
                <td data-label="Numéro de licence">
                  <?= echapperHtml($joueur['num_licence'] ?: 'Non renseigné') ?>
                </td>
                <td data-label="Statut">
                  <span class="badge badge--<?= (int) $joueur['actif'] === 1 ? 'ok' : 'neutral' ?>">
                    <?= (int) $joueur['actif'] === 1 ? 'Actif' : 'Inactif' ?>
                  </span>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</section>

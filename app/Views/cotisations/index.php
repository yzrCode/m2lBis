<header class="page-head">
  <div>
    <div class="page-head__row"><h1>Cotisations</h1></div>
    <p class="page-head__lead">Suivez les montants et les paiements enregistrés pour chaque licencié.</p>
  </div>
</header>

<section class="card cotisation-card" aria-labelledby="cotisations-title">
  <div class="card__head">
    <h2 class="card__title" id="cotisations-title">Cotisations par saison</h2>
  </div>
  <div class="card__body">
    <p class="licencies-note">Le suivi est manuel : cette page n’encaisse pas de paiement. Les cotisations affichées concernent les licenciés actifs.</p>

    <?php if ($saisons !== []): ?>
      <form class="gestion-filtres" method="get" action="/cotisations">
        <label class="field-block">
          <span>Saison</span>
          <select name="saison">
            <?php foreach ($saisons as $optionSaison): ?>
              <option value="<?= (int) $optionSaison['id'] ?>"<?= $saison !== null && (int) $saison['id'] === (int) $optionSaison['id'] ? ' selected' : '' ?>>
                <?= echapperHtml($optionSaison['libelle']) ?><?= $optionSaison['en_cours'] ? ' (en cours)' : '' ?>
              </option>
            <?php endforeach; ?>
          </select>
        </label>
        <label class="field-block">
          <span>Rechercher un licencié</span>
          <input type="search" name="q" value="<?= echapperHtml($filtres['recherche']) ?>" placeholder="Nom, prénom ou licence">
        </label>
        <label class="field-block">
          <span>Statut du paiement</span>
          <select name="statut">
            <option value="">Tous</option>
            <option value="a_payer"<?= $filtres['statut'] === 'a_payer' ? ' selected' : '' ?>>À payer</option>
            <option value="payee"<?= $filtres['statut'] === 'payee' ? ' selected' : '' ?>>Payée</option>
            <option value="en_retard"<?= $filtres['statut'] === 'en_retard' ? ' selected' : '' ?>>En retard</option>
          </select>
        </label>
        <div class="gestion-filtres__actions">
          <button class="btn btn--primary" type="submit">Filtrer</button>
          <a class="btn btn--ghost" href="/cotisations?saison=<?= (int) $saison['id'] ?>">Effacer</a>
        </div>
      </form>

      <?php if ($cotisations === []): ?>
        <p class="empty">Aucun licencié actif ne correspond à ces critères.</p>
      <?php else: ?>
        <div class="table-wrap">
          <table class="tbl">
            <thead>
              <tr>
                <th scope="col">Licencié</th>
                <th scope="col">N° de licence</th>
                <th scope="col">Montant</th>
                <th scope="col">Statut actuel</th>
                <th scope="col">Enregistrer un changement</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($cotisations as $cotisation): ?>
                <tr>
                  <td data-label="Licencié"><?= echapperHtml(trim($cotisation['prenom'] . ' ' . $cotisation['nom'])) ?><small class="licencies-email"><?= echapperHtml($cotisation['email']) ?></small></td>
                  <td data-label="N° de licence"><?= echapperHtml($cotisation['num_licence'] ?: '—') ?></td>
                  <td data-label="Montant"><?= echapperHtml(number_format((float) $cotisation['montant'], 2, ',', ' ')) ?> €</td>
                  <td data-label="Statut actuel">
                    <?php
                      $statutAffiche = [
                          'payee' => ['label' => 'Payée', 'tone' => 'ok'],
                          'a_payer' => ['label' => 'À payer', 'tone' => 'warn'],
                          'en_retard' => ['label' => 'En retard', 'tone' => 'danger'],
                      ][$cotisation['statut']] ?? ['label' => 'À vérifier', 'tone' => 'neutral'];
                    ?>
                    <span class="badge badge--<?= echapperHtml($statutAffiche['tone']) ?>"><?= echapperHtml($statutAffiche['label']) ?></span>
                  </td>
                  <td data-label="Enregistrer un changement">
                    <form class="gestion-ligne-form" method="post" action="/cotisations/<?= (int) $cotisation['id'] ?>/modifier">
                      <?= champCsrf() ?>
                      <input type="hidden" name="saison_id" value="<?= (int) $saison['id'] ?>">
                      <label>
                        <span class="sr-only">Montant en euros</span>
                        <input type="number" name="montant" min="0" max="999999.99" step="0.01" value="<?= echapperHtml(number_format((float) $cotisation['montant'], 2, '.', '')) ?>" required>
                      </label>
                      <label>
                        <span class="sr-only">Statut</span>
                        <select name="statut">
                          <option value="a_payer"<?= $cotisation['statut'] === 'a_payer' ? ' selected' : '' ?>>À payer</option>
                          <option value="payee"<?= $cotisation['statut'] === 'payee' ? ' selected' : '' ?>>Payée</option>
                          <option value="en_retard"<?= $cotisation['statut'] === 'en_retard' ? ' selected' : '' ?>>En retard</option>
                        </select>
                      </label>
                      <button class="btn btn--ghost btn--sm" type="submit">Enregistrer</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    <?php else: ?>
      <p class="empty">Aucune saison n’est encore créée. Ajoute une saison ci-dessous pour commencer le suivi.</p>
    <?php endif; ?>
  </div>
</section>

<section class="card" aria-labelledby="saison-nouvelle-title">
  <div class="card__head">
    <h2 class="card__title" id="saison-nouvelle-title">Créer une saison</h2>
  </div>
  <div class="card__body">
    <p class="licencies-note">Les dates ne peuvent pas chevaucher une saison déjà enregistrée. Une cotisation « à payer » est créée pour chaque licencié actif.</p>
    <form class="gestion-saison-form" method="post" action="/cotisations/saisons">
      <?= champCsrf() ?>
      <label class="field-block">
        <span>Nom de la saison</span>
        <input type="text" name="libelle" maxlength="20" placeholder="Ex. 2026-2027" required>
      </label>
      <label class="field-block">
        <span>Date de début</span>
        <input type="date" name="date_debut" required>
      </label>
      <label class="field-block">
        <span>Date de fin</span>
        <input type="date" name="date_fin" required>
      </label>
      <button class="btn btn--primary" type="submit">Créer la saison</button>
    </form>
  </div>
</section>

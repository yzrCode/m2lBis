<?php
$statutsAchats = [
    'paye' => ['label' => 'Payé', 'tone' => 'ok'],
    'payee' => ['label' => 'Payé', 'tone' => 'ok'],
    'en_attente' => ['label' => 'En attente', 'tone' => 'warn'],
    'en attente' => ['label' => 'En attente', 'tone' => 'warn'],
    'a_valider' => ['label' => 'À valider', 'tone' => 'warn'],
    'à valider' => ['label' => 'À valider', 'tone' => 'warn'],
    'annule' => ['label' => 'Annulé', 'tone' => 'danger'],
    'annulee' => ['label' => 'Annulé', 'tone' => 'danger'],
];
?>

<header class="page-head finance-head">
  <div>
    <div class="page-head__row"><h1>Finances</h1></div>
    <p class="page-head__lead">Suivez les recettes de cotisations, les dépenses et les achats fournisseurs du club.</p>
  </div>
  <?php if ($saisons !== []): ?>
    <form class="finance-season" method="get" action="/finances">
      <label for="finance-season">Saison des cotisations</label>
      <select id="finance-season" name="saison">
        <?php foreach ($saisons as $optionSaison): ?>
          <option
            value="<?= (int) $optionSaison['id_saisons'] ?>"
            <?= $saison !== null && (int) $saison['id_saisons'] === (int) $optionSaison['id_saisons'] ? 'selected' : '' ?>
          >
            <?= echapperHtml($optionSaison['libelle_saisons']) ?><?= (int) $optionSaison['en_cours'] === 1 ? ' · En cours' : '' ?>
          </option>
        <?php endforeach; ?>
      </select>
      <button class="btn btn--primary" type="submit">Afficher</button>
    </form>
  <?php endif; ?>
</header>

<?php if ($saison !== null): ?>
  <section class="finance-kpis" aria-label="Indicateurs financiers">
    <article class="finance-kpi finance-kpi--income">
      <span class="finance-kpi__icon" aria-hidden="true">+</span>
      <div>
        <p class="finance-kpi__label">Cotisations encaissées</p>
        <p class="finance-kpi__value"><?= echapperHtml(number_format((float) ($indicateurs['recettes'] ?? 0), 2, ',', ' ')) ?> €</p>
        <p class="finance-kpi__hint">Saison <?= echapperHtml($saison['libelle_saisons']) ?></p>
      </div>
    </article>
    <article class="finance-kpi finance-kpi--expense">
      <span class="finance-kpi__icon" aria-hidden="true">−</span>
      <div>
        <p class="finance-kpi__label">Dépenses réglées</p>
        <p class="finance-kpi__value"><?= echapperHtml(number_format((float) ($indicateurs['depenses'] ?? 0), 2, ',', ' ')) ?> €</p>
        <p class="finance-kpi__hint">Achats fournisseurs enregistrés</p>
      </div>
    </article>
    <article class="finance-kpi finance-kpi--balance">
      <span class="finance-kpi__icon" aria-hidden="true">=</span>
      <div>
        <p class="finance-kpi__label">Solde recettes − dépenses</p>
        <p class="finance-kpi__value"><?= echapperHtml(number_format((float) ($indicateurs['solde'] ?? 0), 2, ',', ' ')) ?> €</p>
        <p class="finance-kpi__hint">Calcul indicatif pour la période</p>
      </div>
    </article>
    <article class="finance-kpi finance-kpi--pending">
      <span class="finance-kpi__icon" aria-hidden="true">!</span>
      <div>
        <p class="finance-kpi__label">Achats à valider</p>
        <p class="finance-kpi__value"><?= (int) ($indicateurs['achats_en_attente'] ?? 0) ?></p>
        <p class="finance-kpi__hint">En attente de paiement ou validation</p>
      </div>
    </article>
  </section>
<?php else: ?>
  <section class="card finance-empty-season" aria-labelledby="finance-empty-title">
    <div class="card__body">
      <h2 id="finance-empty-title">Aucune saison financière</h2>
      <p>Créez une saison dans l’onglet Cotisations pour afficher les recettes.</p>
    </div>
  </section>
<?php endif; ?>

<section class="card finance-purchases" aria-labelledby="finance-purchases-title">
  <div class="card__head finance-purchases__head">
    <div>
      <h2 class="card__title" id="finance-purchases-title">Achats fournisseurs</h2>
      <p class="card__sub">Consultez les dépenses saisies et validez leur paiement.</p>
    </div>
    <span class="finance-count"><?= count($achats) ?> achat<?= count($achats) > 1 ? 's' : '' ?></span>
  </div>
  <div class="card__body">
    <?php if ($achats === []): ?>
      <div class="finance-empty">
        <span class="finance-empty__icon" aria-hidden="true">€</span>
        <p class="finance-empty__title">Aucun achat enregistré</p>
        <p>Les achats fournisseurs apparaîtront ici lorsqu’ils seront saisis.</p>
      </div>
    <?php else: ?>
      <div class="table-wrap">
        <table class="tbl finance-table">
          <thead>
            <tr>
              <th scope="col">Fournisseur</th>
              <th scope="col">Description</th>
              <th scope="col">Date</th>
              <th scope="col">Montant</th>
              <th scope="col">Statut</th>
              <th scope="col">Saisi par</th>
              <th scope="col">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($achats as $achat): ?>
              <?php
                $statutBrut = mb_strtolower(trim((string) $achat['statut_fournisseurs']));
                $statut = $statutsAchats[$statutBrut] ?? [
                    'label' => $statutBrut === '' ? 'Non renseigné' : ucfirst(str_replace('_', ' ', $statutBrut)),
                    'tone' => 'neutral',
                ];
                $estPaye = in_array($statutBrut, ['paye', 'payee', 'payé', 'payée'], true);
                $peutValider = in_array($statutBrut, ['en_attente', 'en attente', 'a_valider', 'à valider'], true);
              ?>
              <tr>
                <td data-label="Fournisseur" class="finance-supplier"><?= echapperHtml($achat['fournisseur']) ?></td>
                <td data-label="Description"><?= echapperHtml($achat['libelle_fournisseurs'] ?: '—') ?></td>
                <td data-label="Date">
                  <?= $achat['date_cmd'] ? echapperHtml(date('d/m/Y', strtotime($achat['date_cmd']))) : '—' ?>
                </td>
                <td data-label="Montant" class="finance-amount">
                  <?= echapperHtml(number_format((float) $achat['montant_ttc'], 2, ',', ' ')) ?> €
                </td>
                <td data-label="Statut">
                  <span class="badge badge--<?= echapperHtml($statut['tone']) ?>"><?= echapperHtml($statut['label']) ?></span>
                </td>
                <td data-label="Saisi par"><?= echapperHtml($achat['acheteur_nom'] ?: 'Non renseigné') ?></td>
                <td data-label="Action">
                  <?php if ($estPaye): ?>
                    <span class="finance-paid">Paiement validé</span>
                  <?php elseif ($peutValider): ?>
                    <form method="post" action="/finances/achats/payer">
                      <?= champCsrf() ?>
                      <input type="hidden" name="id_achat" value="<?= (int) $achat['id_achats_fournisseurs'] ?>">
                      <button class="btn btn--ghost btn--sm" type="submit">Valider le paiement</button>
                    </form>
                  <?php else: ?>
                    <span class="finance-no-action">—</span>
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

<p class="finance-footnote">
  Les achats fournisseurs ne sont pas associés à une saison dans la base : les dépenses affichées couvrent tous les achats payés.
</p>

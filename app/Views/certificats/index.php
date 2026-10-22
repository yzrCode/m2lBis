<header class="page-head">
  <div>
    <div class="page-head__row"><h1>Certificats</h1></div>
    <p class="page-head__lead">Suivi administratif des certificats pour les licenciés actifs.</p>
  </div>
</header>

<section class="card" aria-labelledby="certificats-title">
  <div class="card__head">
    <h2 class="card__title" id="certificats-title">État des certificats</h2>
  </div>
  <div class="card__body">
    <p class="licencies-note">Cette page conserve uniquement un statut et une éventuelle date de réception. Aucun document médical n’est envoyé ni stocké.</p>

    <?php if ($saisons !== []): ?>
      <form class="gestion-filtres" method="get" action="/certificats">
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
          <span>État du certificat</span>
          <select name="statut">
            <option value="">Tous</option>
            <option value="manquant"<?= $filtres['statut'] === 'manquant' ? ' selected' : '' ?>>Manquant</option>
            <option value="recu"<?= $filtres['statut'] === 'recu' ? ' selected' : '' ?>>Reçu</option>
            <option value="a_renouveler"<?= $filtres['statut'] === 'a_renouveler' ? ' selected' : '' ?>>À renouveler</option>
          </select>
        </label>
        <div class="gestion-filtres__actions">
          <button class="btn btn--primary" type="submit">Filtrer</button>
          <a class="btn btn--ghost" href="/certificats?saison=<?= (int) $saison['id'] ?>">Effacer</a>
        </div>
      </form>

      <?php if ($certificats === []): ?>
        <p class="empty">Aucun licencié actif ne correspond à ces critères.</p>
      <?php else: ?>
        <div class="table-wrap">
          <table class="tbl">
            <thead>
              <tr>
                <th scope="col">Licencié</th>
                <th scope="col">N° de licence</th>
                <th scope="col">État</th>
                <th scope="col">Date de réception</th>
                <th scope="col">Mettre à jour</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($certificats as $certificat): ?>
                <tr>
                  <td data-label="Licencié"><?= echapperHtml(trim($certificat['prenom'] . ' ' . $certificat['nom'])) ?></td>
                  <td data-label="N° de licence"><?= echapperHtml($certificat['num_licence'] ?: '—') ?></td>
                  <td data-label="État">
                    <?php
                      $statutAffiche = [
                          'recu' => ['label' => 'Reçu', 'tone' => 'ok'],
                          'manquant' => ['label' => 'Manquant', 'tone' => 'danger'],
                          'a_renouveler' => ['label' => 'À renouveler', 'tone' => 'warn'],
                      ][$certificat['statut']] ?? ['label' => 'À vérifier', 'tone' => 'neutral'];
                    ?>
                    <span class="badge badge--<?= echapperHtml($statutAffiche['tone']) ?>"><?= echapperHtml($statutAffiche['label']) ?></span>
                  </td>
                  <td data-label="Date de réception"><?= echapperHtml($certificat['date_reception'] ?? '—') ?></td>
                  <td data-label="Mettre à jour">
                    <form class="gestion-ligne-form" method="post" action="/certificats/<?= (int) $certificat['id'] ?>/modifier">
                      <?= champCsrf() ?>
                      <input type="hidden" name="saison_id" value="<?= (int) $saison['id'] ?>">
                      <label>
                        <span class="sr-only">État du certificat</span>
                        <select name="statut">
                          <option value="manquant"<?= $certificat['statut'] === 'manquant' ? ' selected' : '' ?>>Manquant</option>
                          <option value="recu"<?= $certificat['statut'] === 'recu' ? ' selected' : '' ?>>Reçu</option>
                          <option value="a_renouveler"<?= $certificat['statut'] === 'a_renouveler' ? ' selected' : '' ?>>À renouveler</option>
                        </select>
                      </label>
                      <label>
                        <span class="sr-only">Date de réception (facultative)</span>
                        <input type="date" name="date_reception" value="<?= echapperHtml($certificat['date_reception'] ?? '') ?>">
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
      <p class="empty">Aucune saison n’est encore créée. Crée d’abord une saison dans la page <a href="/cotisations">Cotisations</a>.</p>
    <?php endif; ?>
  </div>
</section>

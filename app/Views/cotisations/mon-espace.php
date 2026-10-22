<header class="page-head">
  <div>
    <div class="page-head__row"><h1>Ma cotisation</h1></div>
    <p class="page-head__lead">Consultez les informations de paiement enregistrées par le club.</p>
  </div>
</header>

<section class="card" aria-labelledby="ma-cotisation-title">
  <div class="card__head">
    <h2 class="card__title" id="ma-cotisation-title">
      <?= $saison === null ? 'Aucune saison' : 'Saison ' . echapperHtml($saison['libelle']) ?>
    </h2>
  </div>
  <div class="card__body">
    <?php if ($saison === null): ?>
      <p class="empty">Le club n’a pas encore configuré de saison.</p>
    <?php elseif ($cotisation === null): ?>
      <p class="empty">La cotisation n’est pas encore renseignée. Contactez le secrétariat.</p>
    <?php else: ?>
      <?php
        $statutAffiche = [
            'payee' => ['label' => 'Payée', 'tone' => 'ok'],
            'a_payer' => ['label' => 'À payer', 'tone' => 'warn'],
            'en_retard' => ['label' => 'En retard', 'tone' => 'danger'],
        ][$cotisation['statut']] ?? ['label' => 'À vérifier', 'tone' => 'neutral'];
      ?>
      <dl class="licencie-details">
        <div><dt>Montant enregistré</dt><dd><?= echapperHtml(number_format((float) $cotisation['montant'], 2, ',', ' ')) ?> €</dd></div>
        <div><dt>Statut</dt><dd><span class="badge badge--<?= echapperHtml($statutAffiche['tone']) ?>"><?= echapperHtml($statutAffiche['label']) ?></span></dd></div>
      </dl>
      <p class="licencies-note">Cette page affiche le suivi du club. Aucun paiement en ligne n’est proposé.</p>
    <?php endif; ?>
  </div>
</section>

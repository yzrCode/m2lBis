<header class="page-head">
  <div>
    <div class="page-head__row"><h1>Demandes de comptes</h1></div>
    <p class="page-head__lead">Examinez les demandes des membres du personnel, puis acceptez ou refusez l’accès à l’espace membre.</p>
  </div>
</header>

<section class="card" aria-labelledby="requests-title">
  <div class="card__head">
    <h2 class="card__title" id="requests-title">Demandes en attente</h2>
  </div>
  <div class="card__body">
    <?php if ($requests === []): ?>
      <p class="empty">Aucune demande de compte en attente.</p>
    <?php else: ?>
      <div class="table-wrap">
        <table class="tbl">
          <thead>
            <tr>
              <th scope="col">Membre</th>
              <th scope="col">Rôle demandé</th>
              <th scope="col">Date</th>
              <th scope="col"><span class="sr-only">Actions</span></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($requests as $request): ?>
              <tr>
                <td>
                  <div class="person">
                    <span><?= echapperHtml($request['prenom'] . ' ' . $request['nom']) ?><small><?= echapperHtml($request['email']) ?></small></span>
                  </div>
                </td>
                <td><?= echapperHtml($roles[$request['role']]['label'] ?? $request['role']) ?></td>
                <td><?= echapperHtml(date('d/m/Y H:i', strtotime($request['cree_le']))) ?></td>
                <td>
                  <div class="request-actions">
                    <form method="post" action="/utilisateurs/demandes/<?= (int) $request['id'] ?>/accepter">
                      <?= champCsrf() ?>
                      <button class="btn btn--primary btn--sm" type="submit">Accepter</button>
                    </form>
                    <form method="post" action="/utilisateurs/demandes/<?= (int) $request['id'] ?>/refuser">
                      <?= champCsrf() ?>
                      <button class="btn btn--ghost btn--sm" type="submit">Refuser</button>
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

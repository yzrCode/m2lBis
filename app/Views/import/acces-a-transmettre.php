<main class="login" id="contenu">
  <section class="login__panel" aria-labelledby="import-result-title">
    <div class="login__box">
      <h1 id="import-result-title">Accès à transmettre</h1>
      <p class="login__lead">
        <?= count($comptes) ?> e-mail(s) sur <?= (int) $nombreTotal ?> n’ont pas pu être envoyé(s).
        Les comptes ont été créés ; transmettez ces accès aux licenciés par un moyen sécurisé.
      </p>

      <ul>
        <?php foreach ($comptes as $compte): ?>
          <li>
            <p><strong><?= echapperHtml($compte['prenom'] . ' ' . $compte['nom']) ?></strong></p>
            <p>E-mail : <?= echapperHtml($compte['email']) ?></p>
            <p>Mot de passe provisoire : <code><?= echapperHtml($compte['mot_de_passe_temporaire']) ?></code></p>
          </li>
        <?php endforeach; ?>
      </ul>

      <p class="notice notice--error" role="alert">
        Cette page affiche les mots de passe provisoires une seule fois. Ne la partagez pas et ne l’imprimez pas.
      </p>
      <a class="btn btn--primary btn--block" href="/tableau-de-bord">Retour au tableau de bord</a>
    </div>
  </section>
</main>

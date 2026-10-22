<main class="login" id="contenu">
  <section class="login__art" aria-hidden="true">
    <svg class="login__pitch" viewBox="0 0 640 420"><use href="#pitch"/></svg>
    <a class="login__brand" href="/"><svg class="login__crest" viewBox="0 0 40 46"><use href="#crest-m2l"/></svg><span>M2L</span></a>
    <p class="login__claim">Votre première connexion commence ici.</p>
  </section>

  <section class="login__panel" aria-labelledby="password-title">
    <div class="login__box">
      <h1 id="password-title">Choisissez votre mot de passe</h1>
      <p class="login__lead">Pour sécuriser votre compte, remplacez le mot de passe provisoire reçu par e-mail.</p>

      <?php if (!empty($flash)): ?>
        <p class="notice notice--error" role="alert"><?= echapperHtml($flash['message']) ?></p>
      <?php endif; ?>

      <form method="post" action="/mot-de-passe-provisoire">
        <?= champCsrf() ?>
        <label class="field-block">
          <span>Nouveau mot de passe (12 caractères minimum)</span>
          <input type="password" name="password" autocomplete="new-password" minlength="12" maxlength="72" required autofocus>
        </label>
        <label class="field-block">
          <span>Confirmer le nouveau mot de passe</span>
          <input type="password" name="password_confirmation" autocomplete="new-password" minlength="12" maxlength="72" required>
        </label>
        <button class="btn btn--primary btn--block" type="submit">Enregistrer mon mot de passe</button>
      </form>
      <form method="post" action="/deconnexion">
        <?= champCsrf() ?>
        <button class="btn btn--ghost btn--block" type="submit">Se déconnecter</button>
      </form>
    </div>
  </section>
</main>

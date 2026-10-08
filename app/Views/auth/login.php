<main class="login" id="contenu">
  <section class="login__art" aria-hidden="true">
    <svg class="login__pitch" viewBox="0 0 640 420"><use href="#pitch"/></svg>
    <a class="login__brand" href="/"><svg class="login__crest" viewBox="0 0 40 46"><use href="#crest-m2l"/></svg><span>M2L</span></a>
    <p class="login__claim">Un seul espace pour toute la vie du club.</p>
  </section>

  <section class="login__panel" aria-labelledby="login-title">
    <div class="login__box">
      <a class="login__back" href="/">Retour au site du club</a>
      <h1 id="login-title">Espace membre</h1>
      <p class="login__lead">Connectez-vous avec l'adresse e-mail de votre compte club.</p>

      <?php if (!empty($flash)): ?>
        <p class="notice notice--<?= e($flash['type'] === 'error' ? 'error' : 'ok') ?>" role="alert"><?= e($flash['message']) ?></p>
      <?php endif; ?>

      <form method="post" action="/connexion" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="next" value="<?= e($next) ?>">
        <label class="field-block">
          <span>Adresse e-mail</span>
          <input type="email" name="email" value="<?= e($email) ?>" autocomplete="username" required autofocus>
        </label>
        <label class="field-block">
          <span>Mot de passe</span>
          <input type="password" name="password" autocomplete="current-password" required>
        </label>
        <button class="btn btn--primary btn--block" type="submit"<?= $locked ? ' disabled' : '' ?>>Se connecter</button>
      </form>
    </div>
  </section>
</main>

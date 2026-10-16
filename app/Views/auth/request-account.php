<main class="login" id="contenu">
  <section class="login__art" aria-hidden="true">
    <svg class="login__pitch" viewBox="0 0 640 420"><use href="#pitch"/></svg>
    <a class="login__brand" href="/"><svg class="login__crest" viewBox="0 0 40 46"><use href="#crest-m2l"/></svg><span>M2L</span></a>
    <p class="login__claim">Le club avance grâce à son équipe.</p>
  </section>

  <section class="login__panel" aria-labelledby="request-title">
    <div class="login__box">
      <a class="login__back" href="/connexion">Retour à la connexion</a>
      <h1 id="request-title">Demande de compte</h1>
      <p class="login__lead">Membres du personnel : soumettez votre demande au président du club. Votre compte sera activé après son approbation.</p>

      <?php if (!empty($flash)): ?>
        <p class="notice notice--<?= echapperHtml($flash['type'] === 'error' ? 'error' : 'ok') ?>" role="alert"><?= echapperHtml($flash['message']) ?></p>
      <?php endif; ?>

      <form method="post" action="/creation-compte">
        <?= champCsrf() ?>
        <label class="sr-only">Ne pas remplir<input type="text" name="website" tabindex="-1" autocomplete="off"></label>
        <label class="field-block">
          <span>Prénom</span>
          <input type="text" name="prenom" value="<?= echapperHtml($old['prenom'] ?? '') ?>" autocomplete="given-name" maxlength="80" required autofocus>
        </label>
        <label class="field-block">
          <span>Nom</span>
          <input type="text" name="nom" value="<?= echapperHtml($old['nom'] ?? '') ?>" autocomplete="family-name" maxlength="80" required>
        </label>
        <label class="field-block">
          <span>Adresse e-mail</span>
          <input type="email" name="email" value="<?= echapperHtml($old['email'] ?? '') ?>" autocomplete="email" maxlength="190" required>
        </label>
        <label class="field-block">
          <span>Rôle demandé</span>
          <select name="role" required>
            <option value="">Sélectionnez votre rôle</option>
            <?php foreach ($roles as $key => $role): ?>
              <option value="<?= echapperHtml($key) ?>"<?= ($old['role'] ?? '') === $key ? ' selected' : '' ?>><?= echapperHtml($role['label']) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label class="field-block">
          <span>Mot de passe (12 caractères minimum)</span>
          <input type="password" name="password" autocomplete="new-password" minlength="12" required>
        </label>
        <label class="field-block">
          <span>Confirmer le mot de passe</span>
          <input type="password" name="password_confirmation" autocomplete="new-password" minlength="12" required>
        </label>
        <button class="btn btn--primary btn--block" type="submit">Envoyer ma demande</button>
      </form>
    </div>
  </section>
</main>

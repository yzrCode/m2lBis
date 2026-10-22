<header class="page-head">
  <div>
    <div class="page-head__row"><h1>Nouveau dossier</h1></div>
    <p class="page-head__lead">Saisissez les informations de contact du licencié.</p>
  </div>
</header>

<section class="card" aria-labelledby="nouveau-dossier-title">
  <div class="card__head">
    <h2 class="card__title" id="nouveau-dossier-title">Informations du dossier</h2>
  </div>
  <div class="card__body">
    <p>Cette page crée un dossier de suivi, mais pas un compte de connexion. Pour créer des comptes licenciés, utilisez l’import des licenciés.</p>

    <form class="dossier-form" method="post" action="/dossiers/nouveau">
      <?= champCsrf() ?>

      <label class="field-block">
        <span>Prénom</span>
        <input type="text" name="prenom" value="<?= echapperHtml($old['prenom'] ?? '') ?>" autocomplete="given-name" maxlength="60" required autofocus>
      </label>

      <label class="field-block">
        <span>Nom</span>
        <input type="text" name="nom" value="<?= echapperHtml($old['nom'] ?? '') ?>" autocomplete="family-name" maxlength="60" required>
      </label>

      <label class="field-block">
        <span>E-mail de contact</span>
        <input type="email" name="email" value="<?= echapperHtml($old['email'] ?? '') ?>" autocomplete="email" maxlength="150" required>
      </label>

      <label class="field-block">
        <span>Catégorie</span>
        <input type="text" name="categorie" value="<?= echapperHtml($old['categorie'] ?? '') ?>" maxlength="20" placeholder="Ex. U15" required>
      </label>

      <label class="field-block">
        <span>Date de naissance (facultative)</span>
        <input type="date" name="date_naissance" value="<?= echapperHtml($old['date_naissance'] ?? '') ?>">
      </label>

      <div class="dossier-form__actions">
        <button class="btn btn--primary" type="submit">Créer le dossier</button>
        <a class="btn btn--ghost" href="/dossiers">Annuler</a>
      </div>
    </form>
  </div>
</section>

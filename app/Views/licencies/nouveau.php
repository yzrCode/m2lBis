<header class="page-head">
  <div>
    <div class="page-head__row"><h1>Ajouter un licencié</h1></div>
    <p class="page-head__lead">Le compte sera lié à un dossier et ses accès seront envoyés par e-mail.</p>
  </div>
</header>

<section class="card" aria-labelledby="nouveau-licencie-title">
  <div class="card__head">
    <h2 class="card__title" id="nouveau-licencie-title">Informations du licencié</h2>
  </div>
  <div class="card__body">
    <form class="licencie-form" method="post" action="/licencies/nouveau">
      <?= champCsrf() ?>
      <div class="licencie-form__grid">
        <label class="field-block">
          <span>Prénom</span>
          <input type="text" name="prenom" value="<?= echapperHtml($old['prenom'] ?? '') ?>" autocomplete="given-name" maxlength="60" required autofocus>
        </label>
        <label class="field-block">
          <span>Nom</span>
          <input type="text" name="nom" value="<?= echapperHtml($old['nom'] ?? '') ?>" autocomplete="family-name" maxlength="60" required>
        </label>
        <label class="field-block">
          <span>E-mail de connexion</span>
          <input type="email" name="email" value="<?= echapperHtml($old['email'] ?? '') ?>" autocomplete="email" maxlength="150" required>
        </label>
        <label class="field-block">
          <span>Téléphone (facultatif)</span>
          <input type="tel" name="telephone" value="<?= echapperHtml($old['telephone'] ?? '') ?>" autocomplete="tel" maxlength="20">
        </label>
        <label class="field-block">
          <span>Numéro de licence (facultatif)</span>
          <input type="text" name="num_licence" value="<?= echapperHtml($old['num_licence'] ?? '') ?>" maxlength="40">
        </label>
        <label class="field-block">
          <span>Catégorie</span>
          <input type="text" name="categorie" value="<?= echapperHtml($old['categorie'] ?? '') ?>" maxlength="20" placeholder="Ex. U15" required>
        </label>
        <label class="field-block">
          <span>Date de naissance (facultative)</span>
          <input type="date" name="date_naissance" value="<?= echapperHtml($old['date_naissance'] ?? '') ?>">
        </label>
      </div>
      <p class="licencie-form__help">Un mot de passe provisoire sera créé et envoyé à l’adresse e-mail de connexion. Le licencié devra le changer à sa première connexion.</p>
      <div class="dossier-form__actions">
        <button class="btn btn--primary" type="submit">Ajouter le licencié</button>
        <a class="btn btn--ghost" href="/licencies">Annuler</a>
      </div>
    </form>
  </div>
</section>

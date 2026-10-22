<header class="page-head">
  <div>
    <div class="page-head__row"><h1>Modifier le licencié</h1></div>
    <p class="page-head__lead"><?= echapperHtml($licencie['nom_complet']) ?></p>
  </div>
</header>

<section class="card" aria-labelledby="modifier-licencie-title">
  <div class="card__head">
    <h2 class="card__title" id="modifier-licencie-title">Informations du licencié</h2>
  </div>
  <div class="card__body">
    <form class="licencie-form" method="post" action="/licencies/<?= (int) $licencie['id'] ?>/modifier">
      <?= champCsrf() ?>
      <div class="licencie-form__grid">
        <label class="field-block">
          <span>Prénom</span>
          <input type="text" name="prenom" value="<?= echapperHtml($old['prenom'] ?? $licencie['prenom']) ?>" maxlength="60" required>
        </label>
        <label class="field-block">
          <span>Nom</span>
          <input type="text" name="nom" value="<?= echapperHtml($old['nom'] ?? $licencie['nom']) ?>" maxlength="60" required>
        </label>
        <label class="field-block">
          <span>E-mail de connexion</span>
          <input type="email" name="email" value="<?= echapperHtml($old['email'] ?? $licencie['email']) ?>" maxlength="150" required>
        </label>
        <label class="field-block">
          <span>Téléphone (facultatif)</span>
          <input type="tel" name="telephone" value="<?= echapperHtml($old['telephone'] ?? $licencie['telephone'] ?? '') ?>" maxlength="20">
        </label>
        <label class="field-block">
          <span>Numéro de licence (facultatif)</span>
          <input type="text" name="num_licence" value="<?= echapperHtml($old['num_licence'] ?? $licencie['num_licence'] ?? '') ?>" maxlength="40">
        </label>
        <label class="field-block">
          <span>Catégorie</span>
          <input type="text" name="categorie" value="<?= echapperHtml($old['categorie'] ?? $licencie['categorie'] ?? '') ?>" maxlength="20" required>
        </label>
        <label class="field-block">
          <span>Date de naissance (facultative)</span>
          <input type="date" name="date_naissance" value="<?= echapperHtml($old['date_naissance'] ?? $licencie['date_naissance'] ?? '') ?>">
        </label>
      </div>

      <?php if ($saison !== null): ?>
        <label class="field-block licencie-form__cotisation">
          <span>Cotisation de la saison <?= echapperHtml($saison['libelle']) ?></span>
          <select name="cotisation">
            <option value="">Ne pas modifier</option>
            <option value="a_payer"<?= ($old['cotisation'] ?? $licencie['cotisation'] ?? '') === 'a_payer' ? ' selected' : '' ?>>À payer</option>
            <option value="payee"<?= ($old['cotisation'] ?? $licencie['cotisation'] ?? '') === 'payee' ? ' selected' : '' ?>>Payée</option>
            <option value="en_retard"<?= ($old['cotisation'] ?? $licencie['cotisation'] ?? '') === 'en_retard' ? ' selected' : '' ?>>En retard</option>
          </select>
        </label>
      <?php else: ?>
        <p class="licencies-note">Aucune saison n’est configurée : le statut de cotisation ne peut pas encore être modifié.</p>
      <?php endif; ?>

      <div class="dossier-form__actions">
        <button class="btn btn--primary" type="submit">Enregistrer les modifications</button>
        <a class="btn btn--ghost" href="/licencies/<?= (int) $licencie['id'] ?>">Annuler</a>
      </div>
    </form>
  </div>
</section>

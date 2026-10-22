<?php
//Accueil public. Variables : $club $season $stats $teams $next $results $standings $calendars
//$news $events $registration $flash $old $member
$libellesResultats = ['V' => ['Victoire', 'ok'], 'N' => ['Match nul', 'neutral'], 'D' => ['Défaite', 'danger']];
$libellesTerrains = ['H' => ['Domicile', 'ok'], 'A' => ['Extérieur', 'neutral'], 'N' => ['Terrain neutre', 'warn']];

//Liste d'onglets accessible (le comportement est dans site.js).
$afficherOnglets = static function (string $identifiant, string $libelle, array $elements): void { ?>
  <div class="tabs" role="tablist" aria-label="<?= echapperHtml($libelle) ?>" data-tabs="<?= echapperHtml($identifiant) ?>">
    <?php foreach ($elements as $indice => [$cle, $texte]): ?>
      <button class="tab" type="button" role="tab" id="<?= echapperHtml("$identifiant-tab-$cle") ?>" aria-controls="<?= echapperHtml("$identifiant-panel-$cle") ?>"
              aria-selected="<?= $indice === 0 ? 'true' : 'false' ?>" tabindex="<?= $indice === 0 ? '0' : '-1' ?>"><?= echapperHtml($texte) ?></button>
    <?php endforeach; ?>
  </div>
<?php };

$equipesAvecResultats = [];
foreach ($teams as $equipe) {
    foreach ($results as $resultat) {
        if ($resultat['team'] === $equipe['id']) { $equipesAvecResultats[] = $equipe; break; }
    }
}
$afficherResultat = static function (array $resultat) use ($libellesResultats): void { [$libelleIssue, $couleurIssue] = $libellesResultats[$resultat['outcome']]; ?>
  <article class="res res--<?= echapperHtml($resultat['outcome']) ?>">
    <header class="res__head"><span class="tag"><?= echapperHtml($resultat['teamName']) ?></span><span><?= echapperHtml($resultat['date']) ?>, <?= echapperHtml($resultat['competition']) ?></span></header>
    <p class="res__row<?= $resultat['usHome'] ? ' is-us' : '' ?>"><span><?= echapperHtml($resultat['home']) ?></span><strong><?= (int) $resultat['hs'] ?></strong></p>
    <p class="res__row<?= !$resultat['usHome'] ? ' is-us' : '' ?>"><span><?= echapperHtml($resultat['away']) ?></span><strong><?= (int) $resultat['as'] ?></strong></p>
    <span class="badge badge--<?= echapperHtml($couleurIssue) ?>"><?= echapperHtml($libelleIssue) ?></span>
  </article>
<?php };

$abreviationAdversaire = mb_strtoupper(mb_substr((string) preg_replace('/[^\p{L}]/u', '', $next['opponent']), 0, 3));
?>

<!-- ============================ Hero ============================ -->
<section class="hero-site" aria-labelledby="hero-title">
  <svg class="hero-site__pitch" viewBox="0 0 640 420" aria-hidden="true"><use href="#pitch"/></svg>
  <div class="wrap hero-site__grid">
    <div class="hero-site__text">
      <p class="hero-chip"><?= echapperHtml($season['label']) ?></p>
      <h1 id="hero-title"><?= (int) $stats[0]['value'] ?> licenciés.<br>Une seule couleur.</h1>
      <p class="hero-site__lead">Résultats, classements et calendriers de toutes nos équipes, actualités du club et inscriptions pour la saison qui commence.</p>
      <div class="hero-site__cta">
        <a class="btn btn--light" href="#inscriptions">S'inscrire pour la saison</a>
        <a class="btn btn--line" href="#resultats">Voir les résultats</a>
      </div>
    </div>

    <article class="matchcard" aria-labelledby="next-title">
      <header class="matchcard__head">
        <h2 id="next-title">Prochain match de l'équipe fanion</h2>
        <span class="hero-chip hero-chip--dark"><?= echapperHtml($next['competition']) ?></span>
      </header>
      <div class="matchcard__fixture">
        <?php
        $equipeALaMaison = '<div class="mc-team"><svg class="mc-crest" viewBox="0 0 40 46" aria-hidden="true"><use href="#crest-m2l"/></svg><p>M2L ' . echapperHtml($next['team']) . '</p></div>';
        $equipeAdverse = '<div class="mc-team"><span class="mc-crest mc-crest--away" aria-hidden="true">' . echapperHtml($abreviationAdversaire) . '</span><p>' . echapperHtml($next['opponent']) . '</p></div>';
        echo $next['home'] ? $equipeALaMaison . '<span class="mc-vs" aria-hidden="true">VS</span>' . $equipeAdverse : $equipeAdverse . '<span class="mc-vs" aria-hidden="true">VS</span>' . $equipeALaMaison;
        ?>
      </div>
      <p class="matchcard__when"><span class="sr-only">Coup d'envoi : </span><?= echapperHtml($next['label']) ?></p>
      <p class="matchcard__venue"><svg class="ic ic--sm" aria-hidden="true"><use href="#i-pin"/></svg><?= echapperHtml($next['venue']) ?>, <?= $next['home'] ? 'à domicile' : 'en déplacement' ?></p>
      <div class="countdown" data-countdown data-now="<?= echapperHtml($next['now']) ?>" data-kickoff="<?= echapperHtml($next['kickoff']) ?>" aria-hidden="true">
        <?php foreach (['jours', 'heures', 'min', 'sec'] as $u): ?><div class="cd"><strong data-cd="<?= echapperHtml($u) ?>">00</strong><span><?= echapperHtml($u) ?></span></div><?php endforeach; ?>
      </div>
    </article>
  </div>
</section>

<div class="wrap">
  <ul class="stats" aria-label="Le club en chiffres">
    <?php foreach ($stats as $s): ?>
      <li><strong><?= (int) $s['value'] ?></strong><span><?= echapperHtml($s['label']) ?></span></li>
    <?php endforeach; ?>
  </ul>
</div>

<!-- ============================ Résultats ============================ -->
<section class="section" id="resultats" aria-labelledby="t-resultats">
  <div class="wrap">
    <header class="section__head">
      <div><h2 id="t-resultats">Résultats</h2><p>Les derniers matchs de nos équipes, de la plus récente à la plus ancienne.</p></div>
      <?php $afficherOnglets('res', 'Filtrer par équipe', array_merge([['all', 'Toutes']], array_map(static fn ($t) => [$t['id'], $t['name']], $equipesAvecResultats))); ?>
    </header>
    <div class="results" role="tabpanel" id="res-panel-all" aria-labelledby="res-tab-all">
      <?php foreach (array_slice($results, 0, 6) as $resultat) { $afficherResultat($resultat); } ?>
    </div>
    <?php foreach ($equipesAvecResultats as $t): ?>
      <div class="results" role="tabpanel" id="res-panel-<?= echapperHtml($t['id']) ?>" aria-labelledby="res-tab-<?= echapperHtml($t['id']) ?>" hidden>
        <?php foreach ($results as $resultat) { if ($resultat['team'] === $t['id']) { $afficherResultat($resultat); } } ?>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<!-- ============================ Classements ============================ -->
<section class="section section--alt" id="classements" aria-labelledby="t-classements">
  <div class="wrap">
    <header class="section__head">
      <div><h2 id="t-classements">Classements</h2><p>Après trois journées. Le M2L apparaît en rouge dans chaque groupe.</p></div>
      <?php $afficherOnglets('std', 'Choisir un championnat', array_map(static fn ($t) => [$t['id'], $t['label']], $standings)); ?>
    </header>
    <?php foreach ($standings as $i => $t): ?>
      <div role="tabpanel" id="std-panel-<?= echapperHtml($t['id']) ?>" aria-labelledby="std-tab-<?= echapperHtml($t['id']) ?>"<?= $i ? ' hidden' : '' ?>>
        <p class="std-comp"><?= echapperHtml($t['competition']) ?></p>
        <div class="table-scroll">
          <table class="std">
            <caption class="sr-only">Classement <?= echapperHtml($t['label']) ?>, <?= echapperHtml($t['competition']) ?></caption>
            <thead><tr>
              <th scope="col">Rang</th><th scope="col">Équipe</th>
              <th scope="col"><abbr title="Matchs joués">J</abbr></th><th scope="col"><abbr title="Victoires">G</abbr></th>
              <th scope="col"><abbr title="Nuls">N</abbr></th><th scope="col"><abbr title="Défaites">P</abbr></th>
              <th scope="col"><abbr title="Différence de buts">Diff</abbr></th><th scope="col"><abbr title="Points">Pts</abbr></th>
              <th scope="col">Forme</th>
            </tr></thead>
            <tbody>
              <?php foreach ($t['rows'] as $row): ?>
                <tr<?= $row['us'] ? ' class="is-us"' : '' ?>>
                  <td class="std__rank"><?= (int) $row['rank'] ?></td>
                  <th scope="row"><?= echapperHtml($row['name']) ?></th>
                  <td><?= (int) $row['j'] ?></td><td><?= (int) $row['g'] ?></td><td><?= (int) $row['n'] ?></td><td><?= (int) $row['p'] ?></td>
                  <td><?= $row['diff'] > 0 ? '+' : '' ?><?= (int) $row['diff'] ?></td>
                  <td class="std__pts"><?= (int) $row['pts'] ?></td>
                  <td><span class="form"><?php foreach ($row['form'] as $f): ?><i class="fdot fdot--<?= echapperHtml($f) ?>"><span class="sr-only"><?= echapperHtml($libellesResultats[$f][0]) ?></span></i><?php endforeach; ?></span></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<!-- ============================ Calendriers ============================ -->
<section class="section" id="calendriers" aria-labelledby="t-calendriers">
  <div class="wrap">
    <header class="section__head">
      <div><h2 id="t-calendriers">Calendriers des équipes</h2><p>Les prochains rendez-vous, avec le lieu et l'heure du coup d'envoi.</p></div>
      <?php $afficherOnglets('cal', 'Choisir une équipe', array_map(static fn ($t) => [$t['id'], $t['name']], $teams)); ?>
    </header>
    <?php foreach ($teams as $i => $t): ?>
      <div role="tabpanel" id="cal-panel-<?= echapperHtml($t['id']) ?>" aria-labelledby="cal-tab-<?= echapperHtml($t['id']) ?>"<?= $i ? ' hidden' : '' ?>>
        <div class="cal-bar">
          <p><strong><?= echapperHtml($t['name']) ?></strong>, <?= echapperHtml($t['competition']) ?></p>
          <a class="btn btn--ghost btn--sm" href="/calendrier.ics?equipe=<?= echapperHtml($t['id']) ?>"><svg class="ic ic--sm" aria-hidden="true"><use href="#i-calendar"/></svg>Ajouter à mon agenda</a>
        </div>
        <ol class="fixtures">
          <?php foreach ($calendars[$t['id']] as $m): [$libelleTerrain, $couleurTerrain] = $libellesTerrains[$m['site']]; ?>
            <li class="fx">
              <div class="fx__date"><span><?= echapperHtml($m['weekday']) ?></span><strong><?= echapperHtml($m['day']) ?></strong><span><?= echapperHtml($m['month']) ?></span></div>
              <div class="fx__body">
                <p class="fx__title"><?= echapperHtml($m['title']) ?></p>
                <p class="fx__meta"><span><svg class="ic ic--sm" aria-hidden="true"><use href="#i-clock"/></svg><?= echapperHtml($m['time']) ?></span><span><svg class="ic ic--sm" aria-hidden="true"><use href="#i-pin"/></svg><?= echapperHtml($m['venue']) ?></span><span><?= echapperHtml($m['competition']) ?></span></p>
              </div>
              <span class="badge badge--<?= echapperHtml($couleurTerrain) ?>"><?= echapperHtml($libelleTerrain) ?></span>
            </li>
          <?php endforeach; ?>
        </ol>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<!-- ============================ Actualités ============================ -->
<section class="section section--alt" id="actualites" aria-labelledby="t-actualites">
  <div class="wrap">
    <header class="section__head"><div><h2 id="t-actualites">Actualités</h2><p>La vie du club, semaine après semaine.</p></div></header>
    <div class="news">
      <?php $tones = ['red', 'wine', 'ink', 'soft']; foreach ($news as $i => $n): ?>
        <article class="news-card<?= $i === 0 ? ' news-card--feat' : '' ?>">
          <div class="art art--<?= echapperHtml($tones[$i % 4]) ?>" aria-hidden="true">
            <svg viewBox="0 0 640 420" preserveAspectRatio="xMidYMid slice"><use href="#pitch"/></svg><span><?= echapperHtml($n['tag']) ?></span>
          </div>
          <div class="news-card__body">
            <p class="news-card__meta"><time datetime="<?= echapperHtml($n['iso']) ?>"><?= echapperHtml($n['dateLabel']) ?></time></p>
            <h3><?= echapperHtml($n['title']) ?></h3>
            <p><?= echapperHtml($n['excerpt']) ?></p>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ============================ Événements ============================ -->
<section class="section" id="evenements" aria-labelledby="t-evenements">
  <div class="wrap">
    <header class="section__head"><div><h2 id="t-evenements">Événements</h2><p>Tournois, stages et rendez-vous du club à venir.</p></div></header>
    <ul class="events">
      <?php foreach ($events as $ev): ?>
        <li class="event">
          <div class="event__date"><strong><?= echapperHtml($ev['day']) ?></strong><span><?= echapperHtml($ev['month']) ?></span></div>
          <div>
            <p class="event__tag"><span class="badge badge--neutral"><?= echapperHtml($ev['tag']) ?></span></p>
            <h3><?= echapperHtml($ev['title']) ?></h3>
            <p class="event__meta"><?= echapperHtml($ev['range']) ?>, <?= echapperHtml($ev['time']) ?>, <?= echapperHtml($ev['place']) ?></p>
            <p><?= echapperHtml($ev['text']) ?></p>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
</section>

<!-- ============================ Inscriptions ============================ -->
<section class="section section--red" id="inscriptions" aria-labelledby="t-inscriptions">
  <svg class="section--red__pitch" viewBox="0 0 640 420" aria-hidden="true"><use href="#pitch"/></svg>
  <div class="wrap reg">
    <div class="reg__info">
      <h2 id="t-inscriptions">Rejoignez le club</h2>
      <p class="reg__lead">Les inscriptions pour la <?= echapperHtml(mb_strtolower($season['label'])) ?> sont ouvertes jusqu'au <?= echapperHtml($registration['deadline']) ?>.</p>

      <ol class="steps">
        <?php foreach ($registration['steps'] as $i => $s): ?>
          <li><span class="steps__n" aria-hidden="true"><?= $i + 1 ?></span><div><h3><?= echapperHtml($s['title']) ?></h3><p><?= echapperHtml($s['text']) ?></p></div></li>
        <?php endforeach; ?>
      </ol>

      <h3 class="reg__sub">Cotisation annuelle</h3>
      <dl class="prices">
        <?php foreach ($registration['prices'] as $p): ?>
          <div><dt><?= echapperHtml($p['cat']) ?><small><?= echapperHtml($p['ages']) ?></small></dt><dd><?= echapperHtml(formaterMontant($p['price'])) ?></dd></div>
        <?php endforeach; ?>
      </dl>
    </div>

    <div class="reg__form card-white">
      <h3>Pré-inscription en ligne</h3>
      <p class="muted">Le secrétariat vous répond sous 48 heures.</p>
      <?php if (!empty($flash)): ?>
        <p class="notice notice--<?= echapperHtml($flash['type'] === 'error' ? 'error' : 'ok') ?>" role="status"><?= echapperHtml($flash['message']) ?></p>
      <?php endif; ?>
      <form method="post" action="/inscription" novalidate>
        <?= champCsrf() ?>
        <div class="form-grid">
          <label class="field-block"><span>Prénom</span><input name="prenom" value="<?= echapperHtml($old['prenom'] ?? '') ?>" autocomplete="given-name" required></label>
          <label class="field-block"><span>Nom</span><input name="nom" value="<?= echapperHtml($old['nom'] ?? '') ?>" autocomplete="family-name" required></label>
        </div>
        <label class="field-block"><span>Adresse e-mail</span><input type="email" name="email" value="<?= echapperHtml($old['email'] ?? '') ?>" autocomplete="email" required></label>
        <label class="field-block"><span>Catégorie</span>
          <select name="categorie" required>
            <option value="">Choisir…</option>
            <?php foreach ($registration['prices'] as $p): ?>
              <option value="<?= echapperHtml($p['cat']) ?>"<?= ($old['categorie'] ?? '') === $p['cat'] ? ' selected' : '' ?>><?= echapperHtml($p['cat']) ?>, <?= echapperHtml($p['ages']) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label class="field-block"><span>Message (facultatif)</span><textarea name="message" rows="3" maxlength="500"><?= echapperHtml($old['message'] ?? '') ?></textarea></label>
        <div class="hp" aria-hidden="true"><label>Ne pas remplir<input name="website" tabindex="-1" autocomplete="off"></label></div>
        <button class="btn btn--primary btn--block" type="submit">Envoyer ma demande</button>
      </form>
    </div>
  </div>
</section>

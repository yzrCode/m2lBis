<?php
/**
 * Accueil public. Variables : $club $season $stats $teams $next $results $standings $calendars
 * $news $events $registration $shop $flash $old $member
 */
$outcome = ['V' => ['Victoire', 'ok'], 'N' => ['Match nul', 'neutral'], 'D' => ['Défaite', 'danger']];
$siteLabel = ['H' => ['Domicile', 'ok'], 'A' => ['Extérieur', 'neutral'], 'N' => ['Terrain neutre', 'warn']];
$sprite = ['jersey' => 'p-jersey', 'hoodie' => 'p-hoodie', 'shorts' => 'p-shorts', 'scarf' => 'p-scarf', 'bag' => 'p-bag'];

/** Liste d'onglets accessible (le comportement est dans site.js). */
$tablist = static function (string $id, string $label, array $items): void { ?>
  <div class="tabs" role="tablist" aria-label="<?= e($label) ?>" data-tabs="<?= e($id) ?>">
    <?php foreach ($items as $i => [$key, $text]): ?>
      <button class="tab" type="button" role="tab" id="<?= e("$id-tab-$key") ?>" aria-controls="<?= e("$id-panel-$key") ?>"
              aria-selected="<?= $i === 0 ? 'true' : 'false' ?>" tabindex="<?= $i === 0 ? '0' : '-1' ?>"><?= e($text) ?></button>
    <?php endforeach; ?>
  </div>
<?php };

$resultTeams = [];
foreach ($teams as $t) {
    foreach ($results as $r) {
        if ($r['team'] === $t['id']) { $resultTeams[] = $t; break; }
    }
}
$rc = static function (array $r) use ($outcome): void { [$olabel, $otone] = $outcome[$r['outcome']]; ?>
  <article class="res res--<?= e($r['outcome']) ?>">
    <header class="res__head"><span class="tag"><?= e($r['teamName']) ?></span><span><?= e($r['date']) ?>, <?= e($r['competition']) ?></span></header>
    <p class="res__row<?= $r['usHome'] ? ' is-us' : '' ?>"><span><?= e($r['home']) ?></span><strong><?= (int) $r['hs'] ?></strong></p>
    <p class="res__row<?= !$r['usHome'] ? ' is-us' : '' ?>"><span><?= e($r['away']) ?></span><strong><?= (int) $r['as'] ?></strong></p>
    <span class="badge badge--<?= e($otone) ?>"><?= e($olabel) ?></span>
  </article>
<?php };

$oppShort = mb_strtoupper(mb_substr((string) preg_replace('/[^\p{L}]/u', '', $next['opponent']), 0, 3));
?>

<!-- ============================ Hero ============================ -->
<section class="hero-site" aria-labelledby="hero-title">
  <svg class="hero-site__pitch" viewBox="0 0 640 420" aria-hidden="true"><use href="#pitch"/></svg>
  <div class="wrap hero-site__grid">
    <div class="hero-site__text">
      <p class="hero-chip"><?= e($season['label']) ?></p>
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
        <span class="hero-chip hero-chip--dark"><?= e($next['competition']) ?></span>
      </header>
      <div class="matchcard__fixture">
        <?php
        $us = '<div class="mc-team"><svg class="mc-crest" viewBox="0 0 40 46" aria-hidden="true"><use href="#crest-m2l"/></svg><p>M2L ' . e($next['team']) . '</p></div>';
        $them = '<div class="mc-team"><span class="mc-crest mc-crest--away" aria-hidden="true">' . e($oppShort) . '</span><p>' . e($next['opponent']) . '</p></div>';
        echo $next['home'] ? $us . '<span class="mc-vs" aria-hidden="true">VS</span>' . $them : $them . '<span class="mc-vs" aria-hidden="true">VS</span>' . $us;
        ?>
      </div>
      <p class="matchcard__when"><span class="sr-only">Coup d'envoi : </span><?= e($next['label']) ?></p>
      <p class="matchcard__venue"><svg class="ic ic--sm" aria-hidden="true"><use href="#i-pin"/></svg><?= e($next['venue']) ?>, <?= $next['home'] ? 'à domicile' : 'en déplacement' ?></p>
      <div class="countdown" data-countdown data-now="<?= e($next['now']) ?>" data-kickoff="<?= e($next['kickoff']) ?>" aria-hidden="true">
        <?php foreach (['jours', 'heures', 'min', 'sec'] as $u): ?><div class="cd"><strong data-cd="<?= e($u) ?>">00</strong><span><?= e($u) ?></span></div><?php endforeach; ?>
      </div>
    </article>
  </div>
</section>

<div class="wrap">
  <ul class="stats" aria-label="Le club en chiffres">
    <?php foreach ($stats as $s): ?>
      <li><strong><?= (int) $s['value'] ?></strong><span><?= e($s['label']) ?></span></li>
    <?php endforeach; ?>
  </ul>
</div>

<!-- ============================ Résultats ============================ -->
<section class="section" id="resultats" aria-labelledby="t-resultats">
  <div class="wrap">
    <header class="section__head">
      <div><h2 id="t-resultats">Résultats</h2><p>Les derniers matchs de nos équipes, de la plus récente à la plus ancienne.</p></div>
      <?php $tablist('res', 'Filtrer par équipe', array_merge([['all', 'Toutes']], array_map(static fn ($t) => [$t['id'], $t['name']], $resultTeams))); ?>
    </header>
    <div class="results" role="tabpanel" id="res-panel-all" aria-labelledby="res-tab-all">
      <?php foreach (array_slice($results, 0, 6) as $r) { $rc($r); } ?>
    </div>
    <?php foreach ($resultTeams as $t): ?>
      <div class="results" role="tabpanel" id="res-panel-<?= e($t['id']) ?>" aria-labelledby="res-tab-<?= e($t['id']) ?>" hidden>
        <?php foreach ($results as $r) { if ($r['team'] === $t['id']) { $rc($r); } } ?>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<!-- ============================ Classements ============================ -->
<section class="section section--alt" id="classements" aria-labelledby="t-classements">
  <div class="wrap">
    <header class="section__head">
      <div><h2 id="t-classements">Classements</h2><p>Après trois journées. Le M2L apparaît en rouge dans chaque groupe.</p></div>
      <?php $tablist('std', 'Choisir un championnat', array_map(static fn ($t) => [$t['id'], $t['label']], $standings)); ?>
    </header>
    <?php foreach ($standings as $i => $t): ?>
      <div role="tabpanel" id="std-panel-<?= e($t['id']) ?>" aria-labelledby="std-tab-<?= e($t['id']) ?>"<?= $i ? ' hidden' : '' ?>>
        <p class="std-comp"><?= e($t['competition']) ?></p>
        <div class="table-scroll">
          <table class="std">
            <caption class="sr-only">Classement <?= e($t['label']) ?>, <?= e($t['competition']) ?></caption>
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
                  <th scope="row"><?= e($row['name']) ?></th>
                  <td><?= (int) $row['j'] ?></td><td><?= (int) $row['g'] ?></td><td><?= (int) $row['n'] ?></td><td><?= (int) $row['p'] ?></td>
                  <td><?= $row['diff'] > 0 ? '+' : '' ?><?= (int) $row['diff'] ?></td>
                  <td class="std__pts"><?= (int) $row['pts'] ?></td>
                  <td><span class="form"><?php foreach ($row['form'] as $f): ?><i class="fdot fdot--<?= e($f) ?>"><span class="sr-only"><?= e($outcome[$f][0]) ?></span></i><?php endforeach; ?></span></td>
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
      <?php $tablist('cal', 'Choisir une équipe', array_map(static fn ($t) => [$t['id'], $t['name']], $teams)); ?>
    </header>
    <?php foreach ($teams as $i => $t): ?>
      <div role="tabpanel" id="cal-panel-<?= e($t['id']) ?>" aria-labelledby="cal-tab-<?= e($t['id']) ?>"<?= $i ? ' hidden' : '' ?>>
        <div class="cal-bar">
          <p><strong><?= e($t['name']) ?></strong>, <?= e($t['competition']) ?></p>
          <a class="btn btn--ghost btn--sm" href="/calendrier.ics?equipe=<?= e($t['id']) ?>"><svg class="ic ic--sm" aria-hidden="true"><use href="#i-calendar"/></svg>Ajouter à mon agenda</a>
        </div>
        <ol class="fixtures">
          <?php foreach ($calendars[$t['id']] as $m): [$sl, $st] = $siteLabel[$m['site']]; ?>
            <li class="fx">
              <div class="fx__date"><span><?= e($m['weekday']) ?></span><strong><?= e($m['day']) ?></strong><span><?= e($m['month']) ?></span></div>
              <div class="fx__body">
                <p class="fx__title"><?= e($m['title']) ?></p>
                <p class="fx__meta"><span><svg class="ic ic--sm" aria-hidden="true"><use href="#i-clock"/></svg><?= e($m['time']) ?></span><span><svg class="ic ic--sm" aria-hidden="true"><use href="#i-pin"/></svg><?= e($m['venue']) ?></span><span><?= e($m['competition']) ?></span></p>
              </div>
              <span class="badge badge--<?= e($st) ?>"><?= e($sl) ?></span>
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
          <div class="art art--<?= e($tones[$i % 4]) ?>" aria-hidden="true">
            <svg viewBox="0 0 640 420" preserveAspectRatio="xMidYMid slice"><use href="#pitch"/></svg><span><?= e($n['tag']) ?></span>
          </div>
          <div class="news-card__body">
            <p class="news-card__meta"><time datetime="<?= e($n['iso']) ?>"><?= e($n['dateLabel']) ?></time></p>
            <h3><?= e($n['title']) ?></h3>
            <p><?= e($n['excerpt']) ?></p>
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
          <div class="event__date"><strong><?= e($ev['day']) ?></strong><span><?= e($ev['month']) ?></span></div>
          <div>
            <p class="event__tag"><span class="badge badge--neutral"><?= e($ev['tag']) ?></span></p>
            <h3><?= e($ev['title']) ?></h3>
            <p class="event__meta"><?= e($ev['range']) ?>, <?= e($ev['time']) ?>, <?= e($ev['place']) ?></p>
            <p><?= e($ev['text']) ?></p>
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
      <p class="reg__lead">Les inscriptions pour la <?= e(mb_strtolower($season['label'])) ?> sont ouvertes jusqu'au <?= e($registration['deadline']) ?>.</p>

      <ol class="steps">
        <?php foreach ($registration['steps'] as $i => $s): ?>
          <li><span class="steps__n" aria-hidden="true"><?= $i + 1 ?></span><div><h3><?= e($s['title']) ?></h3><p><?= e($s['text']) ?></p></div></li>
        <?php endforeach; ?>
      </ol>

      <h3 class="reg__sub">Cotisation annuelle</h3>
      <dl class="prices">
        <?php foreach ($registration['prices'] as $p): ?>
          <div><dt><?= e($p['cat']) ?><small><?= e($p['ages']) ?></small></dt><dd><?= e(money($p['price'])) ?></dd></div>
        <?php endforeach; ?>
      </dl>
    </div>

    <div class="reg__form card-white">
      <h3>Pré-inscription en ligne</h3>
      <p class="muted">Le secrétariat vous répond sous 48 heures.</p>
      <?php if (!empty($flash)): ?>
        <p class="notice notice--<?= e($flash['type'] === 'error' ? 'error' : 'ok') ?>" role="status"><?= e($flash['message']) ?></p>
      <?php endif; ?>
      <form method="post" action="/inscription" novalidate>
        <?= csrf_field() ?>
        <div class="form-grid">
          <label class="field-block"><span>Prénom</span><input name="prenom" value="<?= e($old['prenom'] ?? '') ?>" autocomplete="given-name" required></label>
          <label class="field-block"><span>Nom</span><input name="nom" value="<?= e($old['nom'] ?? '') ?>" autocomplete="family-name" required></label>
        </div>
        <label class="field-block"><span>Adresse e-mail</span><input type="email" name="email" value="<?= e($old['email'] ?? '') ?>" autocomplete="email" required></label>
        <label class="field-block"><span>Catégorie</span>
          <select name="categorie" required>
            <option value="">Choisir…</option>
            <?php foreach ($registration['prices'] as $p): ?>
              <option value="<?= e($p['cat']) ?>"<?= ($old['categorie'] ?? '') === $p['cat'] ? ' selected' : '' ?>><?= e($p['cat']) ?>, <?= e($p['ages']) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label class="field-block"><span>Message (facultatif)</span><textarea name="message" rows="3" maxlength="500"><?= e($old['message'] ?? '') ?></textarea></label>
        <div class="hp" aria-hidden="true"><label>Ne pas remplir<input name="website" tabindex="-1" autocomplete="off"></label></div>
        <button class="btn btn--primary btn--block" type="submit">Envoyer ma demande</button>
      </form>
    </div>
  </div>
</section>

<!-- ============================ Boutique ============================ -->
<section class="section" id="boutique" aria-labelledby="t-boutique">
  <div class="wrap">
    <header class="section__head"><div><h2 id="t-boutique">Boutique du club</h2><p><?= e($shop['pickup']) ?>. Réservez par e-mail, réglez au retrait.</p></div></header>
    <ul class="products">
      <?php foreach ($shop['products'] as $p): ?>
        <li class="prod">
          <div class="prod__art prod__art--<?= e($p['tone']) ?>">
            <?php if ($p['badge']): ?><span class="prod__badge"><?= e($p['badge']) ?></span><?php endif; ?>
            <svg viewBox="0 0 120 120" aria-hidden="true"><use href="#<?= e($sprite[$p['kind']]) ?>"/></svg>
          </div>
          <div class="prod__body">
            <h3><?= e($p['name']) ?></h3>
            <p class="muted"><?= e($p['sizes']) ?></p>
            <p class="prod__foot"><strong><?= e(money($p['price'])) ?></strong>
              <a class="btn btn--ghost btn--sm" href="mailto:<?= e($club['shopEmail']) ?>?subject=<?= e(rawurlencode('Réservation : ' . $p['name'])) ?>"><svg class="ic ic--sm" aria-hidden="true"><use href="#i-mail"/></svg>Réserver</a></p>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
</section>

/* M2L · Tableau de bord
 * Le menu, le profil et l'en-tête sont rendus côté serveur (PHP, session du membre).
 * Ce script dessine hero, agenda, indicateurs, panneaux et notifications à partir du JSON
 * #dashboard-data, qui ne contient QUE les données du rôle du membre connecté.
 */
(() => {
  'use strict';

  const $  = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => [...r.querySelectorAll(s)];
  const root = document.documentElement;
  const dataEl = $('#dashboard-data');
  const DATA = dataEl ? JSON.parse(dataEl.textContent) : null;
  const TOKEN = $('meta[name="csrf-token"]')?.content || '';

  /* ---------- Utilitaires ---------- */
  const esc = v => String(v ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const icon = (name, cls = '') => `<svg class="ic ${cls}" aria-hidden="true" focusable="false"><use href="#i-${esc(name)}"/></svg>`;
  const initials = name => String(name).trim().split(/\s+/).map(w => w[0]).slice(0, 2).join('').toUpperCase();
  const badge = s => `<span class="badge badge--${esc(s.tone)}">${esc(s.label)}</span>`;
  const fmt = n => Number(n).toLocaleString('fr-FR');
  const KEY_BY_TONE = { warn: 'pending', danger: 'missing', ok: 'valid', neutral: 'other' };

  async function post(url, body) {
    const res = await fetch(url, {
      method: 'POST', credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-Token': TOKEN },
      body: JSON.stringify(body)
    });
    if (!res.ok) throw new Error(String(res.status));
    return res.json();
  }

  /* ---------- Actions (chaque bouton porte le chemin de l'action dans le payload) ---------- */
  const actionEl = (a, variant, path) => {
    const hero = variant === 'hero';
    const cls = hero
      ? `btn ${a.kind === 'primary' ? 'btn--light' : 'btn--line'}`
      : `btn btn--sm ${a.kind === 'primary' ? 'btn--primary' : 'btn--ghost'}`;
    if (a.href) return `<a class="${cls}" href="${esc(a.href)}">${esc(a.label)}</a>`;
    return `<button type="button" class="${cls}" data-action data-path="${esc(path)}" data-toast="${esc(a.toast)}" data-done="${esc(a.done || 'Fait')}"
      ${a.set ? `data-set-tone="${esc(a.set.tone)}" data-set-label="${esc(a.set.label)}"` : ''}>${esc(a.label)}</button>`;
  };
  const actions = (list, variant, path) => !list || !list.length ? '' :
    `<span class="acts" data-actions>${list.map((a, i) => actionEl(a, variant, `${path}.${i}`)).join('')}</span>`;
  const statusSlot = s => s ? `<span data-status-slot aria-live="polite">${badge(s)}</span>` : '';

  /* ---------- Hero ---------- */
  const PITCH = `<svg class="hero__pitch" viewBox="0 0 640 420" aria-hidden="true"><use href="#pitch"/></svg>`;

  const crest = t => t.us
    ? `<svg class="crest" viewBox="0 0 40 46" aria-hidden="true"><use href="#crest-m2l"/></svg>`
    : `<span class="crest crest--away" aria-hidden="true">${esc(t.short)}</span>`;

  const responsesBar = r => `
    <div class="resp">
      <div class="resp__bar" aria-hidden="true">
        <span class="ok" style="flex:${+r.yes}"></span><span class="ko" style="flex:${+r.no}"></span><span class="wait" style="flex:${+r.pending}"></span>
      </div>
      <p class="resp__legend">${+r.yes} confirmés, ${+r.no} absent${r.no > 1 ? 's' : ''}, ${+r.pending} sans réponse</p>
    </div>`;

  const team = t => `<div class="team">${crest(t)}<p class="team__name">${esc(t.name)}</p><p class="team__sub">${esc(t.sub)}</p></div>`;

  const ring = (r, cls = '') => {
    const c = 2 * Math.PI * 52, len = c * r.percent / 100;
    return `<div class="ring ${cls}" role="img" aria-label="${esc(r.value)} ${esc(r.label)}">
      <svg viewBox="0 0 120 120" aria-hidden="true"><circle class="ring__bg" cx="60" cy="60" r="52"/><circle class="ring__fg" cx="60" cy="60" r="52" style="--len:${len.toFixed(1)};--c:${c.toFixed(1)}"/></svg>
      <div class="ring__txt" aria-hidden="true"><strong>${esc(r.value)}</strong><span>${esc(r.label)}</span></div></div>`;
  };

  const heroMatch = h => `
    <section class="hero" data-row aria-labelledby="hero-title">
      ${PITCH}
      <div class="hero__top"><h2 class="hero__title" id="hero-title">${esc(h.title)}</h2><span class="hero__chip">${esc(h.competition)}</span></div>
      <div class="hero__mid">
        <div class="fixture">${team(h.home)}<span class="fixture__vs" aria-hidden="true">VS</span>${team(h.away)}</div>
        <div class="hero__when">
          <p class="hero__date"><span class="sr-only">Coup d'envoi : </span>${esc(h.label)}</p>
          <div class="countdown" data-countdown="${esc(h.kickoff)}" aria-hidden="true">
            ${['jours', 'heures', 'min', 'sec'].map(k => `<div class="cd"><strong data-cd="${k}">00</strong><span>${k}</span></div>`).join('')}
          </div>
        </div>
      </div>
      <div class="hero__foot">
        <div class="hero__info">
          <p class="hero__venue">${icon('pin', 'ic--sm')}${esc(h.venue)}</p>
          ${h.responses ? responsesBar(h.responses) : ''}
          ${statusSlot(h.status)}
        </div>
        ${actions(h.actions, 'hero', 'hero.actions')}
      </div>
    </section>`;

  const heroFocus = h => `
    <section class="hero" data-row aria-labelledby="hero-title">
      ${PITCH}
      <div class="hero__top"><h2 class="hero__title" id="hero-title">${esc(h.title)}</h2></div>
      <div class="focus">
        <div><p class="focus__value">${esc(h.value)}</p><p class="focus__caption">${esc(h.caption)}</p></div>
        ${ring(h.ring)}
      </div>
      <div class="hero__foot">${actions(h.actions, 'hero', 'hero.actions')}</div>
    </section>`;

  const renderAgenda = a => `
    <section class="card agenda" aria-labelledby="agenda-title">
      <div class="card__head"><h2 class="card__title" id="agenda-title">${esc(a.title)}</h2></div>
      <ul class="card__body">${a.items.map(e => `
        <li class="ev ${e.highlight ? 'ev--hl' : ''}">
          <div class="ev__date"><span>${esc(e.day)}</span><strong>${esc(e.n)}</strong></div>
          <div><p class="ev__title">${esc(e.title)}</p><p class="ev__meta">${esc(e.meta)}</p></div>
        </li>`).join('')}
      </ul>
    </section>`;

  /* ---------- Indicateurs ---------- */
  const spark = vals => {
    const w = 200, h = 46, pad = 4, min = Math.min(...vals), max = Math.max(...vals), span = (max - min) || 1;
    const pts = vals.map((v, i) => [i / (vals.length - 1) * w, h - pad - (v - min) / span * (h - 2 * pad)]);
    const d = pts.map((p, i) => `${i ? 'L' : 'M'}${p[0].toFixed(1)} ${p[1].toFixed(1)}`).join(' ');
    return `<svg class="spark" viewBox="0 0 ${w} ${h}" preserveAspectRatio="none" aria-hidden="true"><path class="spark__area" d="${d} L${w} ${h} L0 ${h} Z"/><path class="spark__line" d="${d}"/></svg>`;
  };

  const renderKpi = k => `
    <li class="card kpi kpi--${esc(k.tone || 'neutral')}">
      <div class="kpi__top">
        <span class="kpi__icon">${icon(k.icon)}</span>
        ${k.chip ? `<span class="chip chip--${esc(k.chip.tone)}">${k.chip.dir ? icon('arrow-' + k.chip.dir) : ''}${esc(k.chip.label)}</span>` : ''}
      </div>
      <h3 class="kpi__label">${esc(k.label)}</h3>
      <p class="kpi__value">${esc(k.value)}</p>
      <p class="kpi__hint">${esc(k.hint)}</p>
      ${k.spark ? spark(k.spark) : ''}
      ${k.meter ? `<div class="meter"><div class="bar" role="img" aria-label="${+k.meter.percent} %"><span style="--w:${+k.meter.percent}%"></span></div><p>${esc(k.meter.label)}</p></div>` : ''}
    </li>`;

  /* ---------- Panneaux ---------- */
  const shell = (p, inner) => `
    <section class="card panel ${p.full ? 'panel--full' : ''}" id="panel-${esc(p.id)}" aria-labelledby="t-${esc(p.id)}">
      <div class="card__head"><h2 class="card__title" id="t-${esc(p.id)}">${esc(p.title)}</h2></div>
      ${p.subtitle ? `<p class="card__sub">${esc(p.subtitle)}</p>` : ''}
      <div class="card__body">${inner}</div>
    </section>`;

  const toolbar = (filters, counts, placeholder) => `
    <div class="toolbar">
      ${filters ? `<div class="filters" role="group" aria-label="Filtrer par statut">${filters.map((f, i) =>
        `<button type="button" class="filter" data-filter="${esc(f.key)}" aria-pressed="${i === 0}">${esc(f.label)}<small data-count="${esc(f.key)}">${counts[f.key] || 0}</small></button>`).join('')}</div>` : '<span></span>'}
      ${placeholder ? `<label class="field">${icon('search', 'ic--sm')}<span class="sr-only">${esc(placeholder)}</span><input type="search" placeholder="${esc(placeholder)}" data-table-search></label>` : ''}
    </div>`;

  const countKeys = rows => {
    const c = { all: rows.length };
    rows.forEach(r => { if (r.status) { const k = KEY_BY_TONE[r.status.tone]; c[k] = (c[k] || 0) + 1; } });
    return c;
  };

  const P = {};

  P.convocations = (p, path) => shell(p, `
    <ul>${p.items.map((it, i) => `
      <li class="match" data-row>
        <div class="match__date"><strong>${esc(it.day)}</strong><span>${esc(it.month)}</span></div>
        <div>
          <p class="match__title"><span class="tag">${esc(it.tag)}</span>${esc(it.title)}</p>
          <p class="match__meta">${(it.meta || []).map((m, j) => `<span>${j < 2 ? icon(['clock', 'pin'][j], 'ic--sm') : ''}${esc(m)}</span>`).join('')}</p>
          ${it.responses ? responsesBar(it.responses) : ''}
        </div>
        <div class="match__side">${statusSlot(it.status)}${actions(it.actions, undefined, `${path}.items.${i}.actions`)}</div>
      </li>`).join('')}
    </ul>`);

  P.attendance = (p, path) => {
    const yes = p.yesLabel || 'Présent', no = p.noLabel || 'Absent', label = p.countLabel || 'présents sur';
    return shell(p, `
    <div data-att>
      <div class="att__summary">
        <p aria-live="polite"><strong data-att-count>0</strong> ${esc(label)} ${p.players.length}</p>
        <div class="bar" aria-hidden="true"><span data-att-bar style="--w:0%"></span></div>
      </div>
      <ul class="att__list">${p.players.map(pl => `
        <li class="att__row ${p.plain ? 'att__row--plain' : ''}">
          <span class="att__name">${esc(pl.name)}</span>
          <div class="seg" role="group" aria-label="${esc(pl.name)}">
            <button type="button" class="seg__btn seg__btn--present" data-att-set aria-pressed="${pl.state === 'present'}">${esc(yes)}</button>
            <button type="button" class="seg__btn seg__btn--absent" data-att-set aria-pressed="${pl.state !== 'present'}">${esc(no)}</button>
          </div>
        </li>`).join('')}
      </ul>
      <div class="att__foot"><button type="button" class="btn btn--primary btn--sm" data-att-save="${esc(path)}" data-toast="${esc(p.saveToast || 'Enregistré')}">Enregistrer</button></div>
    </div>`);
  };

  P.dossiers = (p, path) => shell(p, `
    <div data-table>
      ${toolbar([{ key: 'all', label: 'Tous' }, { key: 'pending', label: 'À valider' }, { key: 'missing', label: 'Incomplets' }, { key: 'valid', label: 'Validés' }], countKeys(p.rows), 'Rechercher un licencié')}
      <div class="table-wrap">
        <table class="tbl">
          <thead><tr>
            <th scope="col">Licencié</th><th scope="col">Catégorie</th><th scope="col">Déposé le</th>
            <th scope="col">Pièces</th><th scope="col">Statut</th><th scope="col"><span class="sr-only">Action</span></th>
          </tr></thead>
          <tbody>${p.rows.map((r, i) => `
            <tr data-row data-key="${KEY_BY_TONE[r.status.tone]}" data-name="${esc(r.name.toLowerCase())}">
              <td data-label="Licencié"><div class="person"><span class="avatar" aria-hidden="true">${esc(initials(r.name))}</span><span>${esc(r.name)}<small>${esc(r.email)}</small></span></div></td>
              <td data-label="Catégorie">${esc(r.category)}</td>
              <td data-label="Déposé le">${esc(r.date)}</td>
              <td data-label="Pièces" class="num">${esc(r.pieces)}</td>
              <td data-label="Statut">${statusSlot(r.status)}</td>
              <td data-label="Action">${actions(r.actions, undefined, `${path}.rows.${i}.actions`)}</td>
            </tr>`).join('')}
          </tbody>
        </table>
        <p class="empty" data-empty hidden>Aucun dossier ne correspond à votre recherche.</p>
      </div>
    </div>`);

  /* Tableau générique : colonnes libres, statut et actions facultatifs */
  P.table = (p, path) => {
    const hasStatus = p.rows.some(r => r.status);
    const filters = p.filters ? [{ key: 'all', label: 'Tous' }, ...p.filters] : null;
    const search = p.rows.length >= 6 ? 'Rechercher' : null;
    const hasActions = p.rows.some(r => r.actions && r.actions.length);
    return shell(p, `
    <div data-table>
      ${filters || search ? toolbar(filters, countKeys(p.rows), search) : ''}
      <div class="table-wrap">
        <table class="tbl">
          <thead><tr>
            ${p.columns.map(c => `<th scope="col">${esc(c)}</th>`).join('')}
            ${hasStatus ? `<th scope="col">${esc(p.statusLabel || 'Statut')}</th>` : ''}
            ${hasActions ? '<th scope="col"><span class="sr-only">Action</span></th>' : ''}
          </tr></thead>
          <tbody>${p.rows.map((r, i) => `
            <tr data-row data-key="${r.status ? KEY_BY_TONE[r.status.tone] : 'other'}" data-name="${esc(r.cells.join(' ').toLowerCase())}">
              ${r.cells.map((c, j) => `<td data-label="${esc(p.columns[j])}"${j === 0 ? ' class="tbl__first"' : ''}>${esc(c)}</td>`).join('')}
              ${hasStatus ? `<td data-label="${esc(p.statusLabel || 'Statut')}">${statusSlot(r.status)}</td>` : ''}
              ${hasActions ? `<td data-label="Action">${actions(r.actions, undefined, `${path}.rows.${i}.actions`)}</td>` : ''}
            </tr>`).join('')}
          </tbody>
        </table>
        <p class="empty" data-empty hidden>Aucun résultat pour cette recherche.</p>
      </div>
    </div>`);
  };

  P.import = p => shell(p, `
    <form class="import" method="post" action="${esc(p.formAction)}" enctype="multipart/form-data" data-import>
      <input type="hidden" name="_csrf" value="${esc(TOKEN)}">
      <label class="dropzone" data-dropzone>
        <input class="sr-only" type="file" name="fichier_csv" accept=".csv,text/csv" data-file>
        ${icon('upload', 'ic--lg')}
        <span class="dropzone__main">Déposez votre fichier CSV ici</span>
        <span class="dropzone__sub">ou <span class="link">parcourez vos fichiers</span></span>
      </label>
      <div class="import__info">
        <h3 class="import__h">Colonnes attendues</h3>
        <p class="import__cols">${esc(p.columns)}</p>
        <p class="muted">${esc(p.last)}</p>
        <p class="import__file" data-file-row hidden>${icon('check')}<span><strong data-file-name></strong> <span data-file-meta></span></span></p>
        <div class="import__actions">
          <a class="btn btn--ghost btn--sm" href="${esc(p.templateHref)}">${icon('download')}Télécharger le modèle</a>
          <button class="btn btn--primary btn--sm" type="submit" disabled data-import-submit>Importer le fichier</button>
        </div>
      </div>
    </form>`);

  P.documents = (p, path) => shell(p, `
    <div class="docs-top">
      ${ring({ percent: Math.round(p.provided / p.items.length * 100), value: `${+p.provided}/${p.items.length}`, label: 'pièces' }, 'ring--card')}
      <p><strong>${+p.provided} pièces sur ${p.items.length} fournies</strong>${esc(p.summary)}</p>
    </div>
    <ul>${p.items.map((d, i) => `
      <li class="doc" data-row>
        <span class="doc__icon">${icon(d.icon)}</span>
        <div><p class="doc__name">${esc(d.name)}</p><p class="doc__meta">${esc(d.meta)}</p></div>
        <div class="doc__side">${statusSlot(d.status)}${actions(d.actions, undefined, `${path}.items.${i}.actions`)}</div>
      </li>`).join('')}
    </ul>`);

  P.categories = p => {
    const max = Math.max(...p.items.map(i => i.count));
    return shell(p, `
      <ul>${p.items.map(c => `
        <li class="cat">
          <span class="cat__name">${esc(c.name)}</span>
          <div class="bar" aria-hidden="true"><span style="--w:${(c.count / max * 100).toFixed(1)}%"></span></div>
          <span class="cat__n">${fmt(c.count)}${esc(p.unit || '')}</span>
        </li>`).join('')}
      </ul>`);
  };

  P.finance = p => {
    const total = p.stats.reduce((s, x) => s + x.value, 0), C = 2 * Math.PI * 46;
    const color = { ok: 'var(--ok-solid)', warn: 'var(--warn-solid)', danger: 'var(--red-600)' };
    let off = 0;
    const segs = p.stats.map(s => {
      const share = C * s.value / total, len = Math.max(share - 3, 0);
      const el = `<circle class="donut__seg" cx="60" cy="60" r="46" style="--len:${len.toFixed(1)};--c:${C.toFixed(1)};stroke:${color[s.tone]};stroke-dashoffset:${(-off).toFixed(1)}"/>`;
      off += share;
      return el;
    }).join('');
    return shell(p, `
      <p class="fin__big"><strong>${esc(p.raised)}</strong><span>${esc(p.target)}</span></p>
      <div class="bar" role="img" aria-label="${+p.percent} % encaissé"><span style="--w:${+p.percent}%"></span></div>
      <div class="fin__chart">
        <div class="donut" role="img" aria-label="Répartition de ${total} ${esc(p.donutLabel || 'éléments')}">
          <svg viewBox="0 0 120 120" aria-hidden="true"><circle class="donut__bg" cx="60" cy="60" r="46"/>${segs}</svg>
          <div class="donut__txt" aria-hidden="true"><strong>${total}</strong><span>${esc(p.donutLabel || '')}</span></div>
        </div>
        <dl class="legend">${p.stats.map(s => `<div><i style="background:${color[s.tone]}"></i><dt>${esc(s.label)}</dt><dd>${+s.value}</dd></div>`).join('')}</dl>
      </div>
      <a class="btn btn--ghost btn--sm" href="${esc(p.action.href)}">${esc(p.action.label)}</a>`);
  };

  P.trend = p => {
    const n = p.values.length, span = p.max - p.min;
    const X = i => i / (n - 1) * 100, Y = v => 100 - (v - p.min) / span * 100;
    const line = p.values.map((v, i) => `${i ? 'L' : 'M'}${X(i).toFixed(2)} ${Y(v).toFixed(2)}`).join(' ');
    const ticks = [0, 1, 2, 3, 4].map(k => p.min + span * k / 4);
    const gid = `g-${esc(p.id)}`;
    const u = p.unit || '';
    return shell(p, `
      <div class="chart">
        <div class="chart__y" aria-hidden="true">${ticks.map(t => `<span style="bottom:${((t - p.min) / span * 100)}%">${Math.round(t)}</span>`).join('')}</div>
        <div class="chart__plot">
          <svg viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true">
            <defs><linearGradient id="${gid}" x1="0" x2="0" y1="0" y2="1"><stop offset="0" style="stop-color:var(--chart);stop-opacity:.3"/><stop offset="1" style="stop-color:var(--chart);stop-opacity:0"/></linearGradient></defs>
            ${ticks.map(t => { const y = 100 - (t - p.min) / span * 100; return `<line class="chart__grid" x1="0" x2="100" y1="${y}" y2="${y}"/>`; }).join('')}
            <path d="${line} L100 100 L0 100 Z" fill="url(#${gid})"/>
            <path class="chart__line" d="${line}"/>
          </svg>
          ${p.values.map((v, i) => `<span class="dot ${i === n - 1 ? 'dot--last' : ''}" tabindex="0" role="img" aria-label="${esc(p.labels[i])} : ${v}${esc(u)}" style="left:${X(i)}%;top:${Y(v)}%"><span class="tip">${v}${esc(u)}</span></span>`).join('')}
        </div>
        <div class="chart__x" aria-hidden="true">${p.labels.map((l, i) => `<span style="left:${X(i)}%">${esc(l)}</span>`).join('')}</div>
      </div>`);
  };

  P.decisions = (p, path) => shell(p, `
    <ul>${p.items.map((d, i) => `
      <li class="decision" data-row>
        <div class="decision__txt"><p class="doc__name">${esc(d.title)}</p><p class="doc__meta">${esc(d.meta)}</p></div>
        <div class="decision__side">${statusSlot(d.status)}${actions(d.actions, undefined, `${path}.items.${i}.actions`)}</div>
      </li>`).join('')}
    </ul>`);
  P.tasks = P.decisions;

  /* ---------- Notifications ---------- */
  const toneIcon = { ok: 'check', warn: 'clock', danger: 'alert', neutral: 'info' };
  const renderAlerts = list => `
    <div class="card__head"><h2 class="card__title" id="alerts-title">Notifications</h2><span class="badge" id="unreadCount"></span></div>
    <div class="alerts__tools"><button type="button" class="link-btn" data-read-all>Tout marquer comme lu</button></div>
    <div class="card__body">
      ${list.length ? `<ul>${list.map(n => `
        <li class="alert alert--${esc(n.tone)} ${n.unread ? 'is-unread' : ''}" data-alert>
          <span class="alert__icon">${icon(toneIcon[n.tone] || 'info')}</span>
          <div>
            <p class="alert__title">${n.unread ? '<span class="sr-only">Non lue : </span>' : ''}${esc(n.title)}</p>
            <p class="alert__text">${esc(n.text)}</p>
            <p class="alert__foot"><span>${esc(n.time)}</span>${n.unread ? '<button type="button" class="link-btn" data-read>Marquer comme lu</button>' : ''}</p>
          </div>
        </li>`).join('')}</ul>`
        : `<p class="empty">Rien de nouveau. Les changements de convocation et de dossier apparaîtront ici.</p>`}
    </div>`;

  function updateUnread() {
    const n = $$('.alert.is-unread').length;
    const pill = $('#unreadCount');
    if (pill) {
      pill.textContent = n ? `${n} nouvelle${n > 1 ? 's' : ''}` : 'Tout est lu';
      pill.className = 'badge ' + (n ? 'badge--danger' : 'badge--ok');
    }
    const all = $('[data-read-all]');
    if (all) all.disabled = !n;
    const bell = $('#bellCount');
    if (bell) { bell.hidden = !n; bell.textContent = n; }
  }

  function updateAtt(scope) {
    const rows = $$('.att__row', scope).length;
    const n = $$('.seg__btn--present[aria-pressed="true"]', scope).length;
    $('[data-att-count]', scope).textContent = n;
    $('[data-att-bar]', scope).style.setProperty('--w', (rows ? n / rows * 100 : 0) + '%');
  }

  function updateTable(scope) {
    const activeBtn = $('[data-filter][aria-pressed="true"]', scope);
    const active = activeBtn ? activeBtn.dataset.filter : 'all';
    const q = ($('[data-table-search]', scope)?.value || '').trim().toLowerCase();
    const rows = $$('tbody tr', scope);
    let shown = 0;
    rows.forEach(tr => {
      const ok = (active === 'all' || tr.dataset.key === active) && (!q || tr.dataset.name.includes(q));
      tr.hidden = !ok;
      if (ok) shown++;
    });
    const counts = { all: rows.length };
    rows.forEach(tr => { counts[tr.dataset.key] = (counts[tr.dataset.key] || 0) + 1; });
    $$('[data-count]', scope).forEach(el => { el.textContent = counts[el.dataset.count] || 0; });
    const empty = $('[data-empty]', scope);
    if (empty) empty.hidden = shown > 0;
  }

  /* ---------- Messages ---------- */
  function toast(message, tone = 'ok') {
    const el = document.createElement('div');
    el.className = `toast toast--${tone}`;
    el.innerHTML = icon(tone === 'danger' ? 'alert' : tone === 'neutral' ? 'info' : 'check') + `<span>${esc(message)}</span>`;
    $('#toasts').append(el);
    const kill = () => el.remove();
    setTimeout(() => { el.classList.add('is-leaving'); el.addEventListener('animationend', kill, { once: true }); setTimeout(kill, 400); }, 4200);
  }

  /* ---------- Compte à rebours (heure du serveur) ---------- */
  const NOW0 = DATA ? Date.parse(DATA.meta.now) : Date.now(), T0 = performance.now();
  const pad = n => String(n).padStart(2, '0');
  function tick() {
    const now = NOW0 + (performance.now() - T0);
    $$('[data-countdown]').forEach(el => {
      const s = Math.max(Math.floor((Date.parse(el.dataset.countdown) - now) / 1000), 0);
      const v = { jours: Math.floor(s / 86400), heures: Math.floor(s % 86400 / 3600), min: Math.floor(s % 3600 / 60), sec: s % 60 };
      Object.keys(v).forEach(k => { const t = $(`[data-cd="${k}"]`, el); if (t) t.textContent = pad(v[k]); });
    });
  }
  setInterval(tick, 1000);

  /* ---------- Palette de commandes (pages du menu du rôle + actions + sections) ---------- */
  const dlg = $('#palette'), pin = $('#paletteInput'), plist = $('#paletteList');
  let pItems = [], pView = [], pIdx = 0;

  function buildPalette() {
    const primary = $('[data-primary-action]');
    pItems = [
      ...(primary ? [{ label: primary.textContent.trim(), icon: 'plus', href: primary.getAttribute('href'), kind: 'Action' }] : []),
      ...$$('#nav a').map(a => ({ label: $('span', a).textContent.trim(), icon: a.dataset.icon || 'dashboard', href: a.getAttribute('href'), kind: 'Page' })),
      ...(DATA ? DATA.panels.map(p => ({ label: p.title, icon: 'dashboard', href: '#panel-' + p.id, kind: 'Section' })) : [])
    ];
  }
  function paintPalette() {
    const q = pin.value.trim().toLowerCase();
    pView = pItems.filter(i => i.label.toLowerCase().includes(q));
    pIdx = Math.min(pIdx, Math.max(pView.length - 1, 0));
    plist.innerHTML = pView.length ? pView.map((it, i) => `
      <li class="pal__item" role="option" id="pal-${i}" data-pal="${i}" aria-selected="${i === pIdx}">${icon(it.icon)}<span>${esc(it.label)}</span><small>${esc(it.kind)}</small></li>`).join('')
      : `<li class="empty" role="presentation">Aucun résultat pour « ${esc(pin.value)} »</li>`;
    pin.setAttribute('aria-activedescendant', pView.length ? `pal-${pIdx}` : '');
    $('.pal__item[aria-selected="true"]', plist)?.scrollIntoView({ block: 'nearest' });
  }
  function openPalette() { pin.value = ''; pIdx = 0; paintPalette(); if (!dlg.open) dlg.showModal(); pin.focus(); }
  function goTo(it) {
    if (!it) return;
    dlg.close();
    if (it.href.startsWith('#')) document.querySelector(it.href)?.scrollIntoView({ block: 'start' });
    else location.href = it.href;
  }

  /* ---------- Thème ---------- */
  function paintTheme() {
    const dark = root.dataset.theme === 'dark';
    const b = $('[data-theme-toggle]');
    b.innerHTML = icon(dark ? 'sun' : 'moon');
    b.setAttribute('aria-label', dark ? 'Passer au thème clair' : 'Passer au thème sombre');
  }

  /* ---------- Rendu principal ---------- */
  function render() {
    if (!DATA) return;
    const h = DATA.hero;
    $('#heroRow').innerHTML = (h.type === 'match' ? heroMatch(h) : heroFocus(h)) + renderAgenda(DATA.agenda);
    $('#kpis').innerHTML = DATA.kpis.map(renderKpi).join('');
    // Une seule grille : les notifications s'insèrent après la première série de panneaux
    // demi-largeur (3 colonnes sur grand écran), ou prennent toute la largeur s'il n'y en a pas.
    const panels = DATA.panels.map((p, i) => ({ full: !!p.full, html: P[p.type] ? P[p.type](p, `panels.${i}`) : '' }));
    const start = panels.findIndex(x => !x.full);
    let at = panels.length;
    if (start >= 0) { at = start; while (at < panels.length && !panels[at].full) at++; }
    const alerts = `<aside class="card alerts${start < 0 ? ' alerts--wide' : ''}" id="alerts" tabindex="-1" aria-labelledby="alerts-title">${renderAlerts(DATA.notifications)}</aside>`;
    $('#workspace').innerHTML = [...panels.slice(0, at).map(x => x.html), alerts, ...panels.slice(at).map(x => x.html)].join('');

    $$('[data-att]').forEach(updateAtt);
    $$('[data-table]').forEach(updateTable);
    updateUnread();
    tick();

    // Séquence d'entrée : barres, courbes, anneaux et donuts se dessinent
    requestAnimationFrame(() => requestAnimationFrame(() => document.body.classList.add('is-ready')));
  }

  /* ---------- Menu mobile ---------- */
  const sidebar = $('#sidebar'), scrim = $('#scrim'), burger = $('[data-nav-open]');
  function setNav(open) {
    const wasOpen = sidebar.classList.contains('is-open');
    sidebar.classList.toggle('is-open', open);
    scrim.hidden = !open;
    document.body.classList.toggle('nav-open', open);
    burger.setAttribute('aria-expanded', String(open));
    if (open) $('#nav a')?.focus(); else if (wasOpen) burger.focus();
  }

  /* ---------- Import CSV : aperçu côté navigateur, envoi réel au serveur ---------- */
  async function onFile(form, file) {
    const row = $('[data-file-row]', form), submit = $('[data-import-submit]', form), input = $('[data-file]', form);
    if (!file) return;
    if (!/\.csv$/i.test(file.name)) {
      input.value = ''; row.hidden = true; submit.disabled = true;
      toast('Format non pris en charge. Choisissez un fichier .csv', 'danger');
      return;
    }
    const text = await file.text();
    const lines = Math.max(text.split(/\r?\n/).filter(l => l.trim()).length - 1, 0);
    $('[data-file-name]', form).textContent = file.name;
    $('[data-file-meta]', form).textContent = `${fmt(lines)} licenciés détectés, ${(file.size / 1024).toLocaleString('fr-FR', { maximumFractionDigits: 1 })} Ko`;
    row.hidden = false;
    submit.disabled = lines < 1;
  }

  /* ---------- Exécution d'une action (validation côté serveur) ---------- */
  async function runAction(btn) {
    const row = btn.closest('[data-row]');
    const label = btn.textContent;
    btn.disabled = true;
    btn.setAttribute('aria-busy', 'true');
    try {
      await post('/api/actions', { path: btn.dataset.path });
    } catch (_) {
      btn.disabled = false;
      btn.removeAttribute('aria-busy');
      btn.textContent = label;
      return toast("L'action n'a pas pu être enregistrée. Réessayez.", 'danger');
    }
    if (btn.dataset.setLabel && row) {
      const slot = $('[data-status-slot]', row);
      if (slot) slot.innerHTML = badge({ tone: btn.dataset.setTone, label: btn.dataset.setLabel });
      if (row.dataset.key) row.dataset.key = KEY_BY_TONE[btn.dataset.setTone] || 'other';
      btn.closest('[data-actions]')?.remove();
      const table = row.closest('[data-table]');
      if (table) updateTable(table);
    } else {
      btn.removeAttribute('aria-busy');
      btn.textContent = btn.dataset.done;
    }
    toast(btn.dataset.toast);
  }

  async function saveAttendance(btn) {
    const scope = btn.closest('[data-att]');
    const states = $$('.att__row', scope).map(r => $('.seg__btn--present', r).getAttribute('aria-pressed') === 'true' ? 'present' : 'absent');
    btn.disabled = true;
    try {
      await post('/api/actions', { path: btn.dataset.attSave, data: { states } });
      toast(btn.dataset.toast);
    } catch (_) {
      toast("L'enregistrement a échoué. Réessayez.", 'danger');
    }
    btn.disabled = false;
  }

  /* ---------- Événements (délégation) ---------- */
  document.addEventListener('click', e => {
    const t = e.target;

    if (t.closest('[data-nav-open]')) return setNav(true);
    if (t.closest('[data-nav-close]') || t === scrim) return setNav(false);
    if (t.closest('[data-palette-open]')) return openPalette();
    if (t === dlg) return dlg.close();

    const pal = t.closest('[data-pal]');
    if (pal) return goTo(pView[+pal.dataset.pal]);

    if (t.closest('[data-theme-toggle]')) {
      const next = root.dataset.theme === 'dark' ? 'light' : 'dark';
      root.dataset.theme = next;
      try { localStorage.setItem('m2l-theme', next); } catch (_) {}
      return paintTheme();
    }
    if (t.closest('[data-goto-alerts]')) {
      const box = $('#alerts');
      if (!box) { location.href = '/tableau-de-bord#alerts'; return; }
      box.scrollIntoView({ block: 'start' });
      return box.focus();
    }

    const att = t.closest('[data-att-set]');
    if (att) {
      $$('[data-att-set]', att.parentElement).forEach(b => b.setAttribute('aria-pressed', String(b === att)));
      return updateAtt(att.closest('[data-att]'));
    }
    const save = t.closest('[data-att-save]');
    if (save) return saveAttendance(save);

    const flt = t.closest('[data-filter]');
    if (flt) {
      const scope = flt.closest('[data-table]');
      $$('[data-filter]', scope).forEach(b => b.setAttribute('aria-pressed', String(b === flt)));
      return updateTable(scope);
    }

    const act = t.closest('[data-action]');
    if (act) return runAction(act);

    const read = t.closest('[data-read]');
    if (read) {
      read.closest('[data-alert]').classList.remove('is-unread');
      read.remove();
      return updateUnread();
    }
    if (t.closest('[data-read-all]')) {
      $$('.alert.is-unread').forEach(a => { a.classList.remove('is-unread'); $('[data-read]', a)?.remove(); });
      return updateUnread();
    }
  });

  document.addEventListener('input', e => {
    if (e.target.matches('[data-table-search]')) updateTable(e.target.closest('[data-table]'));
    if (e.target === pin) { pIdx = 0; paintPalette(); }
  });

  document.addEventListener('keydown', e => {
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') { e.preventDefault(); return dlg.open ? dlg.close() : openPalette(); }
    if (e.key === 'Escape' && sidebar.classList.contains('is-open')) setNav(false);
    if (dlg.open && e.target === pin) {
      if (e.key === 'ArrowDown') { e.preventDefault(); pIdx = Math.min(pIdx + 1, pView.length - 1); paintPalette(); }
      if (e.key === 'ArrowUp')   { e.preventDefault(); pIdx = Math.max(pIdx - 1, 0); paintPalette(); }
      if (e.key === 'Enter')     { e.preventDefault(); goTo(pView[pIdx]); }
    }
  });

  document.addEventListener('change', e => {
    if (e.target.matches('[data-file]')) onFile(e.target.closest('form'), e.target.files[0]);
  });

  ['dragenter', 'dragover'].forEach(ev => document.addEventListener(ev, e => {
    const z = e.target.closest?.('[data-dropzone]');
    if (z) { e.preventDefault(); z.classList.add('is-over'); }
  }));
  ['dragleave', 'drop'].forEach(ev => document.addEventListener(ev, e => {
    const z = e.target.closest?.('[data-dropzone]');
    if (!z) return;
    e.preventDefault();
    z.classList.remove('is-over');
    if (ev === 'drop' && e.dataTransfer.files.length) {
      $('[data-file]', z).files = e.dataTransfer.files;
      onFile(z.closest('form'), e.dataTransfer.files[0]);
    }
  }));

  /* ---------- Démarrage ---------- */
  $('#kbdHint').textContent = /Mac|iPhone|iPad/.test(navigator.platform) ? '⌘ K' : 'Ctrl K';
  paintTheme();
  render();
  buildPalette();
  const flash = $('[data-flash]');
  if (flash) toast(flash.textContent, flash.dataset.type === 'error' ? 'danger' : 'ok');
})();

/* M2L · Site public : onglets accessibles, menu mobile, thème, compte à rebours, repère de section */
(() => {
  'use strict';
  const $  = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => [...r.querySelectorAll(s)];
  const root = document.documentElement;
  const ic = n => `<svg class="ic" aria-hidden="true"><use href="#i-${n}"/></svg>`;

  /* Onglets (clic, flèches, Début, Fin) */
  $$('[data-tabs]').forEach(list => {
    const tabs = $$('[role="tab"]', list);
    const select = (tab, focus = false) => {
      tabs.forEach(t => {
        const on = t === tab;
        t.setAttribute('aria-selected', String(on));
        t.tabIndex = on ? 0 : -1;
        document.getElementById(t.getAttribute('aria-controls')).hidden = !on;
      });
      if (focus) tab.focus();
    };
    tabs.forEach((t, i) => {
      t.addEventListener('click', () => select(t));
      t.addEventListener('keydown', e => {
        const k = { ArrowRight: i + 1, ArrowLeft: i - 1, Home: 0, End: tabs.length - 1 }[e.key];
        if (k === undefined) return;
        e.preventDefault();
        select(tabs[(k + tabs.length) % tabs.length], true);
      });
    });
  });

  /* Menu mobile */
  const burger = $('[data-site-burger]'), nav = $('#siteNav');
  const setNav = open => { nav.classList.toggle('is-open', open); burger.setAttribute('aria-expanded', String(open)); };
  burger.addEventListener('click', () => setNav(!nav.classList.contains('is-open')));
  nav.addEventListener('click', e => { if (e.target.closest('a')) setNav(false); });
  document.addEventListener('keydown', e => { if (e.key === 'Escape') setNav(false); });

  /* Thème */
  const toggle = $('[data-theme-toggle]');
  const paint = () => {
    const dark = root.dataset.theme === 'dark';
    toggle.innerHTML = ic(dark ? 'sun' : 'moon');
    toggle.setAttribute('aria-label', dark ? 'Passer au thème clair' : 'Passer au thème sombre');
  };
  toggle.addEventListener('click', () => {
    const next = root.dataset.theme === 'dark' ? 'light' : 'dark';
    root.dataset.theme = next;
    try { localStorage.setItem('m2l-theme', next); } catch (_) {}
    paint();
  });
  paint();

  /* Compte à rebours : heure du serveur, pas celle du visiteur */
  const cd = $('[data-countdown]');
  if (cd) {
    const now0 = Date.parse(cd.dataset.now), kick = Date.parse(cd.dataset.kickoff), t0 = performance.now();
    const pad = n => String(n).padStart(2, '0');
    const tick = () => {
      const s = Math.max(Math.floor((kick - (now0 + performance.now() - t0)) / 1000), 0);
      const v = { jours: Math.floor(s / 86400), heures: Math.floor(s % 86400 / 3600), min: Math.floor(s % 3600 / 60), sec: s % 60 };
      Object.keys(v).forEach(k => { $(`[data-cd="${k}"]`, cd).textContent = pad(v[k]); });
    };
    tick();
    setInterval(tick, 1000);
  }

  /* Repère de la section visible dans le menu */
  const links = $$('.site-nav a');
  const map = new Map(links.map(a => [a.getAttribute('href').slice(1), a]));
  const io = new IntersectionObserver(entries => {
    entries.forEach(en => {
      if (en.isIntersecting) {
        links.forEach(l => l.classList.remove('is-active'));
        map.get(en.target.id)?.classList.add('is-active');
      }
    });
  }, { rootMargin: '-45% 0px -50% 0px' });
  map.forEach((_, id) => { const el = document.getElementById(id); if (el) io.observe(el); });
})();

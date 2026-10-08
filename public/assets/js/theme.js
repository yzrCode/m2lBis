/* Thème appliqué avant le premier rendu (fichier externe : compatible CSP sans script inline) */
(function () {
  try {
    var t = localStorage.getItem('m2l-theme');
    if (!t) t = matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    document.documentElement.dataset.theme = t;
  } catch (e) { document.documentElement.dataset.theme = 'light'; }
})();

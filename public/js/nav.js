// Phone menu: the hamburger opens the side menu as a drawer; the dark area, a link or Escape closes it.
(function () {
  var btn = document.getElementById('nav-toggle'), back = document.getElementById('nav-backdrop'), nav = document.getElementById('side-nav');
  if (!btn || !nav) { return; }
  function set(open) {
    document.body.classList.toggle('nav-open', open);
    btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    if (open) { var first = nav.querySelector('a'); if (first) { first.focus({ preventScroll: true }); } } else { btn.focus({ preventScroll: true }); }
  }
  btn.addEventListener('click', function () { set(!document.body.classList.contains('nav-open')); });
  back.addEventListener('click', function () { set(false); });
  nav.addEventListener('click', function (e) { if (e.target.closest('a')) { document.body.classList.remove('nav-open'); btn.setAttribute('aria-expanded', 'false'); } });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && document.body.classList.contains('nav-open')) { set(false); } });
  window.addEventListener('resize', function () { if (window.innerWidth > 900 && document.body.classList.contains('nav-open')) { set(false); } });
})();

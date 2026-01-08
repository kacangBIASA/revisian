// public/assets/js/app.js
(function () {
  const root = document.documentElement;
  const buttons = document.querySelectorAll('[data-set-theme]');
  const FADE_MS = 260;

  // disable transition saat awal load (anti flash)
  root.classList.add('no-transitions');

  function ensureFadeEl() {
    let el = document.getElementById('theme-fade');
    if (!el) {
      el = document.createElement('div');
      el.id = 'theme-fade';
      el.setAttribute('aria-hidden', 'true');
      document.body.appendChild(el);
    }
    return el;
  }

  function applyTheme(theme, withFade = true) {
    if (withFade) {
      ensureFadeEl();
      root.classList.add('theme-fading');
    }

    root.setAttribute('data-theme', theme);
    localStorage.setItem('qn_theme', theme);

    buttons.forEach(btn => {
      btn.classList.toggle('active', btn.dataset.setTheme === theme);
    });

    if (withFade) {
      window.clearTimeout(window.__qnFadeTimer);
      window.__qnFadeTimer = window.setTimeout(() => {
        root.classList.remove('theme-fading');
      }, FADE_MS);
    }
  }

  // init theme (tanpa fade)
  const saved = localStorage.getItem('qn_theme');
  if (saved === 'dark' || saved === 'light') {
    applyTheme(saved, false);
  } else {
    const preferDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
    applyTheme(preferDark ? 'dark' : 'light', false);
  }

  // aktifkan transition setelah init
  requestAnimationFrame(() => root.classList.remove('no-transitions'));

  // click handler (pakai fade)
  buttons.forEach(btn => {
    btn.addEventListener('click', () => applyTheme(btn.dataset.setTheme, true));
  });
})();

    /* ---- simple hash router ---- */

document.getElementById('optDark').addEventListener('change', e => {
    document.documentElement.style.setProperty('--bg', e.target.checked ? '#1d150d' : '#f3ede6');
    document.body.style.color = e.target.checked ? '#f3ede6' : '';
});
// logout modal
// const logoutModal = new bootstrap.Modal(document.getElementById('logoutModal'));
// document.addEventListener('click', e => {
//   if(!e.target.closest('[data-logout]')) return;
//   e.preventDefault();
//   const oc = bootstrap.Offcanvas.getInstance('#mobileNav'); if(oc) oc.hide();
//   logoutModal.show();
// });
// document.getElementById('confirmLogout').addEventListener('click', () => {
//   logoutModal.hide();
//   go('login');   // put your real logout / session-clear call here
// });
//   const views = {login:'view-login', signup:'view-signup', app:'view-app'};
//   function go(name){ location.hash = name; }
//   function route(){
//     const h = (location.hash || '#login').slice(1);
//     const isApp = h === 'dashboard' || h === 'customize';
//     document.querySelectorAll('.view').forEach(v => v.classList.remove('active'));
//     document.getElementById(isApp ? views.app : (views[h] || views.login)).classList.add('active');
//     if(isApp){
//       document.getElementById('page-dashboard').classList.toggle('d-none', h !== 'dashboard');
//       document.getElementById('page-customize').classList.toggle('d-none', h !== 'customize');
//       document.querySelectorAll('[data-nav]').forEach(a => a.classList.toggle('active', a.dataset.nav === h));
//       const oc = bootstrap.Offcanvas.getInstance('#mobileNav'); if(oc) oc.hide();
//     }
//     window.scrollTo(0,0);
//   }
//   window.addEventListener('hashchange', route);

  /* clone sidebar into mobile offcanvas */
  document.getElementById('mobileNavBody').innerHTML =
    '<div class="sidebar-nav">' + document.getElementById('sideNav').innerHTML + '</div>';

  /* password show/hide */
  document.querySelectorAll('.pw-toggle').forEach(b => b.addEventListener('click', () => {
    const i = b.parentElement.querySelector('.pw'); const ic = b.querySelector('i');
    const show = i.type === 'password'; i.type = show ? 'text' : 'password';
    ic.className = 'bi ' + (show ? 'bi-eye-slash' : 'bi-eye');
  }));

  /* password strength */
  const pw = document.querySelector('#view-signup .pw');
  pw.addEventListener('input', () => {
    const v = pw.value; let s = 0;
    if(v.length >= 8) s++; if(/[A-Z]/.test(v)) s++; if(/\d/.test(v)) s++; if(/[^A-Za-z0-9]/.test(v)) s++;
    const bar = document.getElementById('pwbar');
    bar.style.width = (s*25) + '%';
    document.getElementById('pwtxt').textContent = ['Too short','Weak','Fair','Good','Strong'][s];
  });

  /* customize interactions */
  const root = document.documentElement;
  document.querySelectorAll('#swatches .swatch').forEach(s => s.addEventListener('click', () => {
    document.querySelectorAll('#swatches .swatch').forEach(x => x.classList.remove('on'));
    s.classList.add('on'); root.style.setProperty('--accent', s.dataset.c);
  }));
  document.querySelectorAll('#layouts .layout-opt').forEach(o => o.addEventListener('click', () => {
    document.querySelectorAll('#layouts .layout-opt').forEach(x => x.classList.remove('on')); o.classList.add('on');
  }));
  document.getElementById('optCompact').addEventListener('change', e => document.body.classList.toggle('compact', e.target.checked));

  const toast = bootstrap.Toast.getOrCreateInstance(document.getElementById('toast'));
  document.getElementById('savePrefs').addEventListener('click', () => toast.show());
  document.getElementById('resetPrefs').addEventListener('click', () => {
    document.querySelectorAll('#page-customize input[type=checkbox]').forEach((c,i)=>{ c.checked = c.defaultChecked; c.dispatchEvent(new Event('change')); });
    document.querySelector('#swatches .swatch').click();
  });

  /* drag & drop reorder for widgets */
  const list = document.getElementById('widgets'); let dragEl = null;
  list.addEventListener('dragstart', e => { dragEl = e.target.closest('li'); dragEl.style.opacity = .4; });
  list.addEventListener('dragend', () => { dragEl.style.opacity = 1; });
  list.addEventListener('dragover', e => {
    e.preventDefault(); const t = e.target.closest('li');
    if(t && t !== dragEl){ const r = t.getBoundingClientRect(); list.insertBefore(dragEl, (e.clientY - r.top) > r.height/2 ? t.nextSibling : t); }
  });



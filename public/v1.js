/*!
 * Weyfarin Embed Loader v1
 *
 * Usage:
 *   <div data-weyfarin="pk_live_xxxxxxxx"></div>
 *   <script src="https://weyfarin.com/embed/v1.js" async></script>
 *
 * The loader renders nothing itself. It finds containers, fetches each
 * widget's config, and lazy-loads only the renderer that widget's type needs
 * from r/{renderer}.js next to this file. A blogger showing three cards never
 * downloads the booking engine.
 *
 * Renderers register with Weyfarin.define(name, fn) and receive
 * (shadowRoot, data, api). Everything shared lives on `api`.
 */
(function () {
  'use strict';

  if (window.__weyfarinEmbed) return;
  window.__weyfarinEmbed = true;

  // Bump on any renderer change. Renderer URLs carry ?v=, so they can be
  // cached for a year while this loader stays on a short TTL.
  var VERSION = '1.4.0';

  var ATTR = 'data-weyfarin';
  var INIT = 'data-weyfarin-ready';

  // Widget type → renderer file. Must match Widget::TYPES on the server.
  var RENDERERS = {
    listing:  'listing',
    category: 'listing',
    featured: 'listing',
    location: 'listing',
    search:   'search',
    detail:   'detail'
  };

  // ------------------------------------------------------------ script origin

  // Renderers resolve relative to wherever this file is served from, so the
  // r/ folder only has to sit next to v1.js — /v1.js or /embed/v1.js both work.
  var SELF = document.currentScript;
  var ORIGIN = 'https://weyfarin.com';
  var BASE = 'https://weyfarin.com/embed/';

  try {
    var parsed = new URL(SELF && SELF.src ? SELF.src : BASE + 'v1.js');
    ORIGIN = parsed.origin;
    BASE = parsed.origin + parsed.pathname.replace(/[^/]*$/, '');
  } catch (e) { /* keep defaults */ }

  // ----------------------------------------------------------------- helpers

  function el(tag, props, children) {
    var node = document.createElement(tag);
    for (var key in props || {}) {
      if (key === 'class') node.className = props[key];
      else if (key === 'text') node.textContent = props[key];
      else if (props[key] != null) node.setAttribute(key, props[key]);
    }
    (children || []).forEach(function (child) {
      if (child) node.appendChild(child);
    });
    return node;
  }

  function money(amount, currency) {
    try {
      return new Intl.NumberFormat(document.documentElement.lang || 'en', {
        style: 'currency',
        currency: currency || 'INR',
        maximumFractionDigits: 0
      }).format(amount);
    } catch (e) {
      return (currency || '') + ' ' + Math.round(amount);
    }
  }

  function warn(message) {
    if (window.console && console.warn) console.warn('[weyfarin] ' + message);
  }

  // Writes. Body goes as text/plain JSON so the cross-origin request stays
  // "simple" — no CORS preflight, same rule as the beacon. Non-2xx throws an
  // Error carrying the server's `error` code and message for the UI.
  function postJson(url, body) {
    return fetch(url, {
      method: 'POST',
      credentials: 'omit',
      mode: 'cors',
      headers: { 'Content-Type': 'text/plain;charset=UTF-8' },
      body: JSON.stringify(body || {})
    }).then(function (response) {
      return response.json().catch(function () { return {}; }).then(function (json) {
        if (!response.ok) {
          var error = new Error(json.message || ('HTTP ' + response.status + ' for ' + url));
          error.status = response.status;
          error.code = json.error || null;
          error.body = json;
          throw error;
        }
        return json;
      });
    });
  }

  // Every renderer request goes through here: no cookies, CORS, and a thrown
  // error on non-2xx so callers only handle one failure path.
  function getJson(url, signal) {
    return fetch(url, { credentials: 'omit', mode: 'cors', signal: signal })
      .then(function (response) {
        if (!response.ok) throw new Error('HTTP ' + response.status + ' for ' + url);
        return response.json();
      });
  }

  // ---------------------------------------------------------------- tracking

  var queue = [];
  var flushTimer = null;
  var trackUrl = null;

  function track(url, type, id) {
    trackUrl = url || trackUrl;
    queue.push({ type: type, id: id, path: location.pathname });
    if (flushTimer) return;
    flushTimer = setTimeout(flush, 1200);
  }

  function flush() {
    clearTimeout(flushTimer);
    flushTimer = null;
    if (!queue.length || !trackUrl) return;
    var body = JSON.stringify({ events: queue.splice(0, queue.length) });
    try {
      // text/plain keeps this a simple request: no preflight, which
      // sendBeacon cannot complete on pagehide.
      if (navigator.sendBeacon) {
        navigator.sendBeacon(trackUrl, new Blob([body], { type: 'text/plain;charset=UTF-8' }));
      } else {
        fetch(trackUrl, { method: 'POST', body: body, keepalive: true, mode: 'cors',
          headers: { 'Content-Type': 'text/plain;charset=UTF-8' } });
      }
    } catch (e) { /* analytics must never break the host page */ }
  }

  function observeImpressions(nodes, items, url) {
    if (!window.IntersectionObserver) return;
    var seen = new WeakSet();
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting && !seen.has(entry.target)) {
          seen.add(entry.target);
          track(url, 'impression', Number(entry.target.dataset.id));
          io.unobserve(entry.target);
        }
      });
    }, { threshold: 0.5 });

    Array.prototype.forEach.call(nodes, function (node, index) {
      if (!items[index]) return;
      node.dataset.id = items[index].id;
      io.observe(node);
    });
  }

  // ------------------------------------------------------------- breakpoints

  // Container width, not viewport — the host may drop us in a 300px sidebar.
  function sizeFor(width) {
    return width >= 760 ? 'lg' : width >= 480 ? 'md' : 'sm';
  }

  function observeSize(host, wrap) {
    wrap.setAttribute('data-w', sizeFor(host.clientWidth));
    if (!window.ResizeObserver) return;
    new ResizeObserver(function (entries) {
      wrap.setAttribute('data-w', sizeFor(entries[0].contentRect.width));
    }).observe(host);
  }

  // ------------------------------------------------------------ base styles

  // Tokens, reset, skeleton and credit line. Every renderer prepends this to
  // its own CSS, so the theme contract lives in exactly one place.
  function baseStyles(theme) {
    return [
      ':host{all:initial;display:block;contain:content}',
      '*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}',
      '.wf{',
      'font-family:', theme.font === 'inherit'
        ? 'inherit,system-ui,-apple-system,"Segoe UI",Roboto,sans-serif'
        : theme.font, ';',
      'color:', theme.text, ';',
      '--text:', theme.text, ';',
      '--accent:', theme.accent, ';',
      '--accent-text:', theme.accent_text, ';',
      '--muted:', theme.muted, ';',
      '--surface:', theme.surface, ';',
      '--border:', theme.border, ';',
      '--radius:', theme.radius, ';',
      'line-height:1.45;font-size:15px;-webkit-font-smoothing:antialiased}',

      '.wf-grid{display:grid;gap:16px;grid-template-columns:repeat(1,minmax(0,1fr))}',
      '.wf[data-w="md"] .wf-grid{grid-template-columns:repeat(2,minmax(0,1fr))}',
      '.wf[data-w="lg"] .wf-grid{grid-template-columns:repeat(var(--cols,3),minmax(0,1fr))}',

      '.wf-skel{background:var(--border);border-radius:var(--radius);',
      'aspect-ratio:4/3;opacity:.55;animation:wf-pulse 1.4s ease-in-out infinite}',
      '@keyframes wf-pulse{0%,100%{opacity:.35}50%{opacity:.7}}',

      '.wf-credit{margin-top:12px;font-size:.75em;color:var(--muted);text-align:right}',
      '.wf-credit a{color:inherit;text-decoration:none;border-bottom:1px solid var(--border)}',

      '@media (prefers-reduced-motion:reduce){.wf-skel{animation:none}}'
    ].join('');
  }

  var DEFAULT_THEME = {
    font: 'inherit', text: '#111111', accent: '#0f5c4d', accent_text: '#ffffff',
    muted: '#6b7280', surface: '#ffffff', border: '#e5e7eb', radius: '10px'
  };

  function credit(config) {
    return el('p', { class: 'wf-credit' }, [
      el('a', { href: config.brand_url, target: '_blank', rel: 'noopener', text: 'Powered by Weyfarin' })
    ]);
  }

  function skeleton(root, count) {
    var wrap = el('div', { class: 'wf' });
    var grid = el('div', { class: 'wf-grid' });
    for (var i = 0; i < count; i++) grid.appendChild(el('div', { class: 'wf-skel' }));
    wrap.appendChild(grid);
    root.innerHTML = '';
    root.appendChild(el('style', { text: baseStyles(DEFAULT_THEME) }));
    root.appendChild(wrap);
    wrap.style.setProperty('--cols', String(Math.min(count, 3)));
    wrap.setAttribute('data-w', sizeFor(root.host.clientWidth));
  }

  // --------------------------------------------------------------- renderers

  var registry = {};
  var pending = {};

  function define(name, fn) {
    registry[name] = fn;
  }

  function loadRenderer(name) {
    if (registry[name]) return Promise.resolve(registry[name]);
    if (pending[name]) return pending[name];

    pending[name] = new Promise(function (resolve, reject) {
      var script = document.createElement('script');
      script.src = BASE + 'r/' + name + '.js?v=' + VERSION;
      script.async = true;
      script.onload = function () {
        if (registry[name]) resolve(registry[name]);
        else reject(new Error('renderer "' + name + '" loaded but did not register'));
      };
      script.onerror = function () {
        delete pending[name];   // allow a retry on the next mount
        reject(new Error('could not load renderer "' + name + '" from ' + script.src));
      };
      (document.head || document.documentElement).appendChild(script);
    });

    return pending[name];
  }

  // Everything a renderer is allowed to use. Renderers never touch the
  // loader's internals directly, so this object is the whole contract.
  var api = {
    version: VERSION,
    origin: ORIGIN,
    base: BASE,
    el: el,
    money: money,
    getJson: getJson,
    postJson: postJson,
    load: loadRenderer,      // a renderer may lazy-load another (search → detail)
    track: track,
    flush: flush,
    warn: warn,
    baseStyles: baseStyles,
    credit: credit,
    observeSize: observeSize,
    observeImpressions: observeImpressions
  };

  // -------------------------------------------------------------------- boot

  function configUrl(node, key) {
    var query = [];
    // Builder preview passes the config version to skip the 5-minute cache.
    if (node.dataset.version) query.push('v=' + encodeURIComponent(node.dataset.version));
    // Location widgets: one key across many hotel pages (PLAN.md D4).
    if (node.dataset.lat && node.dataset.lng) {
      query.push('lat=' + encodeURIComponent(node.dataset.lat));
      query.push('lng=' + encodeURIComponent(node.dataset.lng));
    }
    return ORIGIN + '/api/embed/' + encodeURIComponent(key) + '.json' +
      (query.length ? '?' + query.join('&') : '');
  }

  function applyOverrides(node, config) {
    // Inline data-* attributes win over the dashboard config.
    if (node.dataset.layout)  config.layout = node.dataset.layout;
    if (node.dataset.columns) config.columns = Number(node.dataset.columns);
    if (node.dataset.target)  config.target = node.dataset.target;
    if (node.dataset.accent)  config.theme.accent = node.dataset.accent;
    if (node.dataset.radius)  config.theme.radius = node.dataset.radius;
    if (node.dataset.font)    config.theme.font = node.dataset.font;
    // data-routing="none" stops the widget touching the page URL — escape
    // hatch for SPA hosts whose router objects to our history entries.
    config.routing = node.dataset.routing === 'none' ? 'none' : 'query';
  }

  function mount(node) {
    if (!node || node.getAttribute(INIT)) return;
    node.setAttribute(INIT, '1');

    var key = node.getAttribute(ATTR);
    if (!key) return;

    // Reuse the shadow root on refresh — attachShadow twice throws.
    var root = node.shadowRoot || (node.attachShadow ? node.attachShadow({ mode: 'open' }) : node);
    skeleton(root, Number(node.dataset.count || 3));

    getJson(configUrl(node, key))
      .then(function (data) {
        var type = data.widget.type || 'listing';
        var name = data.widget.renderer || RENDERERS[type];
        if (!name) throw new Error('unknown widget type "' + type + '"');

        applyOverrides(node, data.widget);

        return loadRenderer(name).then(function (render) {
          render(root, data, api);
        });
      })
      .catch(function (error) {
        // Fail invisibly on the page, loudly in the console. A broken widget
        // must never leave debris on a partner's site.
        root.innerHTML = '';
        warn(error.message);
      });
  }

  // Re-render an already-mounted widget, e.g. the builder's live preview
  // after a save. Set data-version first to bypass the config cache.
  function refresh(node) {
    if (!node) return;
    node.removeAttribute(INIT);
    mount(node);
  }

  function scan(scope) {
    (scope || document).querySelectorAll('[' + ATTR + ']:not([' + INIT + '])')
      .forEach(function (node) {
        if (node.tagName !== 'SCRIPT') mount(node);
      });
  }

  // Convenience: <script src="…/v1.js" data-weyfarin="pk_…"></script> with no container.
  if (SELF && SELF.getAttribute(ATTR)) {
    var holder = el('div', { 'data-weyfarin': SELF.getAttribute(ATTR) });
    ['layout', 'columns', 'accent', 'target', 'count', 'radius', 'font', 'lat', 'lng', 'routing'].forEach(function (key) {
      if (SELF.dataset[key]) holder.dataset[key] = SELF.dataset[key];
    });
    SELF.parentNode.insertBefore(holder, SELF);
  }

  // Public API — set before the first scan so renderers loaded later can
  // register through it.
  window.Weyfarin = {
    version: VERSION,
    define: define,
    mount: mount,
    refresh: refresh,
    render: scan
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { scan(); });
  } else {
    scan();
  }

  // React / Vue / Nuxt hosts mount containers after our script runs.
  if (window.MutationObserver) {
    new MutationObserver(function (mutations) {
      for (var i = 0; i < mutations.length; i++) {
        if (mutations[i].addedNodes.length) { scan(); break; }
      }
    }).observe(document.documentElement, { childList: true, subtree: true });
  }

  window.addEventListener('pagehide', flush);
})();
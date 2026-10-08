/*!
 * Weyfarin renderer: search
 *
 * The full catalogue page: search box, destination filter, card grid and
 * "show more". Clicking a card opens the experience inside the widget and
 * puts ?wf_exp={slug} on the partner's URL, so the browser Back button,
 * refresh, sharing and Ctrl+click all behave like a real page.
 *
 * The detail view itself lives in r/detail.js and is loaded on first open.
 */
(function () {
  'use strict';

  var W = window.Weyfarin;
  if (!W || !W.define) return;

  var PARAM = 'wf_exp';
  var routerTaken = false;   // one search widget per page owns the URL

  var ICON_PIN = 'M12 21s-7-6.2-7-11.5a7 7 0 0 1 14 0C19 14.8 12 21 12 21zM12 12a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5z';
  var ICON_SEARCH = 'M11 18a7 7 0 1 1 0-14 7 7 0 0 1 0 14zM20 20l-4.2-4.2';
  var ICON_CHEVRON = 'M6 9l6 6 6-6';

  function icon(d, size) {
    var ns = 'http://www.w3.org/2000/svg';
    var svg = document.createElementNS(ns, 'svg');
    var path = document.createElementNS(ns, 'path');
    svg.setAttribute('viewBox', '0 0 24 24');
    svg.setAttribute('width', size || 16);
    svg.setAttribute('height', size || 16);
    svg.setAttribute('fill', 'none');
    svg.setAttribute('stroke', 'currentColor');
    svg.setAttribute('stroke-width', '1.8');
    svg.setAttribute('stroke-linecap', 'round');
    svg.setAttribute('stroke-linejoin', 'round');
    svg.setAttribute('aria-hidden', 'true');
    path.setAttribute('d', d);
    svg.appendChild(path);
    return svg;
  }

  // ------------------------------------------------------------------ URL

  function slugFromUrl() {
    try { return new URL(location.href).searchParams.get(PARAM); } catch (e) { return null; }
  }

  function urlFor(slug) {
    var url = new URL(location.href);
    if (slug) url.searchParams.set(PARAM, slug);
    else url.searchParams.delete(PARAM);
    return url.toString();
  }

  // Keep whatever the host router stored in history.state (Next.js, React
  // Router) and add ours beside it, so their popstate handling still works.
  function stateFor(slug) {
    var current = history.state && typeof history.state === 'object' ? history.state : {};
    var next = {};
    for (var key in current) next[key] = current[key];
    next.wfExp = slug || null;
    return next;
  }

  // ---------------------------------------------------------------- styles

  function styles() {
    return [
      '.wf-vh{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap}',
      '[hidden]{display:none!important}',

      '.wf-s-head{margin-bottom:18px}',
      '.wf-s-head h2{font-size:1.6em;font-weight:680;letter-spacing:-.02em;line-height:1.2}',
      '.wf-s-count{margin-top:4px;font-size:.88em;color:var(--muted)}',

      '.wf-s-controls{display:flex;flex-wrap:wrap;gap:10px;margin-bottom:22px}',
      '.wf-s-field{position:relative;flex:1 1 260px}',
      '.wf-s-field svg{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--muted);pointer-events:none}',
      '.wf-s-sel{position:relative;flex:0 1 220px;min-width:170px}',
      '.wf-s-sel svg{position:absolute;right:12px;top:50%;transform:translateY(-50%);color:var(--muted);pointer-events:none}',
      '.wf-s-input,.wf-s-select{width:100%;height:42px;border:1px solid var(--border);border-radius:calc(var(--radius) - 2px);',
      'background:var(--surface);color:var(--text);font:inherit;font-size:.92em}',
      '.wf-s-input{padding:0 12px 0 36px}',
      '.wf-s-select{padding:0 34px 0 12px;-webkit-appearance:none;appearance:none;cursor:pointer}',
      '.wf-s-input:focus,.wf-s-select:focus{outline:2px solid var(--accent);outline-offset:1px}',

      '.wf-s .wf-grid{row-gap:28px;transition:opacity .15s ease}',
      '.wf-s[data-busy] .wf-grid{opacity:.5}',

      '.wf-scard{display:flex;flex-direction:column;text-decoration:none;color:inherit;height:100%}',
      '.wf-scard:focus-visible{outline:2px solid var(--accent);outline-offset:4px;border-radius:var(--radius)}',
      '.wf-scard-media{position:relative;aspect-ratio:4/3;border-radius:var(--radius);overflow:hidden;background:var(--border)}',
      '.wf-scard-media img{width:100%;height:100%;object-fit:cover;display:block;border:0;transition:transform .35s ease}',
      '.wf-scard:hover .wf-scard-media img{transform:scale(1.03)}',
      '.wf-scard-body{padding:10px 2px 0;display:flex;flex-direction:column;gap:4px;flex:1}',
      '.wf-scard-title{font-size:.95em;font-weight:620;line-height:1.35;',
      'display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}',
      '.wf-scard-place{display:flex;align-items:center;gap:4px;font-size:.82em;color:var(--muted)}',
      '.wf-scard-foot{margin-top:auto;padding-top:8px;display:flex;align-items:center;justify-content:space-between;gap:8px}',
      '.wf-scard-price{font-size:.8em;color:var(--muted);line-height:1.3}',
      '.wf-scard-price strong{display:block;font-size:1.2em;color:var(--text);font-weight:650}',
      '.wf-scard-cta{padding:7px 14px;border-radius:999px;background:var(--accent);color:var(--accent-text);',
      'font-size:.8em;font-weight:600;white-space:nowrap}',

      '.wf-s-more{display:block;margin:28px auto 0;padding:10px 22px;border:1px solid var(--border);border-radius:999px;',
      'background:var(--surface);color:var(--text);font:inherit;font-size:.88em;font-weight:560;cursor:pointer}',
      '.wf-s-more:hover{border-color:var(--text)}',

      '.wf-s-empty{padding:48px 16px;text-align:center;color:var(--muted)}',
      '.wf-s-empty button{margin-top:12px;padding:8px 16px;border:1px solid var(--border);border-radius:999px;',
      'background:var(--surface);color:var(--text);font:inherit;font-size:.85em;cursor:pointer}',
      '.wf-s-error{margin-top:14px;font-size:.85em;color:#b42318}',

      '.wf-dload{display:grid;gap:12px}',
      '.wf-dload div{background:var(--border);border-radius:var(--radius);opacity:.55;animation:wf-pulse 1.4s ease-in-out infinite}',

      '@media (prefers-reduced-motion:reduce){.wf-scard-media img,.wf-s .wf-grid{transition:none}',
      '.wf-scard:hover .wf-scard-media img{transform:none}}'
    ].join('');
  }

  // ---------------------------------------------------------------- render

  W.define('search', function (root, data, api) {
    var el = api.el;
    var config = data.widget;
    var apiUrl = config.api_url || (api.origin + '/api/embed/' + config.key);

    var routed = config.routing !== 'none' && !routerTaken;
    if (routed) routerTaken = true;

    var state = {
      q: '',
      destination: '',
      page: (data.search && data.search.page) || 1,
      total: data.search ? data.search.total : (data.items || []).length,
      hasMore: !!(data.search && data.search.has_more),
      controller: null,
      timer: null,
      current: null,       // slug of the open detail, or null for the list
      pushed: false,       // did *we* push the current history entry?
      listScroll: 0
    };

    // --- list view -------------------------------------------------------

    var count = el('p', { class: 'wf-s-count', 'aria-live': 'polite' });

    var input = el('input', {
      class: 'wf-s-input', type: 'search', placeholder: 'Search experiences',
      autocomplete: 'off', 'aria-label': 'Search experiences'
    });

    var select = el('select', { class: 'wf-s-select', 'aria-label': 'Destination' }, [
      el('option', { value: '', text: 'All destinations' })
    ]);
    ((data.facets && data.facets.destinations) || []).forEach(function (city) {
      select.appendChild(el('option', { value: city, text: city }));
    });

    var form = el('form', { class: 'wf-s-controls', role: 'search' }, [
      el('div', { class: 'wf-s-field' }, [icon(ICON_SEARCH), input]),
      el('div', { class: 'wf-s-sel' }, [select, icon(ICON_CHEVRON)])
    ]);

    var grid = el('div', { class: 'wf-grid' });
    var more = el('button', { class: 'wf-s-more', type: 'button', text: 'Show more experiences' });
    var clear = el('button', { type: 'button', text: 'Clear search' });
    var empty = el('div', { class: 'wf-s-empty', hidden: '' }, [
      el('p', { text: 'No experiences match your search.' }), clear
    ]);
    var error = el('p', { class: 'wf-s-error', role: 'status', hidden: '' });

    var listView = el('section', { class: 'wf-view' }, [
      el('div', { class: 'wf-s-head' }, [
        el('h2', { text: config.heading || 'Find your next experience' }), count
      ]),
      form, grid, empty, more, error
    ]);

    var detailView = el('section', { class: 'wf-view', hidden: '' });

    var wrap = el('div', { class: 'wf wf-s', 'data-w': 'sm' }, [listView, detailView, api.credit(config)]);
    wrap.style.setProperty('--cols', String(config.columns || 3));

    root.innerHTML = '';
    root.appendChild(el('style', { text: api.baseStyles(config.theme) + styles() }));
    root.appendChild(wrap);
    api.observeSize(root.host, wrap);

    // --- cards -------------------------------------------------------------

    function card(item) {
      var link = el('a', {
        class: 'wf-scard',
        href: routed ? urlFor(item.slug) : item.url
      }, [
        el('div', { class: 'wf-scard-media' }, [
          item.image ? el('img', { src: item.image, alt: item.title, loading: 'lazy', decoding: 'async' }) : null
        ]),
        el('div', { class: 'wf-scard-body' }, [
          el('h3', { class: 'wf-scard-title', text: item.title }),
          item.location ? el('p', { class: 'wf-scard-place' }, [icon(ICON_PIN, 14), el('span', { text: item.location })]) : null,
          el('div', { class: 'wf-scard-foot' }, [
            item.price > 0
              ? el('p', { class: 'wf-scard-price', text: 'From' }, [
                  el('strong', { text: api.money(item.price, item.currency) })
                ])
              : el('span'),
            el('span', { class: 'wf-scard-cta', text: config.cta || 'Book now' })
          ])
        ])
      ]);

      link.addEventListener('click', function (event) {
        // Let the browser handle new-tab / new-window clicks: the href is a
        // real deep link, so they land on the detail directly.
        if (event.defaultPrevented || event.button !== 0 ||
            event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
        event.preventDefault();
        api.track(config.track_url, 'click', item.id);
        open(item.slug, true);
      });

      return link;
    }

    function addCards(items) {
      var nodes = items.map(card);
      nodes.forEach(function (node) { grid.appendChild(node); });
      api.observeImpressions(nodes, items, config.track_url);
    }

    function updateMeta() {
      var shown = grid.children.length;
      var noun = state.total === 1 ? ' experience' : ' experiences';

      count.textContent = state.hasMore
        ? 'Showing ' + shown + ' of ' + state.total + noun
        : state.total + noun;

      empty.hidden = state.total !== 0;
      grid.hidden = state.total === 0;
      more.hidden = !state.hasMore;
    }

    // --- search ------------------------------------------------------------

    function runSearch(reset) {
      if (state.controller) state.controller.abort();
      var controller = window.AbortController ? new AbortController() : null;
      state.controller = controller;

      var page = reset ? 1 : state.page + 1;
      var query = 'q=' + encodeURIComponent(state.q) +
        '&destination=' + encodeURIComponent(state.destination) +
        '&page=' + page;

      wrap.setAttribute('data-busy', '');
      error.hidden = true;

      api.getJson(apiUrl + '/search.json?' + query, controller ? controller.signal : undefined)
        .then(function (result) {
          if (controller !== state.controller) return;   // a newer search won
          state.page = result.page;
          state.total = result.total;
          state.hasMore = result.has_more;
          if (reset) grid.innerHTML = '';
          addCards(result.items || []);
          updateMeta();
        })
        .catch(function (err) {
          if (err && err.name === 'AbortError') return;
          error.textContent = 'Could not load experiences. Please try again.';
          error.hidden = false;
          api.warn(err.message);
        })
        .then(function () {
          if (controller === state.controller) wrap.removeAttribute('data-busy');
        });
    }

    input.addEventListener('input', function () {
      clearTimeout(state.timer);
      state.timer = setTimeout(function () {
        var q = input.value.trim();
        if (q === state.q) return;
        state.q = q;
        runSearch(true);
      }, 250);
    });

    form.addEventListener('submit', function (event) {
      event.preventDefault();
      clearTimeout(state.timer);
      state.q = input.value.trim();
      runSearch(true);
    });

    select.addEventListener('change', function () {
      state.destination = select.value;
      runSearch(true);
    });

    more.addEventListener('click', function () { runSearch(false); });

    clear.addEventListener('click', function () {
      input.value = '';
      select.value = '';
      state.q = '';
      state.destination = '';
      runSearch(true);
      input.focus();
    });

    // --- detail + routing ----------------------------------------------------

    function bringIntoView() {
      var top = root.host.getBoundingClientRect().top;
      if (top >= 0 && top < window.innerHeight * 0.4) return;
      var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
      root.host.scrollIntoView({ block: 'start', behavior: reduce ? 'auto' : 'smooth' });
    }

    // A nested renderer (checkout) may leave things outside the shadow root —
    // the Stripe mount point and its CSS guard. It registers a cleanup here.
    function clearDetail() {
      if (typeof detailView.__wfCleanup === 'function') {
        try { detailView.__wfCleanup(); } catch (e) { api.warn(e.message); }
      }
      detailView.__wfCleanup = null;
      detailView.innerHTML = '';
    }

    function showList() {
      state.current = null;
      detailView.hidden = true;
      clearDetail();
      listView.hidden = false;
      if (state.listScroll) window.scrollTo(0, state.listScroll);
    }

    function back() {
      if (routed && state.pushed) {
        history.back();                   // popstate takes it from here
        return;
      }
      if (routed) history.replaceState(stateFor(null), '', urlFor(null));
      showList();
    }

    function loading() {
      var heights = [260, 34, 18, 180];
      return el('div', { class: 'wf-dload', 'aria-label': 'Loading' }, heights.map(function (h) {
        var block = el('div');
        block.style.height = h + 'px';
        return block;
      }));
    }

    function open(slug, push) {
      state.listScroll = window.pageYOffset;

      if (push && routed) {
        history.pushState(stateFor(slug), '', urlFor(slug));
        state.pushed = true;
      }

      state.current = slug;
      listView.hidden = true;
      detailView.hidden = false;
      clearDetail();
      detailView.appendChild(loading());
      bringIntoView();

      Promise.all([
        api.load('detail'),
        api.getJson(apiUrl + '/experiences/' + encodeURIComponent(slug) + '.json')
      ])
        .then(function (results) {
          if (state.current !== slug) return;   // user moved on while loading
          results[0](detailView, { widget: config, experience: results[1].experience }, api, {
            embedded: true,
            onBack: back
          });
          api.track(config.track_url, 'detail', results[1].experience.id);
        })
        .catch(function (err) {
          if (state.current !== slug) return;
          api.warn(err.message);
          var backButton = el('button', { type: 'button', text: 'Back to all experiences' });
          backButton.addEventListener('click', back);
          detailView.innerHTML = '';
          detailView.appendChild(el('div', { class: 'wf-s-empty' }, [
            el('p', { text: 'This experience is not available right now.' }), backButton
          ]));
        });
    }

    if (routed) {
      window.addEventListener('popstate', function () {
        var slug = slugFromUrl();
        if (slug === state.current) return;
        if (slug) {
          // Reaching a detail entry by popstate means Forward from the list,
          // so the entry before it is ours.
          open(slug, false);
          state.pushed = true;
        } else {
          state.pushed = false;
          showList();
        }
      });
    }

    // --- first paint -----------------------------------------------------------

    addCards(data.items || []);
    updateMeta();

    var initial = routed ? slugFromUrl() : null;
    if (initial) open(initial, false);   // deep link / refresh on a detail
  });
})();
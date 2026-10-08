/*!
 * Weyfarin renderer: listing
 * Serves widget types: listing, category, featured, location.
 * They differ only in which experiences the server selects (PLAN.md D4).
 */
(function () {
  'use strict';

  var W = window.Weyfarin;
  if (!W || !W.define) return;

  function styles() {
    return [
      '.wf-head{display:flex;align-items:baseline;justify-content:space-between;gap:12px;margin-bottom:14px}',
      '.wf-head h3{font-size:1.15em;font-weight:650;letter-spacing:-0.01em}',
      '.wf-all{font-size:.85em;color:var(--accent);text-decoration:none;border-bottom:1px solid transparent}',
      '.wf-all:hover{border-bottom-color:currentColor}',

      '.wf-rail{display:flex;gap:16px;overflow-x:auto;scroll-snap-type:x mandatory;',
      'padding-bottom:6px;scrollbar-width:thin}',
      '.wf-rail>*{flex:0 0 78%;scroll-snap-align:start}',
      '.wf[data-w="md"] .wf-rail>*{flex-basis:44%}',
      '.wf[data-w="lg"] .wf-rail>*{flex-basis:calc((100% - (var(--cols,3) - 1) * 16px) / var(--cols,3))}',

      '.wf-list{display:flex;flex-direction:column;gap:12px}',
      '.wf-list .wf-card{flex-direction:row}',
      '.wf-list .wf-media{flex:0 0 34%;aspect-ratio:1/1}',

      '.wf-card{display:flex;flex-direction:column;background:var(--surface);',
      'border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;',
      'text-decoration:none;color:inherit;height:100%;position:relative;',
      'transition:border-color .18s ease,transform .18s ease}',
      '.wf-card:hover{border-color:var(--accent);transform:translateY(-2px)}',
      '.wf-card:focus-visible{outline:2px solid var(--accent);outline-offset:3px}',

      '.wf-media{position:relative;aspect-ratio:4/3;background:var(--border);overflow:hidden}',
      '.wf-media img{width:100%;height:100%;object-fit:cover;display:block;border:0}',
      '.wf-badge{position:absolute;top:10px;left:10px;background:var(--accent);',
      'color:var(--accent-text);font-size:.72em;font-weight:600;padding:4px 9px;border-radius:999px}',

      '.wf-body{padding:13px 14px 14px;display:flex;flex-direction:column;gap:5px;flex:1}',
      '.wf-title{font-size:1em;font-weight:620;letter-spacing:-0.005em;',
      'display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}',
      '.wf-place{font-size:.84em;color:var(--muted)}',
      '.wf-foot{margin-top:auto;padding-top:10px;display:flex;align-items:flex-end;',
      'justify-content:space-between;gap:10px}',
      '.wf-price{font-size:.95em;font-weight:640;white-space:nowrap}',
      '.wf-price span{display:block;font-size:.76em;font-weight:400;color:var(--muted)}',
      '.wf-meta{display:flex;flex-direction:column;gap:3px;font-size:.8em;color:var(--muted)}',
      '.wf-rate{color:var(--text);font-weight:560}',
      '.wf-dist{color:var(--accent);font-weight:560}',
      '.wf-cta{display:inline-block;margin-top:10px;padding:8px 13px;border-radius:calc(var(--radius) - 3px);',
      'background:var(--accent);color:var(--accent-text);font-size:.85em;font-weight:560;text-align:center}',

      '@media (prefers-reduced-motion:reduce){',
      '.wf-card{transition:none}.wf-card:hover{transform:none}}'
    ].join('');
  }

  // Location widgets only. Under 1 km reads better than "0 km away".
  function distanceLabel(km) {
    if (km < 1) return 'Under 1 km away';
    return (km < 10 ? km.toFixed(1) : Math.round(km)) + ' km away';
  }

  function card(item, config, api) {
    var el = api.el;
    var show = config.show;

    var media = el('div', { class: 'wf-media' }, [
      item.image ? el('img', {
        src: item.image,
        alt: item.title,
        loading: 'lazy',
        decoding: 'async'
      }) : null,
      (show.badge && item.badge) ? el('span', { class: 'wf-badge', text: item.badge }) : null
    ]);

    var meta = el('div', { class: 'wf-meta' }, [
      (show.distance !== false && item.distance != null)
        ? el('span', { class: 'wf-dist', text: distanceLabel(item.distance) }) : null,
      (show.duration && item.duration) ? el('span', { text: item.duration }) : null,
      (show.rating && item.rating) ? el('span', { class: 'wf-rate',
        text: '\u2605 ' + item.rating + (item.reviews ? ' (' + item.reviews + ')' : '') }) : null
    ]);

    var price = (show.price && item.price > 0)
      ? el('div', { class: 'wf-price', text: api.money(item.price, item.currency) }, [
          el('span', { text: 'per person' })
        ])
      : null;

    var body = el('div', { class: 'wf-body' }, [
      el('h4', { class: 'wf-title', text: item.title }),
      (show.location && item.location) ? el('p', { class: 'wf-place', text: item.location }) : null,
      el('div', { class: 'wf-foot' }, [meta, price]),
      show.cta ? el('span', { class: 'wf-cta', text: config.cta }) : null
    ]);

    var link = el('a', {
      class: 'wf-card',
      href: item.url,
      target: config.target,
      rel: config.target === '_blank' ? 'noopener noreferrer' : null
    }, [media, body]);

    link.addEventListener('click', function () {
      api.track(config.track_url, 'click', item.id);
      api.flush();
    });

    return link;
  }

  W.define('listing', function (root, data, api) {
    var el = api.el;
    var config = data.widget;
    var items = data.items || [];

    // Nothing to show is not an error — render nothing, leave no debris.
    if (!items.length) {
      root.innerHTML = '';
      return;
    }

    var wrap = el('div', { class: 'wf', 'data-w': 'sm' });
    wrap.style.setProperty('--cols', String(config.columns || 3));

    if (config.heading) {
      wrap.appendChild(el('div', { class: 'wf-head' }, [
        el('h3', { text: config.heading }),
        el('a', {
          class: 'wf-all',
          href: config.brand_url,
          target: config.target,
          rel: 'noopener',
          text: 'See all experiences'
        })
      ]));
    }

    var listClass = config.layout === 'carousel' ? 'wf-rail'
      : config.layout === 'list' ? 'wf-list' : 'wf-grid';

    var list = el('div', { class: listClass });
    items.forEach(function (item) { list.appendChild(card(item, config, api)); });
    wrap.appendChild(list);
    wrap.appendChild(api.credit(config));

    root.innerHTML = '';
    root.appendChild(el('style', { text: api.baseStyles(config.theme) + styles() }));
    root.appendChild(wrap);

    api.observeSize(root.host, wrap);
    api.observeImpressions(list.querySelectorAll('.wf-card'), items, config.track_url);
  });
})();

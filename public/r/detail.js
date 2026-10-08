/*!
 * Weyfarin renderer: detail
 *
 * One experience: gallery, highlights, itinerary, meeting point, host,
 * "things to keep in mind", and the booking card.
 *
 * Two ways in:
 *   - standalone: a widget of type "detail" — (shadowRoot, data, api)
 *   - embedded:   opened by r/search.js — (section, data, api, { embedded, onBack })
 *
 * "Confirm booking" emits a composed `weyfarin:checkout` event (partner
 * analytics) and hands off to r/booking.js for guest details and payment.
 */
(function () {
  'use strict';

  var W = window.Weyfarin;
  if (!W || !W.define) return;

  var ICON_PIN = 'M12 21s-7-6.2-7-11.5a7 7 0 0 1 14 0C19 14.8 12 21 12 21zM12 12a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5z';
  var ICON_CLOCK = 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18zM12 7v5l3 2';
  var ICON_STAR = 'M12 3.5l2.6 5.4 5.9.8-4.3 4.1 1 5.8L12 16.9l-5.2 2.7 1-5.8-4.3-4.1 5.9-.8z';
  var ICON_BACK = 'M15 18l-6-6 6-6';
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

  function styles() {
    return [
      '[hidden]{display:none!important}',

      '.wf-back{display:inline-flex;align-items:center;gap:4px;margin:0 0 14px -4px;padding:6px 4px;',
      'background:none;border:0;color:var(--muted);font:inherit;font-size:.88em;cursor:pointer}',
      '.wf-back:hover{color:var(--text)}',

      '.wf-gal{display:flex;gap:8px;overflow-x:auto;scroll-snap-type:x mandatory;margin-bottom:18px;scrollbar-width:thin}',
      '.wf-gal img{flex:0 0 88%;min-width:0;aspect-ratio:4/3;object-fit:cover;scroll-snap-align:start;',
      'border-radius:var(--radius);background:var(--border);display:block;border:0}',
      '.wf-gal[data-n="1"] img{flex-basis:100%}',
      '.wf[data-w="lg"] .wf-gal{display:grid;grid-template-columns:2fr 1fr 1fr;grid-template-rows:190px 190px;overflow:visible}',
      '.wf[data-w="lg"] .wf-gal img{width:100%;height:100%;aspect-ratio:auto;border-radius:calc(var(--radius) - 2px)}',
      '.wf[data-w="lg"] .wf-gal img:first-child{grid-row:span 2}',
      '.wf[data-w="lg"] .wf-gal[data-n="1"]{grid-template-columns:1fr;grid-template-rows:400px}',
      '.wf[data-w="lg"] .wf-gal[data-n="2"],.wf[data-w="lg"] .wf-gal[data-n="3"]{grid-template-columns:2fr 1fr}',

      '.wf-d-title{font-size:1.55em;font-weight:680;letter-spacing:-.02em;line-height:1.2}',
      '.wf-d-meta{display:flex;flex-wrap:wrap;gap:6px 18px;margin-top:8px;font-size:.88em;color:var(--muted)}',
      '.wf-d-meta span{display:inline-flex;align-items:center;gap:5px}',
      '.wf-d-meta .wf-d-rate{color:var(--text);font-weight:560}',

      '.wf-d-layout{display:grid;gap:24px;margin-top:20px}',
      '.wf[data-w="lg"] .wf-d-layout{grid-template-columns:minmax(0,1fr) 330px;gap:44px}',
      '.wf[data-w="lg"] .wf-d-aside{position:sticky;top:16px;align-self:start}',

      '.wf-d-sec{padding:22px 0;border-top:1px solid var(--border)}',
      '.wf-d-sec:first-child{border-top:0;padding-top:0}',
      '.wf-d-sec h3{font-size:1.08em;font-weight:650;margin-bottom:12px}',
      '.wf-d-sec p{line-height:1.65;margin-bottom:10px}',
      '.wf-d-sec p:last-child{margin-bottom:0}',

      '.wf-d-hl{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;margin-top:18px}',
      '.wf[data-w="lg"] .wf-d-hl{grid-template-columns:repeat(4,minmax(0,1fr))}',
      '.wf-d-hl div{padding:12px;border:1px solid var(--border);border-radius:calc(var(--radius) - 2px)}',
      '.wf-d-hl dt{font-size:.76em;color:var(--muted)}',
      '.wf-d-hl dd{margin-top:2px;font-size:.9em;font-weight:560}',

      '.wf-d-steps{list-style:none;counter-reset:wfstep}',
      '.wf-d-steps li{position:relative;padding:0 0 18px 40px;counter-increment:wfstep}',
      '.wf-d-steps li::before{content:counter(wfstep);position:absolute;left:0;top:0;width:26px;height:26px;border-radius:50%;',
      'background:var(--accent);color:var(--accent-text);font-size:.76em;font-weight:650;display:grid;place-items:center}',
      '.wf-d-steps li::after{content:"";position:absolute;left:12px;top:30px;bottom:4px;width:2px;background:var(--border)}',
      '.wf-d-steps li:last-child{padding-bottom:0}',
      '.wf-d-steps li:last-child::after{display:none}',
      '.wf-d-step-t{font-weight:620;line-height:1.4}',
      '.wf-d-step-time{margin-left:8px;font-size:.8em;font-weight:400;color:var(--muted)}',
      '.wf-d-steps p{margin-top:3px;font-size:.92em;color:var(--muted);line-height:1.55}',

      '.wf-d-map{width:100%;height:240px;border:0;border-radius:var(--radius);display:block;background:var(--border);margin:10px 0 8px}',
      '.wf-d-maplink{font-size:.85em;color:var(--accent);text-decoration:none}',
      '.wf-d-maplink:hover{text-decoration:underline}',

      '.wf-d-host{display:flex;gap:14px;align-items:flex-start}',
      '.wf-d-avatar{flex:0 0 52px;width:52px;height:52px;border-radius:50%;overflow:hidden;background:var(--accent);',
      'color:var(--accent-text);display:grid;place-items:center;font-weight:650}',
      '.wf-d-avatar img{width:100%;height:100%;object-fit:cover;display:block}',
      '.wf-d-host strong{display:block;font-weight:620}',
      '.wf-d-host p{margin-top:3px;font-size:.92em;color:var(--muted)}',

      '.wf-d-keep{display:grid;gap:14px}',
      '.wf[data-w="lg"] .wf-d-keep{grid-template-columns:repeat(2,minmax(0,1fr));gap:18px 28px}',
      '.wf-d-keep strong{display:block;font-size:.92em;font-weight:620}',
      '.wf-d-keep p{margin:3px 0 0;font-size:.88em;color:var(--muted);line-height:1.55}',

      '.wf-book{border:1px solid var(--border);border-radius:var(--radius);padding:18px;background:var(--surface);',
      'box-shadow:0 8px 28px rgba(0,0,0,.06)}',
      '.wf-book-price strong{font-size:1.45em;font-weight:700;letter-spacing:-.01em}',
      '.wf-book-price span{margin-left:4px;font-size:.85em;color:var(--muted)}',
      '.wf-book-label{display:block;margin:16px 0 6px;font-size:.8em;color:var(--muted)}',
      '.wf-book-sel{position:relative}',
      '.wf-book-sel svg{position:absolute;right:12px;top:50%;transform:translateY(-50%);color:var(--muted);pointer-events:none}',
      '.wf-book select{width:100%;height:44px;padding:0 34px 0 12px;border:1px solid var(--border);',
      'border-radius:calc(var(--radius) - 2px);background:var(--surface);color:var(--text);font:inherit;font-size:.92em;',
      '-webkit-appearance:none;appearance:none;cursor:pointer}',
      '.wf-book select:focus{outline:2px solid var(--accent);outline-offset:1px}',
      '.wf-book-row{display:flex;align-items:center;justify-content:space-between;padding:10px 0;border-bottom:1px solid var(--border)}',
      '.wf-book-row small{display:block;font-size:.78em;color:var(--muted)}',
      '.wf-step{display:flex;align-items:center;gap:12px}',
      '.wf-step button{width:32px;height:32px;border-radius:50%;border:1px solid var(--border);background:var(--surface);',
      'color:var(--text);font:inherit;font-size:1.05em;line-height:1;cursor:pointer}',
      '.wf-step button:hover:not(:disabled){border-color:var(--text)}',
      '.wf-step button:disabled{opacity:.35;cursor:default}',
      '.wf-step output{min-width:18px;text-align:center;font-weight:600}',
      '.wf-book-lines{margin-top:14px;display:grid;gap:4px;font-size:.84em;color:var(--muted)}',
      '.wf-book-lines div{display:flex;justify-content:space-between;gap:10px}',
      '.wf-book-note{margin-top:8px;font-size:.8em;color:var(--muted)}',
      '.wf-book-total{display:flex;justify-content:space-between;margin-top:10px;padding-top:10px;border-top:1px solid var(--border);font-size:.95em}',
      '.wf-book-total strong{font-size:1.05em}',
      '.wf-book-btn{display:block;width:100%;height:46px;margin-top:14px;border:0;border-radius:calc(var(--radius) - 2px);',
      'background:var(--accent);color:var(--accent-text);font:inherit;font-weight:620;cursor:pointer}',
      '.wf-book-btn:focus-visible{outline:2px solid var(--accent);outline-offset:2px}',
      '.wf-book-msg{margin-top:10px;min-height:1.2em;font-size:.82em;color:var(--muted);text-align:center}',
      '.wf-book-msg[data-tone="warn"]{color:#b42318}',
      '.wf-book-none{margin-top:14px;font-size:.88em;color:var(--muted)}'
    ].join('');
  }

  // ------------------------------------------------------------- sections

  function section(el, title, children) {
    return el('section', { class: 'wf-d-sec' }, [title ? el('h3', { text: title }) : null].concat(children));
  }

  function gallery(el, x) {
    var images = x.images || [];
    if (!images.length) return null;
    return el('div', { class: 'wf-gal', 'data-n': String(images.length) }, images.map(function (src, i) {
      return el('img', {
        src: src,
        alt: x.title + (images.length > 1 ? ', photo ' + (i + 1) : ''),
        loading: i === 0 ? 'eager' : 'lazy',
        decoding: 'async'
      });
    }));
  }

  function meta(el, x) {
    return el('div', { class: 'wf-d-meta' }, [
      x.rating ? el('span', { class: 'wf-d-rate' }, [icon(ICON_STAR, 14), el('span', {
        text: x.rating + (x.reviews ? ' (' + x.reviews + ' reviews)' : '')
      })]) : null,
      x.location ? el('span', {}, [icon(ICON_PIN, 14), el('span', { text: x.location })]) : null,
      x.duration ? el('span', {}, [icon(ICON_CLOCK, 14), el('span', { text: x.duration })]) : null
    ]);
  }

  function about(el, x) {
    var paragraphs = (x.description || []).map(function (text) { return el('p', { text: text }); });
    var highlights = (x.highlights || []).length
      ? el('dl', { class: 'wf-d-hl' }, x.highlights.map(function (h) {
          return el('div', {}, [el('dt', { text: h.label }), el('dd', { text: h.value })]);
        }))
      : null;
    return section(el, 'About this experience', paragraphs.concat([highlights]));
  }

  function itinerary(el, x) {
    if (!(x.itinerary || []).length) return null;
    return section(el, 'What you\u2019ll do', [
      el('ol', { class: 'wf-d-steps' }, x.itinerary.map(function (stop) {
        return el('li', {}, [
          el('div', { class: 'wf-d-step-t', text: stop.title }, [
            stop.duration ? el('span', { class: 'wf-d-step-time', text: stop.duration }) : null
          ]),
          stop.description ? el('p', { text: stop.description }) : null
        ]);
      }))
    ]);
  }

  function meetingPoint(el, x) {
    var mp = x.meeting_point;
    if (!mp) return null;
    var children = [el('p', { text: mp.label })];

    if (mp.lat != null && mp.lng != null) {
      // OpenStreetMap embed: no API key. Partners with a strict CSP need
      // frame-src https://www.openstreetmap.org — the link below still works without it.
      var dLat = 0.012;
      var dLng = 0.02;
      var bbox = [mp.lng - dLng, mp.lat - dLat, mp.lng + dLng, mp.lat + dLat].map(function (n) { return n.toFixed(5); }).join(',');
      children.push(el('iframe', {
        class: 'wf-d-map',
        title: 'Map of the meeting point',
        loading: 'lazy',
        referrerpolicy: 'strict-origin-when-cross-origin',
        src: 'https://www.openstreetmap.org/export/embed.html?bbox=' + bbox +
          '&layer=mapnik&marker=' + mp.lat.toFixed(5) + ',' + mp.lng.toFixed(5)
      }));
      children.push(el('a', {
        class: 'wf-d-maplink',
        href: 'https://www.google.com/maps/search/?api=1&query=' + mp.lat + ',' + mp.lng,
        target: '_blank',
        rel: 'noopener noreferrer',
        text: 'Open in Google Maps'
      }));
    }

    return section(el, 'Where you\u2019ll meet', children);
  }

  function host(el, x) {
    var h = x.host;
    if (!h) return null;
    var avatar = el('div', { class: 'wf-d-avatar' }, [
      h.avatar ? el('img', { src: h.avatar, alt: '', loading: 'lazy' }) : el('span', { text: h.initials || '' })
    ]);
    return section(el, 'Meet your host', [
      el('div', { class: 'wf-d-host' }, [
        avatar,
        el('div', {}, [el('strong', { text: h.name }), h.bio ? el('p', { text: h.bio }) : null])
      ])
    ]);
  }

  function goodToKnow(el, x) {
    if (!(x.good_to_know || []).length) return null;
    return section(el, 'Things to keep in mind', [
      el('div', { class: 'wf-d-keep' }, x.good_to_know.map(function (row) {
        return el('div', {}, [el('strong', { text: row.title }), row.body ? el('p', { text: row.body }) : null]);
      }))
    ]);
  }

  // --------------------------------------------------------------- booking

  function bookingCard(el, x, config, api, onConfirm, restore) {
    var departures = x.departures || [];
    var childrenAllowed = x.children_allowed !== false;
    var state = { departure: null, adults: 1, children: 0 };

    var priceStrong = el('strong');
    var price = el('div', { class: 'wf-book-price' }, [priceStrong, el('span', { text: 'per adult' })]);
    var card = el('div', { class: 'wf-book' }, [price]);

    if (!departures.length) {
      priceStrong.textContent = api.money(x.price, x.currency);
      card.appendChild(el('p', { class: 'wf-book-none', text: 'No dates open right now. Check back soon.' }));
      return card;
    }

    var dateId = 'wf-date-' + x.id;
    var select = el('select', { id: dateId }, [el('option', { value: '', text: 'Select a date' })]);
    departures.forEach(function (d) {
      var soldOut = d.seats_left <= 0;
      var suffix = soldOut ? ' \u2014 sold out' : (d.seats_left <= 3 ? ' \u2014 ' + d.seats_left + ' left' : '');
      select.appendChild(el('option', { value: String(d.id), text: d.label + suffix, disabled: soldOut ? '' : null }));
    });

    function guests() { return state.adults + state.children; }

    function cap() {
      var limit = x.max_guests || 12;
      return state.departure ? Math.min(limit, state.departure.seats_left) : limit;
    }

    function adultPrice() { return state.departure ? state.departure.price : x.price; }
    function childPrice() {
      if (state.departure && state.departure.child_price != null) return state.departure.child_price;
      return x.child_price != null ? x.child_price : adultPrice();
    }
    function total() { return state.adults * adultPrice() + state.children * childPrice(); }

    function stepper(label, hint, key, min) {
      var value = el('output', { 'aria-live': 'polite' });
      var minus = el('button', { type: 'button', 'aria-label': 'Fewer ' + label.toLowerCase(), text: '\u2212' });
      var plus = el('button', { type: 'button', 'aria-label': 'More ' + label.toLowerCase(), text: '+' });
      minus.addEventListener('click', function () { if (state[key] > min) { state[key]--; update(); } });
      plus.addEventListener('click', function () { if (guests() < cap()) { state[key]++; update(); } });
      return {
        row: el('div', { class: 'wf-book-row' }, [
          el('div', {}, [el('span', { text: label }), el('small', { text: hint })]),
          el('div', { class: 'wf-step' }, [minus, value, plus])
        ]),
        sync: function () {
          value.textContent = String(state[key]);
          minus.disabled = state[key] <= min;
          plus.disabled = guests() >= cap();
        }
      };
    }

    var adultHint = childrenAllowed ? 'Age 13+' : (x.min_age ? 'Age ' + x.min_age + '+' : '');
    var adults = stepper('Adults', adultHint, 'adults', 1);
    var children = childrenAllowed
      ? stepper('Children', 'Age ' + Math.max(2, x.min_age || 2) + '\u201312', 'children', 0)
      : null;

    var breakdown = el('div', { class: 'wf-book-lines' });
    var totalValue = el('strong');
    var button = el('button', { class: 'wf-book-btn', type: 'button', text: 'Confirm booking' });
    var message = el('p', { class: 'wf-book-msg', role: 'status' });

    function line(label, amount) {
      return el('div', {}, [el('span', { text: label }), el('span', { text: api.money(amount, x.currency) })]);
    }

    function update() {
      // A smaller departure can shrink the cap below the current party size.
      while (guests() > cap() && state.children > 0) state.children--;
      while (guests() > cap() && state.adults > 1) state.adults--;

      adults.sync();
      if (children) children.sync();

      priceStrong.textContent = api.money(adultPrice(), x.currency);

      breakdown.innerHTML = '';
      breakdown.appendChild(line(
        state.adults + (state.adults === 1 ? ' adult' : ' adults') + ' \u00d7 ' + api.money(adultPrice(), x.currency),
        state.adults * adultPrice()
      ));
      if (state.children) {
        breakdown.appendChild(line(
          state.children + (state.children === 1 ? ' child' : ' children') + ' \u00d7 ' + api.money(childPrice(), x.currency),
          state.children * childPrice()
        ));
      }
      totalValue.textContent = api.money(total(), x.currency);
    }

    function pick(id) {
      state.departure = null;
      for (var i = 0; i < departures.length; i++) {
        if (departures[i].id === id && departures[i].seats_left > 0) state.departure = departures[i];
      }
      select.value = state.departure ? String(state.departure.id) : '';
    }

    select.addEventListener('change', function () {
      pick(Number(select.value));
      message.textContent = '';
      message.removeAttribute('data-tone');
      update();
    });

    button.addEventListener('click', function () {
      if (!state.departure) {
        message.textContent = 'Choose a date to continue.';
        message.setAttribute('data-tone', 'warn');
        select.focus();
        return;
      }

      var selection = {
        departure: state.departure,
        adults: state.adults,
        children: state.children,
        total: total()
      };

      // composed: true lets the partner page listen from outside the shadow root.
      button.dispatchEvent(new CustomEvent('weyfarin:checkout', {
        bubbles: true,
        composed: true,
        detail: {
          widget: config.key, experience: x.slug, departure_id: state.departure.id, date: state.departure.date,
          adults: state.adults, children: state.children, total: total(), currency: x.currency
        }
      }));

      button.disabled = true;
      button.textContent = 'Opening checkout\u2026';
      message.textContent = '';

      onConfirm(selection, function fail(text) {
        button.disabled = false;
        button.textContent = 'Confirm booking';
        message.textContent = text;
        message.setAttribute('data-tone', 'warn');
      });
    });

    card.appendChild(el('label', { class: 'wf-book-label', for: dateId, text: 'Date' }));
    card.appendChild(el('div', { class: 'wf-book-sel' }, [select, icon(ICON_CHEVRON)]));
    card.appendChild(el('span', { class: 'wf-book-label', text: 'Guests' }));
    card.appendChild(adults.row);
    if (children) card.appendChild(children.row);
    if (!childrenAllowed && x.min_age) {
      card.appendChild(el('p', { class: 'wf-book-note', text: 'For guests aged ' + x.min_age + ' and over.' }));
    }
    card.appendChild(breakdown);
    card.appendChild(el('div', { class: 'wf-book-total' }, [el('span', { text: 'Total' }), totalValue]));
    card.appendChild(button);
    card.appendChild(message);

    // Coming back from checkout: put the guest's choices back.
    if (restore) {
      pick(restore.departure ? restore.departure.id : 0);
      state.adults = Math.max(1, restore.adults || 1);
      state.children = childrenAllowed ? (restore.children || 0) : 0;
    }

    update();
    return card;
  }

  // ---------------------------------------------------------------- render

  function renderDetail(target, data, api, opts) {
    opts = opts || {};
    var el = api.el;
    var config = data.widget;
    var x = data.experience;
    var standalone = !opts.embedded;

    if (!x) {
      target.innerHTML = '';
      return;
    }

    function startCheckout(selection, fail) {
      api.load('booking')
        .then(function (book) {
          book(target, { widget: config, experience: x, selection: selection }, api, {
            embedded: opts.embedded,
            // Back from checkout: re-render this detail with the same choices.
            onBack: function () {
              renderDetail(target, { widget: config, experience: x, restore: selection }, api, opts);
            },
            // After a confirmed booking: back to the catalogue, if there is one.
            onClose: opts.onBack || null
          });
        })
        .catch(function (err) {
          api.warn(err.message);
          fail('Checkout could not be opened. Please try again.');
        });
    }

    var back = null;
    if (opts.onBack) {
      back = el('button', { class: 'wf-back', type: 'button' }, [icon(ICON_BACK), el('span', { text: 'Back to all experiences' })]);
      back.addEventListener('click', opts.onBack);
    }

    var main = el('div', { class: 'wf-d-main' }, [
      about(el, x), itinerary(el, x), meetingPoint(el, x), host(el, x), goodToKnow(el, x)
    ]);
    var aside = el('aside', { class: 'wf-d-aside', 'aria-label': 'Book this experience' }, [
      bookingCard(el, x, config, api, startCheckout, data.restore || null)
    ]);

    var body = el('article', { class: standalone ? 'wf wf-d' : 'wf-d', 'data-w': 'sm' }, [
      back,
      gallery(el, x),
      el('h2', { class: 'wf-d-title', text: x.title }),
      meta(el, x),
      el('div', { class: 'wf-d-layout' }, [main, aside]),
      standalone ? api.credit(config) : null
    ]);

    target.innerHTML = '';
    target.appendChild(el('style', { text: (standalone ? api.baseStyles(config.theme) : '') + styles() }));
    target.appendChild(body);

    if (standalone) {
      api.observeSize(target.host, body);
      if (!data.restore) api.track(config.track_url, 'detail', x.id);
    } else {
      body.removeAttribute('data-w');   // embedded: the search wrapper's .wf owns the breakpoint
    }
  }

  W.define('detail', renderDetail);
})();
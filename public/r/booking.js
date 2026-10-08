/*!
 * Weyfarin renderer: booking (checkout)
 *
 * Opened by r/detail.js on "Confirm booking". Flow:
 *   1. POST /bookings          → seats held for 10 min, PaymentIntent created
 *   2. Stripe Payment Element  → mounted in the LIGHT DOM, shown through a
 *                                <slot> (Stripe refuses shadow roots; Day 1 gate)
 *   3. POST /bookings/{ref}/guest → name, email, phone
 *   4. stripe.confirmPayment   → card / 3DS, never redirects (allow_redirects=never)
 *   5. GET  /bookings/{ref}    → poll until the server says "confirmed"
 *
 * The server computes every amount. This file only displays them.
 */
(function () {
  'use strict';

  var W = window.Weyfarin;
  if (!W || !W.define) return;

  var ICON_BACK = 'M15 18l-6-6 6-6';
  var ICON_LOCK = 'M7 11V8a5 5 0 0 1 10 0v3M5 11h14v10H5z';
  var ICON_CHECK = 'M20 6L9 17l-5-5';
  var ICON_CLOCK = 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18zM12 7v5l3 2';

  function icon(d, size) {
    var ns = 'http://www.w3.org/2000/svg';
    var svg = document.createElementNS(ns, 'svg');
    var path = document.createElementNS(ns, 'path');
    svg.setAttribute('viewBox', '0 0 24 24');
    svg.setAttribute('width', size || 16);
    svg.setAttribute('height', size || 16);
    svg.setAttribute('fill', 'none');
    svg.setAttribute('stroke', 'currentColor');
    svg.setAttribute('stroke-width', '2');
    svg.setAttribute('stroke-linecap', 'round');
    svg.setAttribute('stroke-linejoin', 'round');
    svg.setAttribute('aria-hidden', 'true');
    path.setAttribute('d', d);
    svg.appendChild(path);
    return svg;
  }

  // -------------------------------------------------------------- Stripe.js

  var stripeScript = null;
  var stripeInstances = {};

  function loadStripe(publishableKey) {
    if (!stripeScript) {
      stripeScript = window.Stripe ? Promise.resolve() : new Promise(function (resolve, reject) {
        var script = document.createElement('script');
        script.src = 'https://js.stripe.com/v3/';
        script.async = true;
        script.onload = function () { window.Stripe ? resolve() : reject(new Error('Stripe.js loaded but window.Stripe is missing')); };
        script.onerror = function () { stripeScript = null; reject(new Error('Could not load Stripe.js')); };
        document.head.appendChild(script);
      });
    }
    return stripeScript.then(function () {
      if (!stripeInstances[publishableKey]) stripeInstances[publishableKey] = window.Stripe(publishableKey);
      return stripeInstances[publishableKey];
    });
  }

  // ----------------------------------------------------------------- styles

  function styles() {
    return [
      '[hidden]{display:none!important}',
      '.wf-back{display:inline-flex;align-items:center;gap:4px;margin:0 0 14px -4px;padding:6px 4px;',
      'background:none;border:0;color:var(--muted);font:inherit;font-size:.88em;cursor:pointer}',
      '.wf-back:hover{color:var(--text)}',

      '.wf-k-head{display:flex;flex-wrap:wrap;align-items:baseline;justify-content:space-between;gap:8px 16px;margin-bottom:18px}',
      '.wf-k-head h2{font-size:1.45em;font-weight:680;letter-spacing:-.02em}',
      '.wf-k-timer{display:inline-flex;align-items:center;gap:6px;padding:5px 10px;border-radius:999px;',
      'background:var(--border);font-size:.82em;font-weight:560}',
      '.wf-k-timer[data-low]{background:#fde8e7;color:#b42318}',

      '.wf-k-layout{display:grid;gap:22px}',
      '.wf[data-w="lg"] .wf-k-layout{grid-template-columns:minmax(0,1fr) 340px;gap:40px}',
      '.wf[data-w="lg"] .wf-k-aside{position:sticky;top:16px;align-self:start;order:2}',

      '.wf-k-sec{padding:20px;border:1px solid var(--border);border-radius:var(--radius);background:var(--surface);margin-bottom:16px}',
      '.wf-k-sec h3{display:flex;align-items:center;gap:10px;font-size:1.02em;font-weight:650;margin-bottom:4px}',
      '.wf-k-sec h3 span{width:24px;height:24px;border-radius:50%;background:var(--accent);color:var(--accent-text);',
      'font-size:.75em;display:grid;place-items:center}',
      '.wf-k-sec > p{font-size:.85em;color:var(--muted);margin-bottom:14px}',

      '.wf-k-grid{display:grid;gap:12px}',
      '.wf[data-w="lg"] .wf-k-grid{grid-template-columns:1fr 1fr}',
      '.wf[data-w="lg"] .wf-k-grid .wf-k-full{grid-column:1/-1}',
      '.wf-k-field label{display:block;font-size:.8em;color:var(--muted);margin-bottom:5px}',
      '.wf-k-field input{display:block;width:100%;height:44px;padding:0 12px;border:1px solid var(--border);',
      'border-radius:calc(var(--radius) - 2px);background:var(--surface);color:var(--text);font:inherit;font-size:.95em}',
      '.wf-k-field input:focus{outline:2px solid var(--accent);outline-offset:1px}',
      '.wf-k-field[data-error] input{border-color:#b42318}',
      '.wf-k-err{display:block;min-height:1em;margin-top:4px;font-size:.78em;color:#b42318}',

      '.wf-k-pay{min-height:120px}',
      '.wf-k-loading{padding:28px 0;text-align:center;font-size:.88em;color:var(--muted)}',

      '.wf-k-btn{display:flex;align-items:center;justify-content:center;gap:8px;width:100%;height:50px;border:0;',
      'border-radius:calc(var(--radius) - 2px);background:var(--accent);color:var(--accent-text);font:inherit;font-weight:640;cursor:pointer}',
      '.wf-k-btn:disabled{opacity:.55;cursor:default}',
      '.wf-k-btn:focus-visible{outline:2px solid var(--accent);outline-offset:2px}',
      '.wf-k-msg{margin-top:10px;min-height:1.2em;font-size:.85em;color:#b42318;text-align:center}',
      '.wf-k-terms{margin-top:8px;font-size:.78em;color:var(--muted);text-align:center}',

      '.wf-k-sum{border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;background:var(--surface)}',
      '.wf-k-sum img{display:block;width:100%;aspect-ratio:16/9;object-fit:cover;background:var(--border);border:0}',
      '.wf-k-sum-body{padding:16px}',
      '.wf-k-sum-body h4{font-size:1em;font-weight:640;line-height:1.35}',
      '.wf-k-sum-date{margin-top:4px;font-size:.85em;color:var(--muted)}',
      '.wf-k-lines{margin-top:14px;padding-top:12px;border-top:1px solid var(--border);display:grid;gap:6px;font-size:.88em}',
      '.wf-k-lines div{display:flex;justify-content:space-between;gap:10px}',
      '.wf-k-lines span:first-child{color:var(--muted)}',
      '.wf-k-total{display:flex;justify-content:space-between;margin-top:12px;padding-top:12px;border-top:1px solid var(--border);font-weight:650}',

      '.wf-k-done{max-width:520px;margin:12px auto;text-align:center}',
      '.wf-k-done-icon{width:56px;height:56px;margin:0 auto 14px;border-radius:50%;background:var(--accent);',
      'color:var(--accent-text);display:grid;place-items:center}',
      '.wf-k-done h2{font-size:1.5em;font-weight:680;letter-spacing:-.02em}',
      '.wf-k-done > p{margin-top:6px;color:var(--muted)}',
      '.wf-k-ref{display:inline-block;margin:16px 0 18px;padding:8px 16px;border-radius:999px;border:1px dashed var(--border);',
      'font-weight:650;letter-spacing:.04em}',
      '.wf-k-done .wf-k-sum{text-align:left}',
      '.wf-k-again{margin-top:18px;padding:10px 20px;border:1px solid var(--border);border-radius:999px;background:var(--surface);',
      'color:var(--text);font:inherit;font-size:.9em;cursor:pointer}'
    ].join('');
  }

  // ----------------------------------------------------------------- render

  W.define('booking', function (target, data, api, opts) {
    opts = opts || {};
    var el = api.el;
    var config = data.widget;
    var x = data.experience;
    var selection = data.selection;
    var standalone = !opts.embedded;
    var host = target.host || target.getRootNode().host;
    var apiUrl = config.api_url || (api.origin + '/api/embed/' + config.key);

    var state = {
      booking: null,       // server response from POST /bookings
      stripe: null,
      elements: null,
      paymentElement: null,
      mountEl: null,
      guard: null,
      timer: null,
      expiresAt: 0,
      paying: false,
      finished: false,
      closed: false
    };

    // --- form ---------------------------------------------------------------

    function field(id, label, type, autocomplete, full) {
      var input = el('input', { id: id, type: type, autocomplete: autocomplete, required: '' });
      var error = el('span', { class: 'wf-k-err', 'aria-live': 'polite' });
      var wrap = el('div', { class: 'wf-k-field' + (full ? ' wf-k-full' : '') }, [
        el('label', { for: id, text: label }), input, error
      ]);
      return {
        wrap: wrap,
        input: input,
        set: function (message) {
          error.textContent = message || '';
          if (message) wrap.setAttribute('data-error', '');
          else wrap.removeAttribute('data-error');
        }
      };
    }

    var uid = Math.random().toString(36).slice(2, 8);
    var fName = field('wf-name-' + uid, 'Full name', 'text', 'name', true);
    var fEmail = field('wf-email-' + uid, 'Email', 'email', 'email', false);
    var fPhone = field('wf-phone-' + uid, 'Mobile number', 'tel', 'tel', false);
    fPhone.input.setAttribute('placeholder', '+91 98765 43210');

    var slotName = 'wf-pay-' + uid;
    var payArea = el('div', { class: 'wf-k-pay' }, [
      el('p', { class: 'wf-k-loading', text: 'Holding your seats\u2026' })
    ]);

    var payButton = el('button', { class: 'wf-k-btn', type: 'button', disabled: '' }, [
      icon(ICON_LOCK), el('span', { text: 'Preparing payment\u2026' })
    ]);
    var message = el('p', { class: 'wf-k-msg', role: 'alert' });
    var timerText = el('span');
    var timer = el('p', { class: 'wf-k-timer', hidden: '' }, [icon(ICON_CLOCK, 14), timerText]);

    function setButton(text, disabled) {
      payButton.lastChild.textContent = text;
      payButton.disabled = !!disabled;
    }

    // --- summary (filled from the server's numbers) -------------------------

    var sumLines = el('div', { class: 'wf-k-lines' });
    var sumTotal = el('strong');

    function summaryCard(source) {
      return el('div', { class: 'wf-k-sum' }, [
        source.image ? el('img', { src: source.image, alt: '', loading: 'lazy' }) : null,
        el('div', { class: 'wf-k-sum-body' }, [
          el('h4', { text: source.title }),
          el('p', { class: 'wf-k-sum-date', text: source.date || '' }),
          source.lines,
          el('div', { class: 'wf-k-total' }, [el('span', { text: 'Total' }), source.total])
        ])
      ]);
    }

    function fillLines(container, totalEl, payload) {
      container.innerHTML = '';
      (payload.lines || []).forEach(function (row) {
        container.appendChild(el('div', {}, [
          el('span', { text: row.label + ' \u00d7 ' + api.money(row.unit, payload.currency) }),
          el('span', { text: api.money(row.amount, payload.currency) })
        ]));
      });
      totalEl.textContent = api.money(payload.total, payload.currency);
    }

    // Provisional figures until the server answers.
    fillLines(sumLines, sumTotal, {
      currency: x.currency,
      total: selection.total,
      lines: [{ label: selection.adults + (selection.adults === 1 ? ' adult' : ' adults'), unit: selection.departure.price, amount: selection.adults * selection.departure.price }]
        .concat(selection.children ? [{ label: selection.children + (selection.children === 1 ? ' child' : ' children'), unit: selection.departure.child_price, amount: selection.children * selection.departure.child_price }] : [])
    });

    // --- layout -------------------------------------------------------------

    var back = el('button', { class: 'wf-back', type: 'button' }, [icon(ICON_BACK), el('span', { text: 'Back to experience' })]);

    var form = el('div', { class: 'wf-k-main' }, [
      el('section', { class: 'wf-k-sec' }, [
        el('h3', {}, [el('span', { text: '1' }), document.createTextNode('Your information')]),
        el('p', { text: 'We\u2019ll send your booking details here.' }),
        el('div', { class: 'wf-k-grid' }, [fName.wrap, fEmail.wrap, fPhone.wrap])
      ]),
      el('section', { class: 'wf-k-sec' }, [
        el('h3', {}, [el('span', { text: '2' }), document.createTextNode('Payment')]),
        el('p', { text: 'Card details go straight to Stripe and never touch this website.' }),
        payArea
      ]),
      payButton,
      message,
      el('p', { class: 'wf-k-terms', text: 'By completing this booking you agree to the cancellation policy shown on the experience.' })
    ]);

    var aside = el('aside', { class: 'wf-k-aside', 'aria-label': 'Booking summary' }, [
      summaryCard({ image: (x.images && x.images[0]) || x.image, title: x.title, date: selection.departure.label, lines: sumLines, total: sumTotal })
    ]);

    var body = el('article', { class: standalone ? 'wf wf-k' : 'wf-k', 'data-w': 'sm' }, [
      back,
      el('div', { class: 'wf-k-head' }, [el('h2', { text: 'Confirm and pay' }), timer]),
      el('div', { class: 'wf-k-layout' }, [aside, form])
    ]);

    target.innerHTML = '';
    target.appendChild(el('style', { text: (standalone ? api.baseStyles(config.theme) : '') + styles() }));
    target.appendChild(body);
    if (standalone) api.observeSize(host, body);
    else body.removeAttribute('data-w');

    var hostTop = host.getBoundingClientRect().top;
    if (hostTop < 0 || hostTop > window.innerHeight * 0.4) host.scrollIntoView({ block: 'start' });

    // --- cleanup ------------------------------------------------------------

    // Anything we put outside the shadow root must come back out: the Stripe
    // mount point in the host's light DOM and the CSS guard in <head>.
    function teardownStripe() {
      if (state.paymentElement) { try { state.paymentElement.destroy(); } catch (e) { /* already gone */ } }
      if (state.mountEl && state.mountEl.parentNode) state.mountEl.parentNode.removeChild(state.mountEl);
      if (state.guard && state.guard.parentNode) state.guard.parentNode.removeChild(state.guard);
      state.paymentElement = state.mountEl = state.guard = null;
    }

    function stopTimer() {
      clearInterval(state.timer);
      state.timer = null;
    }

    function releaseHold(useBeacon) {
      var b = state.booking;
      if (!b || state.finished || state.paying) return;
      var url = apiUrl + '/bookings/' + encodeURIComponent(b.reference) + '/release';
      var payload = JSON.stringify({ token: b.token });
      if (useBeacon && navigator.sendBeacon) {
        navigator.sendBeacon(url, new Blob([payload], { type: 'text/plain;charset=UTF-8' }));
      } else {
        api.postJson(url, { token: b.token }).catch(function () { /* the hold expires anyway */ });
      }
    }

    // Tab closed mid-checkout: give the seats back now, not in 10 minutes.
    function onPageHide() { releaseHold(true); }
    window.addEventListener('pagehide', onPageHide);

    function close() {
      if (state.closed) return;
      state.closed = true;
      stopTimer();
      teardownStripe();
      window.removeEventListener('pagehide', onPageHide);
    }

    // The search renderer calls this if the guest navigates away with the
    // browser's Back button while checkout is open.
    target.__wfCleanup = function () {
      releaseHold(false);
      close();
    };

    back.addEventListener('click', function () {
      releaseHold(false);
      close();
      target.__wfCleanup = null;
      if (opts.onBack) opts.onBack();
    });

    // --- hold timer -----------------------------------------------------------

    function startTimer(seconds) {
      state.expiresAt = Date.now() + seconds * 1000;
      timer.hidden = false;

      function tick() {
        var left = Math.max(0, Math.round((state.expiresAt - Date.now()) / 1000));
        var m = Math.floor(left / 60);
        var s = left % 60;
        timerText.textContent = 'Seats held for ' + m + ':' + (s < 10 ? '0' : '') + s;
        if (left <= 60) timer.setAttribute('data-low', '');

        if (left === 0 && !state.paying && !state.finished) {
          stopTimer();
          teardownStripe();
          payArea.innerHTML = '';
          payArea.appendChild(el('p', { class: 'wf-k-loading', text: 'Your seats were released after ' +
            Math.round(seconds / 60) + ' minutes. Go back to choose your date again.' }));
          setButton('Seats released', true);
        }
      }

      tick();
      state.timer = setInterval(tick, 1000);
    }

    // --- Stripe mount (slot pattern) -------------------------------------------

    function mountPayment(stripe, booking) {
      state.stripe = stripe;
      state.elements = stripe.elements({
        clientSecret: booking.client_secret,
        appearance: {
          theme: 'stripe',
          variables: {
            colorPrimary: config.theme.accent,
            borderRadius: config.theme.radius,
            fontFamily: 'system-ui, -apple-system, "Segoe UI", Roboto, sans-serif'
          }
        }
      });

      // Shadow side: where it should appear.
      payArea.innerHTML = '';
      payArea.appendChild(el('slot', { name: slotName }));

      // Light side: where Stripe actually mounts — a child of the host.
      var mountEl = document.createElement('div');
      mountEl.id = slotName;
      mountEl.setAttribute('slot', slotName);
      mountEl.style.cssText = 'display:block;margin:0;padding:0;border:0;max-width:none;line-height:normal';
      host.appendChild(mountEl);
      state.mountEl = mountEl;

      // Partner CSS can reach the iframe element through the slot. An ID
      // selector with !important outranks an element selector with !important.
      var guard = document.createElement('style');
      guard.setAttribute('data-weyfarin-guard', slotName);
      guard.textContent = '#' + slotName + ' iframe{filter:none!important;transform:none!important;' +
        'opacity:1!important;max-width:none!important;visibility:visible!important;clip-path:none!important}';
      document.head.appendChild(guard);
      state.guard = guard;

      state.paymentElement = state.elements.create('payment', {
        layout: 'tabs',
        // Collected in our own form above; passed on confirm.
        fields: { billingDetails: { name: 'never', email: 'never', phone: 'never' } }
      });

      state.paymentElement.on('ready', function () {
        setButton('Pay ' + api.money(booking.total, booking.currency), false);
      });
      state.paymentElement.on('loaderror', function (event) {
        message.textContent = 'The payment form could not load. Please refresh and try again.';
        api.warn(event.error ? event.error.message : 'Payment Element loaderror');
      });

      state.paymentElement.mount(mountEl);
    }

    // --- 1. create the booking ---------------------------------------------------

    api.postJson(apiUrl + '/bookings', {
      experience: x.slug,
      departure_id: selection.departure.id,
      adults: selection.adults,
      children: selection.children,
      idempotency_key: 'k' + Date.now().toString(36) + Math.random().toString(36).slice(2, 12)
    })
      .then(function (booking) {
        if (state.closed) { state.booking = booking; releaseHold(false); return; }
        state.booking = booking;
        fillLines(sumLines, sumTotal, booking);          // server figures replace provisional ones
        startTimer(booking.hold_seconds);
        return loadStripe(booking.publishable_key).then(function (stripe) {
          if (!state.closed) mountPayment(stripe, booking);
        });
      })
      .catch(function (err) {
        if (state.closed) return;
        api.warn(err.message);
        payArea.innerHTML = '';
        payArea.appendChild(el('p', { class: 'wf-k-loading', text:
          err.code === 'not_enough_seats' || err.code === 'departure_unavailable'
            ? err.message + ' Go back to pick another date.'
            : 'We could not start checkout. Please go back and try again.' }));
        setButton('Unavailable', true);
      });

    // --- 2–5. pay -----------------------------------------------------------------

    function validate() {
      var ok = true;
      var name = fName.input.value.trim();
      var email = fEmail.input.value.trim();
      var phone = fPhone.input.value.trim();

      fName.set(name.length >= 2 ? '' : 'Enter your full name.');
      fEmail.set(/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email) ? '' : 'Enter a valid email address.');
      fPhone.set(/^\+?[0-9 ()-]{7,20}$/.test(phone) ? '' : 'Enter a valid phone number.');

      [fName, fEmail, fPhone].some(function (f) {
        if (f.wrap.hasAttribute('data-error')) { ok = false; f.input.focus(); return true; }
        return false;
      });

      return ok ? { name: name, email: email, phone: phone } : null;
    }

    function wait(ms) { return new Promise(function (resolve) { setTimeout(resolve, ms); }); }

    // The webhook usually lands within a second; the status endpoint also
    // asks Stripe directly if it has not.
    function pollConfirmed(tries) {
      var b = state.booking;
      var url = apiUrl + '/bookings/' + encodeURIComponent(b.reference) + '?token=' + encodeURIComponent(b.token);
      return api.getJson(url).then(function (status) {
        if (status.status === 'confirmed' || status.status === 'refund_due' || tries <= 1) return status;
        return wait(1500).then(function () { return pollConfirmed(tries - 1); });
      });
    }

    payButton.addEventListener('click', function () {
      if (!state.booking || !state.elements || state.paying) return;
      message.textContent = '';

      var guest = validate();
      if (!guest) return;

      state.paying = true;
      setButton('Processing\u2026', true);

      var b = state.booking;

      api.postJson(apiUrl + '/bookings/' + encodeURIComponent(b.reference) + '/guest', {
        token: b.token, name: guest.name, email: guest.email, phone: guest.phone
      })
        .then(function () {
          return state.stripe.confirmPayment({
            elements: state.elements,
            confirmParams: {
              return_url: location.href,
              payment_method_data: { billing_details: { name: guest.name, email: guest.email, phone: guest.phone } }
            },
            redirect: 'if_required'
          });
        })
        .then(function (result) {
          if (result.error) {
            // Card declined etc. The hold and the PaymentIntent stay valid —
            // the guest can try another card.
            state.paying = false;
            message.textContent = result.error.message;
            setButton('Pay ' + api.money(b.total, b.currency), false);
            return;
          }
          setButton('Confirming\u2026', true);
          return pollConfirmed(12).then(function (status) {
            if (status.status === 'confirmed') return finish(status, guest);
            state.paying = false;
            if (status.status === 'refund_due') {
              message.textContent = 'Payment received, but the last seats were taken while you paid. ' +
                'You will be refunded in full — reference ' + b.reference + '.';
              return;
            }
            message.textContent = 'Payment received. We are still confirming your booking ' + b.reference +
              ' — you will get an email shortly.';
          });
        })
        .catch(function (err) {
          state.paying = false;
          api.warn(err.message);
          if (err.body && err.body.fields) {
            fName.set(err.body.fields.name);
            fEmail.set(err.body.fields.email);
            fPhone.set(err.body.fields.phone);
          }
          message.textContent = err.message || 'Something went wrong. Please try again.';
          setButton('Pay ' + api.money(b.total, b.currency), false);
        });
    });

    // --- thank you ---------------------------------------------------------------

    function finish(status, guest) {
      state.finished = true;
      close();
      target.__wfCleanup = null;

      // Partner conversion tracking, from outside the shadow root.
      host.dispatchEvent(new CustomEvent('weyfarin:booked', {
        bubbles: true,
        composed: true,
        detail: { widget: config.key, reference: status.reference, experience: x.slug, total: status.total, currency: status.currency }
      }));

      var lines = el('div', { class: 'wf-k-lines' });
      var total = el('strong');
      fillLines(lines, total, status);

      var again = el('button', { class: 'wf-k-again', type: 'button', text: opts.onClose ? 'Explore more experiences' : 'Book another date' });
      again.addEventListener('click', function () {
        if (opts.onClose) opts.onClose();
        else if (opts.onBack) opts.onBack();
      });

      var done = el('div', { class: 'wf-k-done', role: 'status' }, [
        el('div', { class: 'wf-k-done-icon' }, [icon(ICON_CHECK, 28)]),
        el('h2', { text: 'Thank you!' }),
        el('p', { text: 'Your booking is confirmed. Booked for ' + guest.name + ' (' + guest.email + ').' }),
        el('div', { class: 'wf-k-ref', text: 'Booking ID: ' + status.reference }),
        summaryCard({
          image: status.experience && status.experience.image,
          title: (status.experience && status.experience.title) || x.title,
          date: status.date_label,
          lines: lines,
          total: total
        }),
        again
      ]);

      body.innerHTML = '';
      body.appendChild(done);
      host.scrollIntoView({ block: 'start' });
    }
  });
})();
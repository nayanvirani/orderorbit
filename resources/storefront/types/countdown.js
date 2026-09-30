/* OrderOrbit Space · countdown. Counts down to one real campaign deadline or a daily cutoff
   (e.g. same-day shipping) in the store's time zone; never resets per visitor. Eight layouts. */
(function () {
  var h = OrderOrbit.h;
  var CLOCK = '<circle cx="12" cy="13" r="8"/><path d="M12 9v4l2.5 2.5M9 2h6"/>';
  var TRUCK = '<path d="M3 7h11v9H3zM14 10h4l3 3v3h-7z"/><circle cx="7" cy="18" r="1.6"/><circle cx="17" cy="18" r="1.6"/>';
  var NAMES = { full: ['Days', 'Hours', 'Min', 'Sec'], short: ['d', 'h', 'm', 's'], none: ['', '', '', ''] };

  // Seconds-accurate next cutoff at "HH:MM" in the store's time zone.
  function nextCutoff(hhmm, tz) {
    var m = String(hhmm || '').match(/^(\d{1,2}):(\d{2})$/);
    if (!m) return 0;
    var now = new Date();
    var p = {};
    try {
      new Intl.DateTimeFormat('en-US', { timeZone: tz || undefined, hour12: false, hour: '2-digit', minute: '2-digit', second: '2-digit' })
        .formatToParts(now).forEach(function (x) { p[x.type] = x.value; });
    } catch (e) { p = { hour: now.getHours(), minute: now.getMinutes(), second: now.getSeconds() }; }
    var diff = (Number(m[1]) * 3600 + Number(m[2]) * 60) - ((Number(p.hour) % 24) * 3600 + Number(p.minute) * 60 + Number(p.second));
    return now.getTime() + (diff <= 0 ? diff + 86400 : diff) * 1000;
  }

  function safeUrl(url) {
    url = String(url || '').trim();
    return /^(\/(?!\/)|https?:\/\/)/.test(url) ? url : '/collections/all';
  }

  // The ticking element. kind: boxes | plain | flip | rings | inline.
  function timer(c, end, kind, daily) {
    var noDays = c.show_days === false || daily;
    var names = (NAMES[c.labels] || NAMES.full).slice(noDays ? 1 : 0);
    var attrs = ' data-oo-end="' + end + '"' + (noDays ? ' data-oo-nodays' : '') + (daily ? ' data-oo-daily' : '') +
      (Number(c.urgency_hours) > 0 ? ' data-oo-urgent="' + Number(c.urgency_hours) * 3600 + '"' : '') + (c.ended === 'hide' && !daily ? ' data-oo-hide-ended' : '');
    if (kind === 'inline') {
      var shortNames = NAMES.short.slice(noDays ? 1 : 0);
      return '<span class="oo-timer oo-cd-inline" role="timer"' + attrs + '>' + shortNames.map(function (n) { return '<span class="oo-u"><b>--</b>' + n + '</span>'; }).join(' ') + '</span>';
    }
    var sep = kind === 'flip' || kind === 'plain' ? '<i class="oo-sep" aria-hidden="true">:</i>' : '';
    return '<div class="oo-timer oo-cd-' + kind + '" role="timer" aria-live="off"' + attrs + '>' + names.map(function (n, i) {
      var ring = kind === 'rings' ? '<svg viewBox="0 0 40 40" aria-hidden="true"><circle class="oo-rt" cx="20" cy="20" r="17"/><circle class="oo-rp" cx="20" cy="20" r="17" pathLength="100"/></svg>' : '';
      return (i ? sep : '') + '<span class="oo-u">' + ring + '<b>--</b>' + (n ? '<small>' + n + '</small>' : '') + '</span>';
    }).join('') + '</div>';
  }

  function cta(c) {
    return c.cta_text ? '<a class="oo-btn oo-cd-cta" data-oo-click="countdown_cta" href="' + h.esc(safeUrl(c.cta_url)) + '">' + h.esc(c.cta_text) + '</a>' : '';
  }

  function code(c) {
    return c.code ? '<button type="button" class="oo-cd-code" data-oo-copy="' + h.esc(c.code) + '" data-oo-click="code_copied"><span>' + h.esc(c.code) + '</span><em>Copy</em></button>' : '';
  }

  function text(c, cls) {
    return '<div class="' + cls + '"><p class="oo-title">' + h.esc(c.headline) + '</p>' + (c.subheadline ? '<p class="oo-sub">' + h.esc(c.subheadline) + '</p>' : '') + '</div>';
  }

  OrderOrbit.define('countdown', function (exp, ctx) {
    var c = exp.content;
    var daily = c.mode === 'daily';
    var end = daily ? nextCutoff(c.daily_time, c.tz) : Date.parse(c.ends_at || exp.ends_at || '');
    // The builder previews a sample deadline until one is set.
    if (!end && ctx.preview) end = Date.now() + ((2 * 24 + 14) * 3600 + 9 * 60) * 1000;
    if (!end || end <= (ctx.now || Date.now())) {
      return c.ended === 'message' ? '<div class="oo-body oo-cd-ended"><p class="oo-message">' + h.esc(c.ended_message) + '</p></div>' : null;
    }

    switch (exp.style) {
      case 'minimal':
        return '<div class="oo-body oo-cd oo-cd-l-minimal">' + h.icon(CLOCK) + '<span class="oo-title">' + h.esc(c.headline) + '</span> ' + timer(c, end, 'inline', daily) + '</div>';
      case 'compact':
        return '<div class="oo-body oo-cd oo-cd-l-compact"><span class="oo-cd-badge">' + h.icon(CLOCK) + h.esc(c.headline) + '</span>' + timer(c, end, 'plain', daily) + '</div>';
      case 'banner':
        return '<div class="oo-body oo-cd oo-cd-l-banner">' + text(c, 'oo-cd-text') + timer(c, end, 'boxes', daily) + cta(c) + '</div>';
      case 'premium':
        return '<div class="oo-body oo-cd oo-cd-l-premium">' + (c.subheadline ? '<p class="oo-cd-eyebrow">' + h.esc(c.subheadline) + '</p>' : '') + '<p class="oo-title">' + h.esc(c.headline) + '</p>' + timer(c, end, 'plain', daily) + cta(c) + '</div>';
      case 'card':
        return '<div class="oo-body oo-cd oo-cd-l-card">' + text(c, 'oo-cd-text') + code(c) + timer(c, end, 'boxes', daily) + cta(c) + '</div>';
      case 'flip':
        return '<div class="oo-body oo-cd oo-cd-l-flip"><p class="oo-title">' + h.esc(c.headline) + '</p>' + timer(c, end, 'flip', daily) + (c.subheadline ? '<p class="oo-sub">' + h.esc(c.subheadline) + '</p>' : '') + cta(c) + '</div>';
      case 'circles':
        return '<div class="oo-body oo-cd oo-cd-l-circles"><p class="oo-title">' + h.esc(c.headline) + '</p>' + timer(c, end, 'rings', daily) + (c.subheadline ? '<p class="oo-sub">' + h.esc(c.subheadline) + '</p>' : '') + cta(c) + '</div>';
      case 'cutoff':
        var parts = h.esc(c.headline).split('{time}');
        return '<div class="oo-body oo-cd oo-cd-l-cutoff">' + h.icon(TRUCK) + '<p class="oo-title">' + parts[0] + (parts.length > 1 ? timer(c, end, 'inline', daily) + parts.slice(1).join('') : ' ' + timer(c, end, 'inline', daily)) + '</p></div>';
      default:
        return '<div class="oo-body oo-cd oo-cd-l-card">' + text(c, 'oo-cd-text') + timer(c, end, 'boxes', daily) + cta(c) + '</div>';
    }
  }, {
    setup: function (root) {
      if (!root) return;
      root.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-oo-copy]');
        if (!btn) return;
        var done = function () { btn.classList.add('oo-copied'); btn.querySelector('em').textContent = 'Copied'; };
        try { navigator.clipboard.writeText(btn.getAttribute('data-oo-copy')).then(done, done); } catch (err) { done(); }
      });
    }
  });
})();

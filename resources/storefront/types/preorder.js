/* Growvia · pre-order. Shows on the products the merchant picked, with the ship date,
   a progress bar (time to shipping or units toward a goal) and the time left in months, weeks or
   days. On the storefront it can relabel the theme's add-to-cart and adds a "Pre-order" line
   property to the theme's own form, so the cart and order show it. */
(function () {
  var h = OrderOrbit.h;
  var DAY = 864e5;
  var CAL = '<rect x="3.5" y="5" width="17" height="15.5" rx="2"/><path d="M3.5 10h17M8 3v4M16 3v4"/>';
  var CHECK = '<path d="m5 12 5 5 9-10"/>';

  function lang() { return document.documentElement.lang || undefined; }

  function shipDate(c, now) {
    if (c.ship_mode === 'relative') return new Date(now + (Number(c.ship_days) || 21) * DAY);
    var d = c.ship_date ? new Date(c.ship_date) : null;
    return d && !isNaN(d) ? d : null;
  }

  function fmtDate(d) {
    try { return d.toLocaleDateString(lang(), { month: 'long', day: 'numeric', year: d.getFullYear() !== new Date().getFullYear() ? 'numeric' : undefined }); } catch (e) { return d.toDateString(); }
  }

  function addMonths(d, n) { var x = new Date(d); x.setMonth(x.getMonth() + n); return x; }

  function parts(c, now, ship) {
    var total = Math.max(0, Math.ceil((ship - now) / DAY));
    var mode = c.breakdown || 'auto';
    if (mode === 'auto') mode = total >= 60 ? 'months' : total >= 14 ? 'weeks' : 'days';
    var m = 0, rest = total;
    if (mode === 'months') {
      while (addMonths(now, m + 1) <= ship) m++;
      rest = Math.max(0, Math.ceil((ship - addMonths(now, m)) / DAY));
    }
    return { mode: mode, months: m, weeks: mode === 'days' ? 0 : Math.floor(rest / 7), days: mode === 'days' ? rest : rest % 7 };
  }

  function unit(n, u) {
    try { return new Intl.NumberFormat(lang(), { style: 'unit', unit: u, unitDisplay: 'long' }).format(n); } catch (e) { return n + ' ' + u + (n === 1 ? '' : 's'); }
  }

  function unitWord(n, u) {
    try {
      return new Intl.NumberFormat(lang(), { style: 'unit', unit: u, unitDisplay: 'long' }).formatToParts(n)
        .filter(function (p) { return p.type === 'unit'; }).map(function (p) { return p.value; }).join('');
    } catch (e) { return u + (n === 1 ? '' : 's'); }
  }

  function timeText(p) {
    var out = [];
    if (p.months) out.push(unit(p.months, 'month'));
    if (p.weeks) out.push(unit(p.weeks, 'week'));
    if (p.days || !out.length) out.push(unit(p.days, 'day'));
    return out.join(' ');
  }

  function tiles(p) {
    var list = p.mode === 'months' ? [[p.months, 'month'], [p.weeks, 'week'], [p.days, 'day']] : p.mode === 'weeks' ? [[p.weeks, 'week'], [p.days, 'day']] : [[p.days, 'day']];
    return '<div class="oo-po-tiles">' + list.map(function (t) {
      return '<div class="oo-po-tile"><b>' + t[0] + '</b><span>' + h.esc(unitWord(t[0], t[1])) + '</span></div>';
    }).join('') + '</div>';
  }

  function bar(pct, label, right) {
    pct = Math.max(0, Math.min(100, Math.round(pct)));
    return '<div class="oo-po-bar"><div class="oo-po-bar-top"><span>' + label + '</span><b>' + (right || pct + '%') + '</b></div>' +
      '<div class="oo-progress" role="progressbar" aria-valuenow="' + pct + '" aria-valuemin="0" aria-valuemax="100"><i style="width:' + pct + '%"></i></div></div>';
  }

  function progress(c, now, ship) {
    if (c.progress === 'goal') {
      var target = Math.max(1, Number(c.goal_target) || 1), current = Math.max(0, Number(c.goal_current) || 0);
      return bar(current / target * 100, h.fill(c.goal_label || '{current} of {target} reserved', { current: current.toLocaleString(lang()), target: target.toLocaleString(lang()) }));
    }
    if (c.progress !== 'time' || c.ship_mode === 'relative') return '';
    var start = c.start_date ? Date.parse(c.start_date) : ship - 30 * DAY;
    return bar((now - start) / Math.max(DAY, ship - start) * 100, h.esc(c.progress_label || ''));
  }

  function shipText(c, ship) {
    return c.ship_mode === 'relative' ? 'Ships within ' + (Number(c.ship_days) || 21) + ' days' : 'Ships by ' + fmtDate(ship);
  }

  OrderOrbit.define('preorder', function (exp, ctx) {
    var c = exp.content;
    var now = Date.now();
    if (!ctx.preview) {
      var ids = (c.products || []).map(function (p) { return h.numericId(p.id); });
      if (!ctx.product || ids.indexOf(String(ctx.product)) === -1) return null;
    }
    var ship = shipDate(c, now) || (ctx.preview ? new Date(now + 52 * DAY) : null);
    if (!ship || (ship <= now && !ctx.preview)) return null;

    var p = parts(c, now, ship);
    var style = exp.style || 'card';
    var date = fmtDate(ship);
    var badge = c.badge_text ? '<span class="oo-po-badge">' + h.esc(c.badge_text) + '</span>' : '';
    var title = c.headline ? '<p class="oo-title">' + h.esc(c.headline) + '</p>' : '';
    var msg = c.message ? '<p class="oo-po-msg">' + h.fill(c.message, { date: date, time: timeText(p) }) + '</p>' : '';
    var note = c.note ? '<p class="oo-po-note">' + h.esc(c.note) + '</p>' : '';
    var prog = progress(c, now, ship);
    var inner;

    if (style === 'minimal') {
      inner = '<p class="oo-po-line">' + badge + '<span>' + (c.message ? h.fill(c.message, { date: date, time: timeText(p) }) : h.esc(c.headline)) + '</span></p>' + prog;
    } else if (style === 'pill') {
      inner = '<p class="oo-po-pillrow">' + badge + '<span>' + h.icon(CAL) + h.esc(shipText(c, ship)) + '</span><em>' + h.esc(timeText(p)) + '</em></p>' + prog + note;
    } else if (style === 'banner') {
      inner = '<div class="oo-po-bn">' + h.icon(CAL) + '<div>' + title + msg + '</div></div>' + prog;
    } else if (style === 'timeline') {
      var pct = c.ship_mode === 'relative' ? 15 : Math.max(8, Math.min(92, (now - (c.start_date ? Date.parse(c.start_date) : ship - 30 * DAY)) / Math.max(DAY, ship - (c.start_date ? Date.parse(c.start_date) : ship - 30 * DAY)) * 100));
      inner = badge + title +
        '<ol class="oo-po-steps" style="--po:' + (Math.round(pct) / 100) + '"><li class="oo-done"><i>' + h.icon(CHECK) + '</i><b>Order today</b><span>Reserve yours</span></li>' +
        '<li class="oo-now"><i></i><b>In production</b><span>' + h.esc(timeText(p)) + ' left</span></li>' +
        '<li><i></i><b>Ships</b><span>' + h.esc(date) + '</span></li></ol>' + msg + note;
    } else if (style === 'tiles') {
      inner = '<div class="oo-po-head">' + badge + title + '</div>' + tiles(p) + msg + prog + note;
    } else if (style === 'goal') {
      inner = '<div class="oo-po-head">' + badge + title + '</div>' + prog + msg + note;
    } else if (style === 'premium') {
      inner = '<div class="oo-po-head">' + badge + '<span class="oo-po-date">' + h.icon(CAL) + h.esc(date) + '</span></div>' + title + tiles(p) + prog + msg + note;
    } else {
      inner = '<div class="oo-po-head">' + badge + '<span class="oo-po-date">' + h.icon(CAL) + h.esc(date) + '</span></div>' + title + msg + prog + note;
    }
    return '<div class="oo-body oo-po oo-po-l-' + style + '">' + inner + '</div>';
  }, {
    setup: function (root, exp, ctx) {
      if (!root || ctx.preview) return;
      var c = exp.content;
      var form = document.querySelector('form[action*="/cart/add"]:not([data-oo-form])');
      var block = root.closest('.oo-root');
      if (!form || !block) return;
      var ship = shipDate(c, Date.now());
      var btn = form.querySelector('[type="submit"], button[name="add"]');
      var label = btn && (btn.querySelector('span:not([class*="loading"])') || btn);
      var original = label ? label.textContent : '';
      var input = null;
      var busy = false;
      var last = null;

      function variant() { var i = form.querySelector('[name="id"]'); return String(i ? i.value : ctx.variant || ''); }
      function active() { return c.show_when !== 'sold_out' || (ctx.soldOut || {})[variant()] === true; }

      function apply() {
        if (busy) return;
        busy = true;
        var on = active();
        block.hidden = !on;
        if (c.add_property !== false) {
          if (!input) {
            input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'properties[' + (c.property_name || 'Pre-order') + ']';
            input.value = shipText(c, ship);
            form.appendChild(input);
          }
          input.disabled = !on;
        }
        if (c.change_button !== false && label && c.button_text) {
          var text = label.textContent.trim();
          if (on && text !== c.button_text) { original = text; label.textContent = c.button_text; }
          if (!on && text === c.button_text) label.textContent = original;
        }
        last = variant();
        busy = false;
      }

      apply();
      // Themes rewrite the button and switch variants in their own ways: follow along.
      if (btn && 'MutationObserver' in window) new MutationObserver(apply).observe(btn, { childList: true, subtree: true, characterData: true });
      form.addEventListener('change', function () { setTimeout(apply, 60); });
      setInterval(function () { if (variant() !== last) apply(); }, 800);
    }
  });
})();

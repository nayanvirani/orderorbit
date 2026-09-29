/* OrderOrbit · countdown. Counts down to one real campaign deadline; never resets per visitor. */
OrderOrbit.define('countdown', function (exp, ctx, h) {
  var c = exp.content;
  var end = Date.parse(c.ends_at || exp.ends_at || '');
  // The builder previews a sample deadline until one is set.
  if (!end && ctx.preview) end = Date.now() + ((2 * 24 + 14) * 3600 + 9 * 60) * 1000;
  if (!end || end <= (ctx.now || Date.now())) {
    return c.ended === 'message' ? '<div class="oo-body"><p class="oo-message">' + h.esc(c.ended_message) + '</p></div>' : null;
  }
  return '<div class="oo-body oo-countdown-body"><div><p class="oo-title">' + h.esc(c.headline) + '</p>' + (c.subheadline ? '<p class="oo-sub">' + h.esc(c.subheadline) + '</p>' : '') + '</div>' +
    '<div class="oo-timer" data-oo-end="' + end + '" role="timer" aria-live="off">' +
    ['Days', 'Hours', 'Min', 'Sec'].map(function (l) { return '<span><b>--</b><small>' + l + '</small></span>'; }).join('') + '</div></div>';
});

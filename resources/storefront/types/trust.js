/* OrderOrbit · trust & social proof */
(function () {
  var ICONS = {
    shipping: '<path d="M3 7h11v9H3zM14 10h4l3 3v3h-7z"/><circle cx="7" cy="18" r="1.6"/><circle cx="17" cy="18" r="1.6"/>',
    returns: '<path d="M4 12a8 8 0 1 0 3-6.2"/><path d="M4 4v4h4"/>',
    secure: '<rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/>',
    guarantee: '<path d="M12 3l7 3v5c0 5-3.5 8-7 10-3.5-2-7-5-7-10V6z"/><path d="m9 12 2 2 4-4"/>',
    support: '<path d="M4 13v-1a8 8 0 0 1 16 0v1"/><rect x="3" y="13" width="4" height="6" rx="1.5"/><rect x="17" y="13" width="4" height="6" rx="1.5"/>'
  };

  function stars(h, rating) {
    var full = Math.round(Number(rating) || 0);
    var out = '';
    for (var i = 1; i <= 5; i++) out += '<span class="oo-star' + (i <= full ? ' oo-on' : '') + '">★</span>';
    return '<span class="oo-stars" aria-label="' + h.esc(rating) + ' out of 5">' + out + '</span>';
  }

  OrderOrbit.define('trust', function (exp, ctx, h) {
    var c = exp.content;
    var style = exp.style;
    var rating = c.rating ? '<div class="oo-rating">' + stars(h, c.rating) + ' <span>' + h.esc(c.rating) + (c.review_count ? ' · ' + h.esc(Number(c.review_count).toLocaleString()) + ' reviews' : '') + '</span></div>' : '';
    if (style === 'compact') return '<div class="oo-body oo-inline">' + rating + '</div>';
    var badges = (c.badges || []).length ? '<ul class="oo-badges">' + c.badges.map(function (b) { return '<li>' + h.icon(ICONS[b.icon] || ICONS.guarantee) + '<span>' + h.esc(b.label) + '</span></li>'; }).join('') + '</ul>' : '';
    if (style === 'row') return '<div class="oo-body">' + badges + '</div>';
    var reviews = (c.reviews || []).map(function (r) {
      return '<figure class="oo-review">' + stars(h, r.rating) + '<blockquote>' + h.esc(r.quote) + '</blockquote><figcaption><span class="oo-avatar" aria-hidden="true">' + h.esc(String(r.author || '?').charAt(0)) + '</span>' + h.esc(r.author) + '</figcaption></figure>';
    });
    if (style === 'premium') reviews = reviews.slice(0, 1);
    var guarantee = c.guarantee ? '<div class="oo-guarantee">' + h.icon(ICONS.guarantee) + '<p>' + h.esc(c.guarantee) + '</p></div>' : '';
    return '<div class="oo-body">' + (c.headline ? '<p class="oo-title">' + h.esc(c.headline) + '</p>' : '') + rating +
      (style === 'banner' ? guarantee || badges : (reviews.length ? '<div class="oo-reviews">' + reviews.join('') + '</div>' : '') + badges + guarantee) + '</div>';
  });
})();

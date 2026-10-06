/* Template gallery: draws each template with the storefront runtime and the checkout previews,
   the same code the app and the storefront use, as cards scroll into view. */
(() => {
    const grid = document.querySelector('[data-template-gallery]');
    if (!grid) return;
    const load = (src) => new Promise((resolve) => {
        const s = Object.assign(document.createElement('script'), { src, onload: resolve, onerror: resolve });
        document.head.appendChild(s);
    });
    const ctx = { preview: true, currency: 'USD', cartTotal: 4500, productPrice: 2900, productTitle: 'Sample product', page: 'product' };
    Promise.all([
        fetch(grid.dataset.templateGallery).then((r) => r.json()),
        load('/storefront/orderorbit.js').then(() => load(window.OO_PREVIEW_JS || '/js/checkout-preview.js')),
    ]).then(([previews]) => {
        const draw = (el) => {
            const exp = previews[el.dataset.tpl];
            if (!exp || !window.OrderOrbit) return;
            Promise.resolve(window.OrderOrbit.render(el, exp, ctx)).catch(() => {});
        };
        const cells = [...grid.querySelectorAll('[data-tpl]')];
        if (!('IntersectionObserver' in window)) return cells.forEach(draw);
        const io = new IntersectionObserver((entries) => entries.forEach((e) => {
            if (e.isIntersecting) { io.unobserve(e.target); draw(e.target); }
        }), { rootMargin: '400px 0px' });
        cells.forEach((el) => io.observe(el));
    });
})();

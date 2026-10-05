(() => {
    const $ = (s, el = document) => el.querySelector(s);
    const $$ = (s, el = document) => [...el.querySelectorAll(s)];

    // Marketing events (section 12). Pushed to dataLayer for whichever analytics tool is attached.
    window.dataLayer = window.dataLayer || [];
    const track = (event, props = {}) => window.dataLayer.push({ event, ...props, page: location.pathname });
    track('page_view');
    document.addEventListener('click', (e) => {
        const el = e.target.closest('[data-event]');
        if (el) track(el.dataset.event, { label: el.textContent.trim().slice(0, 80) });
    });

    // Header shadow on scroll
    const header = $('[data-header]');
    const onScroll = () => header && header.classList.toggle('scrolled', scrollY > 8);
    addEventListener('scroll', onScroll, { passive: true });
    onScroll();

    // Desktop dropdowns: hover with intent delay, click to toggle, Esc/outside to close
    $$('[data-dropdown]').forEach((item) => {
        const btn = $('button', item);
        let t;
        let hoveredAt = 0;
        const open = (v) => { item.classList.toggle('open', v); btn.setAttribute('aria-expanded', v); };
        item.addEventListener('mouseenter', () => { clearTimeout(t); $$('[data-dropdown].open').forEach((o) => o !== item && o.classList.remove('open')); if (!item.classList.contains('open')) hoveredAt = Date.now(); open(true); });
        item.addEventListener('mouseleave', () => { t = setTimeout(() => open(false), 160); });
        // A click that follows the hover (or a tap, which fires both) keeps the menu open instead of closing it.
        btn.addEventListener('click', () => open(Date.now() - hoveredAt < 600 || !item.classList.contains('open')));
    });
    document.addEventListener('click', (e) => { if (!e.target.closest('[data-dropdown]')) $$('[data-dropdown].open').forEach((i) => i.classList.remove('open')); });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') { $$('[data-dropdown].open').forEach((i) => i.classList.remove('open')); closeModal(); } });

    // Mobile menu
    const toggle = $('[data-menu-toggle]');
    const menu = $('[data-mobile-menu]');
    toggle && toggle.addEventListener('click', () => {
        const open = !menu.classList.contains('open');
        menu.classList.toggle('open', open);
        toggle.setAttribute('aria-expanded', open);
        document.body.style.overflow = open ? 'hidden' : '';
    });

    // Tabs
    $$('[data-tabs]').forEach((root) => {
        const tabs = $$('[role="tab"]', root);
        tabs.forEach((tab) => tab.addEventListener('click', () => {
            tabs.forEach((t) => t.setAttribute('aria-selected', t === tab));
            $$('[role="tabpanel"]', root).forEach((p) => { p.hidden = p.id !== tab.getAttribute('aria-controls'); });
        }));
    });

    // Reveal on scroll
    const io = 'IntersectionObserver' in window ? new IntersectionObserver((entries) => {
        entries.forEach((en) => { if (en.isIntersecting) { en.target.classList.add('in'); io.unobserve(en.target); } });
    }, { rootMargin: '0px 0px -60px 0px' }) : null;
    $$('.reveal').forEach((el) => (io ? io.observe(el) : el.classList.add('in')));

    // Live countdown demo: counts down to a fixed demo deadline and loops
    const timers = $$('[data-countdown]');
    if (timers.length) {
        const end = Date.now() + ((2 * 24 + 14) * 3600 + 9 * 60 + 41) * 1000;
        const pad = (n) => String(n).padStart(2, '0');
        const tick = () => {
            let s = Math.max(0, Math.floor((end - Date.now()) / 1000));
            const d = Math.floor(s / 86400); s %= 86400;
            const h = Math.floor(s / 3600); s %= 3600;
            const m = Math.floor(s / 60); s %= 60;
            timers.forEach((t) => {
                const parts = $$('b', t);
                [d, h, m, s].forEach((v, i) => parts[i] && (parts[i].textContent = pad(v)));
            });
        };
        tick();
        setInterval(tick, 1000);
    }

    // Template gallery: filters, deep link (?type=), modal preview
    const gallery = $('[data-gallery]');
    if (gallery) {
        const state = { surface: 'all', type: new URLSearchParams(location.search).get('type') || 'all' };
        const cards = $$('.tpl', gallery);
        const empty = $('[data-empty]');
        const apply = () => {
            let shown = 0;
            cards.forEach((c) => {
                const ok = (state.surface === 'all' || c.dataset.surface === state.surface) && (state.type === 'all' || c.dataset.type === state.type);
                c.classList.toggle('hide', !ok);
                shown += ok;
            });
            empty.hidden = shown > 0;
            $$('[data-filter]').forEach((b) => b.setAttribute('aria-selected', state[b.dataset.filter] === b.dataset.value));
        };
        $$('[data-filter]').forEach((b) => b.addEventListener('click', () => { state[b.dataset.filter] = b.dataset.value; apply(); }));
        $$('[data-clear]').forEach((b) => b.addEventListener('click', () => { state.surface = 'all'; state.type = 'all'; apply(); }));
        apply();

        cards.forEach((c) => c.addEventListener('click', () => {
            const modal = $('[data-modal]');
            $('[data-modal-title]', modal).textContent = c.dataset.name;
            $('[data-modal-sub]', modal).textContent = c.dataset.label;
            $$('[data-preview]', modal).forEach((p) => { p.hidden = p.dataset.preview !== c.dataset.feature; });
            $('[data-modal-link]', modal).href = '/features/' + c.dataset.feature;
            modal.classList.add('open');
            document.body.style.overflow = 'hidden';
            track('template_previewed', { template: c.dataset.name, type: c.dataset.type });
        }));
        $$('[data-device]').forEach((b) => b.addEventListener('click', () => {
            $$('[data-device]').forEach((x) => x.setAttribute('aria-pressed', x === b));
            $('[data-frame]').classList.toggle('mobile', b.dataset.device === 'mobile');
        }));
    }
    function closeModal() {
        const m = $('[data-modal].open');
        if (m) { m.classList.remove('open'); document.body.style.overflow = ''; }
    }
    $$('[data-modal-close]').forEach((b) => b.addEventListener('click', closeModal));
    $$('[data-modal]').forEach((m) => m.addEventListener('click', (e) => { if (e.target === m) closeModal(); }));

    // Help search: filters category cards client-side
    const helpSearch = $('[data-help-search]');
    if (helpSearch) {
        const items = $$('[data-help-item]');
        const none = $('[data-help-empty]');
        helpSearch.addEventListener('input', () => {
            const q = helpSearch.value.trim().toLowerCase();
            let shown = 0;
            items.forEach((i) => { const ok = !q || i.textContent.toLowerCase().includes(q); i.hidden = !ok; shown += ok; });
            none.hidden = shown > 0;
            $('[data-help-q]', none).textContent = helpSearch.value;
        });
    }

    // "On this page" rail: highlight the section in view
    const tocLinks = $$('[data-toc]');
    if (tocLinks.length && 'IntersectionObserver' in window) {
        const byId = Object.fromEntries(tocLinks.map((a) => [a.getAttribute('href').slice(1), a]));
        const spy = new IntersectionObserver((entries) => {
            entries.forEach((en) => {
                if (en.isIntersecting) {
                    tocLinks.forEach((a) => a.classList.remove('on'));
                    if (byId[en.target.id]) byId[en.target.id].classList.add('on');
                }
            });
        }, { rootMargin: '-40% 0px -55% 0px' });
        Object.keys(byId).forEach((id) => { const el = document.getElementById(id); if (el) spy.observe(el); });
    }

    if (location.pathname === '/pricing') track('pricing_viewed');
})();

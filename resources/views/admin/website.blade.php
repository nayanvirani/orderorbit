@extends('admin.layout')
@section('title', 'Website design')
@section('content')
<div class="ad-head">
    <div><h1>Website design</h1><p>Colours, fonts and corners of the public website (growvia.orderorbit.space): every page, the coming-soon page and the error pages. Changes show in the preview as you edit and go live when you save.</p></div>
    <div class="ad-actions"><a class="ad-btn" href="{{ route('site.home') }}" target="_blank" rel="noopener">View website ↗</a></div>
</div>

<form method="POST" action="{{ route('admin.website.reset') }}" id="ws-reset" onsubmit="return confirm('Reset every colour, font and radius to the built-in look?')">@csrf</form>
<div class="ws-layout">
    <form method="POST" action="{{ route('admin.website.update') }}" class="ad-form ws-form" id="ws-form">
        @csrf
        @foreach ($groups as $group => $fields)
            <section class="ad-card">
                <h2>{{ $group }}</h2>
                @if ($group === 'Dark sections')<p class="ad-muted ad-small">Footer, the analytics section on Home, the featured plan, the Help Center header and code blocks.</p>@endif
                <div class="ws-fields">
                    @foreach ($fields as $key => [$label, $type, $default, $var, $help])
                        @php($value = old('theme.'.$key, $values[$key]))
                        <label class="ws-field">
                            <span>{{ $label }} @if ($value !== $default)<button type="button" class="ad-btn link ws-undo" data-key="{{ $key }}" data-default="{{ $default }}" title="Back to {{ $default }}">Default</button>@endif</span>
                            @if ($type === 'color')
                                <span class="ws-color">
                                    <input type="color" value="{{ $value }}" data-pair="{{ $key }}" aria-label="{{ $group }} {{ $label }}">
                                    <input type="text" name="theme[{{ $key }}]" value="{{ $value }}" data-var="{{ $var }}" data-type="color" data-key="{{ $key }}" pattern="#[0-9a-fA-F]{6}" maxlength="7" spellcheck="false">
                                </span>
                            @elseif ($type === 'font')
                                <input type="text" name="theme[{{ $key }}]" value="{{ $value }}" data-var="{{ $var }}" data-type="font" data-key="{{ $key }}" list="ws-fonts" maxlength="40" spellcheck="false">
                            @elseif ($type === 'weight')
                                <select name="theme[{{ $key }}]" data-var="{{ $var }}" data-type="weight" data-key="{{ $key }}">
                                    @foreach (\App\Support\SiteTheme::WEIGHTS as $w => $wl)<option value="{{ $w }}" @selected((string) $value === (string) $w)>{{ $wl }} ({{ $w }})</option>@endforeach
                                </select>
                            @else
                                <span class="ad-input-prefix"><input type="number" name="theme[{{ $key }}]" value="{{ $value }}" data-var="{{ $var }}" data-type="px" data-key="{{ $key }}" min="0" max="1600" style="border-radius:9px 0 0 9px"><span style="border-radius:0 9px 9px 0;border-left:0;border-right:1px solid #cfd1dc">px</span></span>
                            @endif
                            @if ($help)<small>{{ $help }}</small>@endif
                        </label>
                    @endforeach
                </div>
            </section>
        @endforeach
        <datalist id="ws-fonts">@foreach (\App\Support\SiteTheme::FONTS as $font)<option value="{{ $font }}">@endforeach</datalist>
        <div class="ad-savebar">
            <span class="ad-muted" id="ws-dirty" style="margin-right:auto" hidden>Unsaved changes: shown in the preview only.</span>
            <button class="ad-btn danger" type="submit" form="ws-reset">Reset to built-in look</button>
            <button class="ad-btn primary" type="submit">Save design</button>
        </div>
    </form>

    <aside class="ws-preview">
        <div class="ws-bar">
            <strong>Preview</strong>
            <select id="ws-page" aria-label="Page to preview">
                <option value="{{ route('site.home', [], false) }}">Home</option>
                <option value="{{ route('site.pricing', [], false) }}">Pricing</option>
                <option value="{{ route('site.features', [], false) }}">Features</option>
                <option value="{{ route('site.feature', 'bundles', false) }}">Feature page (Bundles)</option>
                <option value="{{ route('site.how', [], false) }}">How it works</option>
                <option value="{{ route('site.contact', [], false) }}">Contact</option>
                <option value="{{ route('site.legal', 'terms', false) }}">Legal page</option>
                <option value="/this-page-does-not-exist">Error page (404)</option>
            </select>
            <span class="ws-sizes"><button type="button" class="ad-btn small" data-w="100%" aria-pressed="true">Desktop</button><button type="button" class="ad-btn small" data-w="390px" aria-pressed="false">Mobile</button></span>
        </div>
        <div class="ws-frame"><iframe id="ws-iframe" src="{{ route('site.home', [], false) }}" title="Website preview"></iframe></div>
    </aside>
</div>

<style>
    .ws-layout { display: grid; grid-template-columns: minmax(340px, 460px) minmax(0, 1fr); gap: 18px; align-items: start; }
    .ws-fields { display: grid; gap: 14px; }
    .ws-field > span:first-child { display: flex; justify-content: space-between; align-items: baseline; gap: 8px; }
    .ws-field small { font-weight: 400; color: var(--muted); }
    .ws-color { display: grid; grid-template-columns: 44px 1fr; gap: 8px; }
    .ws-color input[type=color] { width: 44px; height: 38px; padding: 3px; border: 1px solid #cfd1dc; border-radius: 9px; background: #fff; cursor: pointer; }
    .ws-color input[type=text] { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; text-transform: lowercase; }
    .ws-undo { font-size: 12.5px; font-weight: 500; }
    .ws-preview { position: sticky; top: 16px; display: grid; gap: 10px; }
    .ws-bar { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; }
    .ws-bar select { width: auto; }
    .ws-sizes { display: inline-flex; gap: 4px; margin-left: auto; }
    .ws-sizes [aria-pressed="true"] { background: var(--soft); border-color: var(--ink); }
    .ws-frame { height: calc(100vh - 110px); min-height: 520px; border: 1px solid var(--line); border-radius: var(--radius); background: #f3f4f8; overflow: hidden; display: grid; justify-items: center; }
    .ws-frame iframe { width: 100%; height: 100%; border: 0; background: #fff; transition: width .2s; }
    @media (max-width: 1100px) { .ws-layout { grid-template-columns: 1fr; } .ws-preview { position: static; } .ws-frame { height: 70vh; } }
</style>
<script>
(() => {
    // Live preview: the edited values become CSS variables on the previewed page (same site, so the
    // frame can be styled directly), and new fonts are loaded from Google Fonts.
    const form = document.getElementById('ws-form');
    const frame = document.getElementById('ws-iframe');
    const dirty = document.getElementById('ws-dirty');
    const inputs = () => [...form.querySelectorAll('[data-var]')];
    const cssValue = (el) => el.dataset.type === 'font' ? `"${el.value.trim()}"` : el.dataset.type === 'px' ? `${el.value}px` : el.value.trim();
    const valid = (el) => el.dataset.type !== 'color' || /^#[0-9a-f]{6}$/i.test(el.value.trim());

    function apply() {
        const doc = frame.contentDocument;
        if (!doc || !doc.documentElement) return;
        const fonts = new Set();
        inputs().forEach((el) => {
            if (!valid(el) || el.value === '') return;
            doc.documentElement.style.setProperty(el.dataset.var, cssValue(el));
            if (el.dataset.type === 'font') fonts.add(el.value.trim());
        });
        fonts.forEach((family) => {
            const id = 'ws-font-' + family.replace(/\W+/g, '-');
            if (doc.getElementById(id)) return;
            const link = doc.createElement('link');
            link.id = id; link.rel = 'stylesheet';
            link.href = 'https://fonts.googleapis.com/css2?family=' + family.replace(/ /g, '+') + ':ital,wght@0,300;0,400;0,500;0,600;0,700;1,400&display=swap';
            link.onerror = () => { link.href = 'https://fonts.googleapis.com/css2?family=' + family.replace(/ /g, '+') + '&display=swap'; };
            doc.head.appendChild(link);
        });
    }

    form.addEventListener('input', (e) => {
        const el = e.target;
        if (el.matches('input[type=color]')) {
            const text = form.querySelector(`[data-key="${el.dataset.pair}"][data-type=color]`);
            text.value = el.value;
        } else if (el.dataset.type === 'color' && valid(el)) {
            form.querySelector(`input[type=color][data-pair="${el.dataset.key}"]`).value = el.value.trim();
        }
        dirty.hidden = false;
        apply();
    });
    form.addEventListener('change', apply);
    form.addEventListener('click', (e) => {
        const undo = e.target.closest('.ws-undo');
        if (!undo) return;
        const el = form.querySelector(`[data-key="${undo.dataset.key}"][data-var]`);
        el.value = undo.dataset.default;
        el.dispatchEvent(new Event('input', { bubbles: true }));
    });
    frame.addEventListener('load', apply);
    document.getElementById('ws-page').addEventListener('change', (e) => { frame.src = e.target.value; });
    document.querySelectorAll('.ws-sizes [data-w]').forEach((b) => b.addEventListener('click', () => {
        frame.style.width = b.dataset.w;
        document.querySelectorAll('.ws-sizes [data-w]').forEach((x) => x.setAttribute('aria-pressed', String(x === b)));
    }));
})();
</script>
@endsection

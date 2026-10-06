@extends('admin.layout')
@section('title', $title.' · Website content')
@section('content')
<div class="ad-head">
    <div>
        <p class="ad-small"><a href="{{ route('admin.content') }}">← Website content</a></p>
        <h1>{{ $title }} @if ($edited)<span class="ad-badge ok">Edited</span>@else<span class="ad-badge">Built-in text</span>@endif</h1>
        <p>Change any line, add or remove list items with the buttons, and save. Empty lines are hidden on the website.</p>
    </div>
    @if (str_starts_with((string) $where, '/'))<div class="ad-actions"><a class="ad-btn" href="{{ url($where) }}" target="_blank" rel="noopener">View page ↗</a></div>@endif
</div>

<details class="ad-note" style="margin-bottom:16px">
    <summary style="cursor:pointer;font-weight:600">Formatting and placeholders</summary>
    <ul style="margin:8px 0 0;padding-left:18px;line-height:1.7">
        <li><code>*words*</code> shows the words in the accent colour (used in headings), <code>**words**</code> in bold.</li>
        <li><code>[link text](/help)</code> makes a link. Links can be a path like <code>/pricing</code>, a full <code>https://</code> address, or <code>{install}</code> / <code>{signin}</code> for the Shopify install and sign-in links.</li>
        <li><code>{year}</code> is the current year, <code>{templates}</code> the number of templates, <code>{from_price}</code> and <code>{max_price}</code> the cheapest and dearest paid plan. Some labels also take <code>{name}</code> or <code>{count}</code>, as in their built-in text.</li>
    </ul>
</details>

<form method="POST" action="{{ route('admin.content.update', $key) }}" id="content-form" class="ad-form">
    @csrf
    <input type="hidden" name="data" id="content-data">
    <div id="content-editor" class="ce"></div>
    <div class="ad-savebar">
        <span class="ad-muted" id="content-dirty" style="margin-right:auto" hidden>Unsaved changes</span>
        @if ($edited)<button class="ad-btn danger" type="submit" form="content-reset">Reset to built-in text</button>@endif
        <button class="ad-btn primary" type="submit">Save</button>
    </div>
</form>
<form method="POST" action="{{ route('admin.content.reset', $key) }}" id="content-reset" onsubmit="return confirm('Bring back the built-in text for {{ $title }}? Your edits on this page are removed.')">@csrf</form>

<script type="application/json" id="content-config">{!! json_encode([
    'values' => $values,
    'defaults' => $defaults,
    'labels' => \App\Support\SiteContent::LABELS,
    'groups' => \App\Support\SiteContent::GROUPS,
    'rows' => \App\Support\SiteContent::ROWS + ['sections' => ['Heading', 'Blocks'], 'Blocks' => ['Type: p, h3, list, note, code, table or faq', 'Content', 'Rows (tables)']],
    'hidden' => \App\Support\SiteContent::HIDDEN,
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
<style>
    .ce { display: grid; gap: 14px; }
    .ce-group { border: 1px solid var(--line); border-radius: 12px; background: var(--surface); }
    .ce-group > summary { cursor: pointer; padding: 12px 16px; font-weight: 650; list-style: none; display: flex; justify-content: space-between; gap: 8px; }
    .ce-group > summary::-webkit-details-marker { display: none; }
    .ce-group > summary::after { content: "▾"; color: var(--muted); }
    .ce-group:not([open]) > summary::after { content: "▸"; }
    .ce-group > .ce-body { padding: 4px 16px 16px; display: grid; gap: 12px; }
    .ce-field { display: grid; gap: 5px; font-weight: 550; }
    .ce-field > span { font-size: 13px; color: var(--muted); }
    .ce-field input[type=text], .ce-field textarea { width: 100%; }
    .ce-field textarea { min-height: 72px; resize: vertical; }
    .ce-list { display: grid; gap: 8px; }
    .ce-item { border: 1px dashed var(--line); border-radius: 10px; padding: 10px 12px; background: var(--soft); display: grid; gap: 8px; }
    .ce-item-head { display: flex; align-items: center; gap: 6px; font-size: 12.5px; color: var(--muted); }
    .ce-item-head b { margin-right: auto; font-weight: 600; }
    .ce-row { display: flex; gap: 6px; align-items: flex-start; }
    .ce-row > :first-child { flex: 1; }
    .ce-tools button { padding: 3px 8px; font-size: 12px; }
    .ce-add { justify-self: start; }
    .ce-sub { display: grid; gap: 10px; padding-left: 12px; border-left: 2px solid var(--line); }
    .ce-sub-title { font-size: 12.5px; font-weight: 650; color: var(--muted); text-transform: uppercase; letter-spacing: .04em; }
</style>
<script src="{{ asset('js/admin-content.js') }}?v={{ filemtime(public_path('js/admin-content.js')) }}" defer></script>
@endsection

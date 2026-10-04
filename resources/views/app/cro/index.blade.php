@extends('layouts.embedded')

@php($typeDef = $type ? \App\Experiences\Registry::type($type) : null)
@section('title', $typeDef['label'] ?? 'All offers')

@section('content')
<s-page inlineSize="large" heading="{{ $typeDef['label'] ?? 'All offers' }}">
    <s-button slot="primary-action" variant="primary" href="{{ app_route('app.cro.experiences.create', array_filter(['type' => $type])) }}">{{ $typeDef ? 'Create '.lower_label($typeDef['singular']) : 'Create experience' }}</s-button>
    <s-button slot="secondary-actions" data-download="{{ app_route('app.cro.experiences.export') }}">Export CSV</s-button>

    <s-section>
        <form method="GET" action="{{ app_route('app.cro.experiences.index') }}" class="oo-form-row" data-filter-form>
            <input type="hidden" name="shop" value="{{ request('shop') }}">
            <input type="hidden" name="host" value="{{ request('host') }}">
            <label class="oo-field" style="flex:1 1 220px">Search<input type="search" name="q" value="{{ $filters['q'] }}" placeholder="Name or ID"></label>
            @unless ($type)
                <label class="oo-field">Type
                    <select name="type" data-filter-change>
                        <option value="">All types</option>
                        @foreach (\App\Experiences\Registry::types() as $key => $t)<option value="{{ $key }}" @selected($filters['type'] === $key)>{{ $t['label'] }}</option>@endforeach
                    </select>
                </label>
            @endunless
            <label class="oo-field">Status
                <select name="status" data-filter-change>
                    <option value="">All except archived</option>
                    @foreach (['draft' => 'Draft', 'published' => 'Published', 'scheduled' => 'Scheduled', 'paused' => 'Paused', 'not_placed' => 'Not placed', 'archived' => 'Archived'] as $key => $label)
                        <option value="{{ $key }}" @selected($filters['status'] === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <s-button type="submit">Filter</s-button>
        </form>
    </s-section>

    <s-section>
        @if ($experiences->isEmpty())
            @if ($filters['q'] || $filters['status'] || (! $type && $filters['type']))
                <s-paragraph>No experiences match those filters.</s-paragraph>
            @else
                <x-app.empty :title="$typeDef ? 'No '.lower_label($typeDef['label']).' yet' : 'No experiences yet'" :text="$typeDef['empty'] ?? 'Create your first experience. Pick a template, customise it and publish it from the Theme Editor.'">
                    <s-button variant="primary" href="{{ app_route('app.cro.experiences.create', array_filter(['type' => $type])) }}">{{ $typeDef ? 'Create '.lower_label($typeDef['singular']) : 'Create experience' }}</s-button>
                </x-app.empty>
            @endif
        @else
            <form method="POST" action="{{ app_route('app.cro.experiences.bulk') }}" data-confirm="Apply this action to the selected experiences?">
                <div class="oo-form-row" style="margin-bottom:10px">
                    <select class="oo-select" name="bulk_action" aria-label="Bulk action" required>
                        <option value="">Bulk actions</option>
                        <option value="pause">Pause</option>
                        <option value="archive">Archive</option>
                    </select>
                    <s-button type="submit">Apply</s-button>
                </div>
                <div class="oo-scroll">
                    <table class="oo-table stack">
                        <thead><tr><th><input type="checkbox" aria-label="Select all" data-select-all></th><th>Name</th>@unless ($type)<th>Type</th>@endunless<th>Status</th><th>Template</th><th>Views</th><th>Revenue</th><th>Updated</th></tr></thead>
                        <tbody>
                            @foreach ($experiences as $experience)
                                <tr>
                                    <td><input type="checkbox" name="ids[]" value="{{ $experience->id }}" aria-label="Select {{ $experience->name }}"></td>
                                    <td><s-link href="{{ app_route('app.cro.experiences.show', ['experience' => $experience->id]) }}">{{ $experience->name }}</s-link><div class="oo-muted oo-small">{{ $experience->handle }}</div></td>
                                    @unless ($type)<td data-label="Type">{{ \App\Experiences\Registry::type($experience->type)['label'] }}</td>@endunless
                                    <td>@include('app.cro._status')</td>
                                    <td data-label="Template">{{ $experience->templateName() }}</td>
                                    <td class="oo-muted" data-label="Views">—</td>
                                    <td class="oo-muted" data-label="Revenue">—</td>
                                    <td class="oo-muted" data-label="Updated">{{ $experience->updated_at->diffForHumans() }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </form>
            <s-stack direction="inline" gap="small-200" style="margin-top:12px">
                @php($pageRoute = 'app.cro.experiences.index')
                @php($pageParams = array_filter(['type' => $type ?? $filters['type'], 'q' => $filters['q'], 'status' => $filters['status']]))
                @if ($experiences->currentPage() > 1)<s-button href="{{ app_route($pageRoute, $pageParams + ['page' => $experiences->currentPage() - 1]) }}">Previous</s-button>@endif
                @if ($experiences->hasMorePages())<s-button href="{{ app_route($pageRoute, $pageParams + ['page' => $experiences->currentPage() + 1]) }}">Next</s-button>@endif
            </s-stack>
        @endif
    </s-section>
</s-page>
@endsection

@push('scripts')
<script>
    document.querySelectorAll('[data-filter-change]').forEach((s) => s.addEventListener('change', () => s.form.submit()));
    // Downloads need the session token, so fetch the file instead of opening a new tab.
    document.querySelectorAll('[data-download]').forEach((b) => b.addEventListener('click', async () => {
        const res = await fetch(b.dataset.download, { headers: { Authorization: 'Bearer ' + await shopify.idToken() } });
        if (!res.ok) { shopify.toast.show('We couldn\'t complete that request. Please try again.', { isError: true }); return; }
        const name = (res.headers.get('Content-Disposition') || '').match(/filename="?([^";]+)/);
        const a = Object.assign(document.createElement('a'), { href: URL.createObjectURL(await res.blob()), download: name ? name[1] : 'experiences.csv' });
        a.click();
        URL.revokeObjectURL(a.href);
    }));
    const all = document.querySelector('[data-select-all]');
    if (all) all.addEventListener('change', () => document.querySelectorAll('input[name="ids[]"]').forEach((c) => { c.checked = all.checked; }));
</script>
@endpush

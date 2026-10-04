@extends('layouts.embedded')

@section('title', $workflow->name)

@push('head')
    <link rel="stylesheet" href="{{ asset('css/builder.css') }}?v={{ filemtime(public_path('css/builder.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/automation.css') }}?v={{ filemtime(public_path('css/automation.css')) }}">
@endpush

@section('content')
<s-page heading="{{ $workflow->name }}">
    <s-link slot="breadcrumb-actions" href="{{ app_route('app.automation.index') }}">Automation</s-link>
    @include('app.automation._nav')

    @if ($banner)
        <s-banner tone="{{ $errors ? 'warning' : 'info' }}">{{ $banner }}</s-banner>
    @endif

    <div class="wf-layout">
        <form method="POST" action="{{ app_route('app.automation.update', ['workflow' => $workflow->id]) }}" class="wf-main" data-workflow-form>
            <input type="hidden" name="definition" value="{{ json_encode($workflow->draft) }}" data-definition>
            <div class="wf-head">
                <label class="wf-name"><span>Workflow name</span><input name="name" value="{{ $workflow->name }}" maxlength="120" required></label>
                <div class="wf-badges">
                    @switch($workflow->status)
                        @case('enabled')<s-badge tone="success">Enabled</s-badge>@break
                        @case('disabled')<s-badge>Disabled</s-badge>@break
                        @default<s-badge tone="info">Draft</s-badge>
                    @endswitch
                    @if ($workflow->has_unpublished_changes && $workflow->published_version_id)<s-badge tone="warning">Unpublished changes</s-badge>@endif
                </div>
            </div>

            <div class="wf-canvas" data-canvas></div>

            <div class="wf-actions">
                <button type="submit" name="action" value="save" class="b-btn">Save draft</button>
                <button type="submit" name="action" value="test" class="b-btn">Test with a sample order</button>
                <button type="submit" name="action" value="publish" class="b-btn b-primary">Publish</button>
            </div>
            <p class="oo-muted wf-foot">Publishing creates a new version and enables the workflow. Runs already in progress finish on the version they started with.</p>
        </form>

        <aside class="wf-side">
            @if ($workflow->published_version_id)
                <s-section heading="Status">
                    <s-paragraph>{{ $workflow->status === 'enabled' ? 'Running on new triggers.' : 'Paused: new triggers are ignored. Runs in progress continue.' }}</s-paragraph>
                    <form method="POST" action="{{ app_route('app.automation.toggle', ['workflow' => $workflow->id]) }}" @if ($workflow->status === 'enabled') data-confirm="Disable this workflow? New orders won't start it until you enable it again." @endif>
                        <s-button type="submit">{{ $workflow->status === 'enabled' ? 'Disable' : 'Enable' }}</s-button>
                    </form>
                </s-section>
            @endif

            <s-section heading="Recent runs">
                @forelse ($recentRuns as $run)
                    <s-paragraph><s-link href="{{ app_route('app.automation.runs.show', ['run' => $run->id]) }}">#{{ $run->id }}</s-link> {{ $run->subject }} · @include('app.automation._status', ['status' => $run->status])@if ($run->test) <s-badge>Test</s-badge>@endif</s-paragraph>
                @empty
                    <s-paragraph><span class="oo-muted">No runs yet. Try "Test with a sample order".</span></s-paragraph>
                @endforelse
            </s-section>

            <s-section heading="Versions">
                @forelse ($versions as $v)
                    <div class="wf-version">
                        <span>Version {{ $v->version }} · {{ $v->published_at->diffForHumans() }}@if ($v->id === $workflow->published_version_id) <s-badge tone="success">Live</s-badge>@endif</span>
                        <form method="POST" action="{{ app_route('app.automation.restore', ['workflow' => $workflow->id, 'version' => $v->id]) }}" data-confirm="Load version {{ $v->version }} into the draft? Your unpublished changes are replaced."><s-button type="submit" variant="tertiary">Load into draft</s-button></form>
                    </div>
                @empty
                    <s-paragraph><span class="oo-muted">Not published yet.</span></s-paragraph>
                @endforelse
            </s-section>

            <s-section>
                <form method="POST" action="{{ app_route('app.automation.destroy', ['workflow' => $workflow->id]) }}" data-confirm="Delete this workflow and its run history? This can't be undone.">
                    <s-button type="submit" tone="critical" variant="tertiary">Delete workflow</s-button>
                </form>
            </s-section>
        </aside>
    </div>
</s-page>

<script type="application/json" data-wf-catalog>{!! json_encode($catalog, JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
<script type="application/json" data-wf-errors>{!! json_encode((object) $errors, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES) !!}</script>
<script type="application/json" data-wf-workflows>{!! json_encode($otherWorkflows, JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
<script src="{{ asset('js/workflow-builder.js') }}?v={{ filemtime(public_path('js/workflow-builder.js')) }}"></script>
@endsection

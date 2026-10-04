@extends('layouts.embedded')

@section('title', 'Automation templates')

@php($catalog = \App\Automation\Definition::catalog())

@section('content')
<s-page heading="Automation templates">
    <s-link slot="breadcrumb-actions" href="{{ app_route('app.automation.index') }}">Automation</s-link>
    @include('app.automation._nav')

    <s-section heading="Start from a template">
        <div class="ob-plans">
            @foreach ($templates as $key => $t)
                <div class="ob-plan">
                    <h3>{{ $t['name'] }}</h3>
                    <p class="oo-muted" style="margin:0">{{ $t['description'] }}</p>
                    <ul>
                        <li>Starts when: {{ $catalog['triggers'][$t['trigger']]['label'] }}</li>
                        @foreach (array_slice($t['steps'], 0, 3) as $step)
                            <li>{{ \App\Automation\Definition::describe($step) }}</li>
                        @endforeach
                    </ul>
                    <form method="POST" action="{{ app_route('app.automation.store') }}">
                        <input type="hidden" name="template" value="{{ $key }}">
                        <s-button type="submit" variant="primary">Use template</s-button>
                    </form>
                </div>
            @endforeach
        </div>
    </s-section>
</s-page>
@endsection

@extends('layouts.embedded')

@section('title', 'Permission')

@section('content')
<s-page heading="{{ ($removed ?? false) ? 'Access removed' : 'Permission needed' }}">
    <s-section>
        @if ($removed ?? false)
            <s-paragraph>Your access to OrderOrbit Space for this store was removed. Ask a store owner to restore it in Settings → Users &amp; roles.</s-paragraph>
        @else
            <s-paragraph>You don't have permission to perform this action.</s-paragraph>
            <s-paragraph>Ask a store owner or admin to change your role in Settings → Users &amp; roles.</s-paragraph>
            <s-button href="{{ app_route('app.dashboard') }}">Back to Home</s-button>
        @endif
    </s-section>
</s-page>
@endsection

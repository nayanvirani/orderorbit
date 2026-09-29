@extends('layouts.embedded')

@section('title', 'Onboarding')

@section('content')
<s-page heading="Welcome to OrderOrbit">
    <s-section heading="What do you want to improve first?">
        <s-paragraph>We'll recommend experiences and templates for your goal. You can change this any time.</s-paragraph>
        <form method="POST" action="{{ app_route('app.onboarding.update') }}">
            @foreach ($goals as $key => $goal)
                <label class="oo-radio">
                    <input type="radio" name="goal" value="{{ $key }}" @checked(old('goal', $store->goal) === $key) required>
                    <span><strong>{{ $goal['label'] }}</strong><small>{{ $goal['help'] }}</small></span>
                </label>
            @endforeach
            @error('goal')
                <s-banner tone="critical">{{ $message }}</s-banner>
            @enderror
            <s-button type="submit" variant="primary">Next</s-button>
        </form>
    </s-section>
</s-page>
@endsection

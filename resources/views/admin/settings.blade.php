@extends('admin.layout')
@section('title', 'Platform settings')
@section('content')
<div class="ad-head"><div><h1>Platform settings</h1><p>Billing rules and test stores for the whole platform.</p></div></div>
<form method="POST" action="{{ route('admin.settings.update') }}" class="ad-form">
    @csrf
    <section class="ad-card">
        <h2>Sales limit</h2>
        <div class="ad-fields">
            <label>{{ $fields['grace_days'][1] }}<input type="number" name="grace_days" min="0" max="60" value="{{ old('grace_days', $values['grace_days']) }}"><small>{{ $fields['grace_days'][3] }}</small></label>
            <label>{{ $fields['warn_at'][1] }}<span class="ad-input-prefix"><input type="number" name="warn_at" min="10" max="100" value="{{ old('warn_at', (int) round($values['warn_at'] * 100)) }}" style="border-radius:9px 0 0 9px"><span style="border-radius:0 9px 9px 0;border-left:0;border-right:1px solid #cfd1dc">%</span></span><small>{{ $fields['warn_at'][3] }}</small></label>
        </div>
    </section>
    <section class="ad-card">
        <h2>Test stores</h2>
        <div class="ad-fields">
            <label>{{ $fields['test_shops'][1] }}<textarea name="test_shops" rows="4" placeholder="vantora-plus.myshopify.com">{{ old('test_shops', implode("\n", (array) $values['test_shops'])) }}</textarea><small>{{ $fields['test_shops'][3] }}</small></label>
            <label>{{ $fields['test_shop_plan'][1] }}
                <select name="test_shop_plan">@foreach ($plans as $k => $name)<option value="{{ $k }}" @selected($values['test_shop_plan'] === $k)>{{ $name }}</option>@endforeach</select>
            </label>
            <label>{{ $fields['count_test_orders_for'][1] }}<textarea name="count_test_orders_for" rows="4">{{ old('count_test_orders_for', implode("\n", (array) $values['count_test_orders_for'])) }}</textarea><small>{{ $fields['count_test_orders_for'][3] }}</small></label>
        </div>
    </section>
    <div class="ad-savebar"><button class="ad-btn primary" type="submit">Save settings</button></div>
</form>
@endsection

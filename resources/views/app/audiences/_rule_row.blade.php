{{-- One segment rule: field, operator and a value input for the field's type (others are disabled). --}}
@php
    $fields = \App\Services\Audiences\Audiences::FIELDS;
    $def = $fields[$r['field']] ?? $fields['orders_count'];
    $type = $def['type'] === 'money' ? 'number' : $def['type'];
    $value = $r['value'] ?? '';
    $n = "rules[{$i}]";
@endphp
<div class="au-rule {{ $error ? 'b-has-error' : '' }}" data-rule>
    <select name="{{ $n }}[field]" data-field aria-label="Rule">
        @foreach ($fields as $key => $f)<option value="{{ $key }}" @selected($r['field'] === $key)>{{ $f['label'] }}</option>@endforeach
    </select>
    <select name="{{ $n }}[op]" data-op aria-label="Condition">
        @foreach ($def['ops'] as $key => $label)<option value="{{ $key }}" @selected(($r['op'] ?? '') === $key)>{{ $label }}</option>@endforeach
    </select>
    <span data-value-type="number" @if ($type !== 'number') hidden @endif><input type="number" name="{{ $n }}[value]" value="{{ is_scalar($value) ? $value : '' }}" min="0" step="any" aria-label="Value" @disabled($type !== 'number')></span>
    <span data-value-type="text" @if ($type !== 'text') hidden @endif><input type="text" name="{{ $n }}[value]" value="{{ is_scalar($value) ? $value : '' }}" maxlength="200" aria-label="Value" @disabled($type !== 'text')></span>
    <span data-value-type="select" @if ($type !== 'select') hidden @endif>
        <select name="{{ $n }}[value]" aria-label="Value" @disabled($type !== 'select')>
            @foreach ($def['options'] ?? [] as $key => $label)<option value="{{ $key }}" @selected($value === $key)>{{ $label }}</option>@endforeach
        </select>
    </span>
    <span data-value-type="products" class="au-pick" @if ($type !== 'products') hidden @endif>
        <input type="hidden" name="{{ $n }}[value]" value="{{ json_encode(is_array($value) ? $value : []) }}" data-pick-input @disabled($type !== 'products')>
        <button type="button" class="b-btn" data-pick>Choose products</button>
        <span class="oo-muted oo-small" data-pick-list></span>
    </span>
    <span data-value-type="experience" @if ($type !== 'experience') hidden @endif>
        <select name="{{ $n }}[value]" aria-label="Experience" @disabled($type !== 'experience')>
            <option value="">Choose an experience</option>
            @foreach ($experiences as $e)<option value="{{ $e->handle }}" @selected($value === $e->handle)>{{ $e->name }}</option>@endforeach
        </select>
    </span>
    <button type="button" class="au-x" data-remove aria-label="Remove rule">✕</button>
    <p class="b-help au-help-line" data-help>{{ $def['help'] ?? '' }}</p>
    @if ($error)<p class="b-error">{{ $error }}</p>@endif
</div>

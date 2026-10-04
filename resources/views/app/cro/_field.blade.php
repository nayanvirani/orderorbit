@php
    $name = "config[{$section}][{$key}]";
    $id = "f-{$section}-{$key}";
    $error = $fieldErrors["{$section}.{$key}"] ?? null;
    $rowErrors = collect($fieldErrors)->filter(fn ($v, $k) => str_starts_with($k, "{$section}.{$key}."))->all();
@endphp
<div class="b-field {{ $field['type'] === 'toggle' ? 'b-toggle' : '' }} {{ $error || $rowErrors ? 'b-has-error' : '' }}" data-field="{{ $section }}.{{ $key }}" @isset($field['when']) data-when="{{ json_encode($field['when']) }}" @endisset>
    @switch($field['type'])
        @case('toggle')
            <label for="{{ $id }}">
                <input type="hidden" name="{{ $name }}" value="0">
                <input type="checkbox" id="{{ $id }}" name="{{ $name }}" value="1" @checked($value)>
                <span>{{ $field['label'] }}</span>
            </label>
            @break

        @case('textarea')
            <label for="{{ $id }}">{{ $field['label'] }}</label>
            <textarea id="{{ $id }}" name="{{ $name }}" rows="{{ $key === 'custom_css' ? 5 : 3 }}" maxlength="{{ $field['max'] ?? 1000 }}" @if ($key === 'custom_css') spellcheck="false" class="b-code" @endif>{{ $value }}</textarea>
            @break

        @case('number')
        @case('money')
            <label for="{{ $id }}">{{ $field['label'] }}@if ($field['type'] === 'money') <span class="b-muted">({{ $store->currency ?? 'USD' }})</span>@endif</label>
            <input type="number" id="{{ $id }}" name="{{ $name }}" value="{{ $value }}" step="{{ $field['step'] ?? ($field['type'] === 'money' ? '0.01' : '1') }}" @isset($field['min']) min="{{ $field['min'] }}" @endisset @isset($field['max']) max="{{ $field['max'] }}" @endisset>
            @break

        @case('select')
            <label for="{{ $id }}">{{ $field['label'] }}</label>
            <select id="{{ $id }}" name="{{ $name }}">
                @foreach ($field['options'] as $optionValue => $optionLabel)
                    <option value="{{ $optionValue }}" @selected((string) $value === (string) $optionValue)>{{ $optionLabel }}</option>
                @endforeach
            </select>
            @break

        @case('checkboxes')
            <span class="b-label">{{ $field['label'] }}</span>
            <div class="b-checks">
                @foreach ($field['options'] as $optionValue => $optionLabel)
                    <label><input type="checkbox" name="{{ $name }}[]" value="{{ $optionValue }}" @checked(in_array($optionValue, (array) $value, true))> {{ $optionLabel }}</label>
                @endforeach
            </div>
            @break

        @case('color')
            <label for="{{ $id }}">{{ $field['label'] }}</label>
            <div class="b-color">
                <input type="color" value="{{ $value }}" data-color-for="{{ $id }}" aria-label="{{ $field['label'] }} picker">
                <input type="text" id="{{ $id }}" name="{{ $name }}" value="{{ $value }}" pattern="#[0-9a-fA-F]{6}" maxlength="7">
            </div>
            @break

        @case('image')
            <label for="{{ $id }}">{{ $field['label'] }}</label>
            <div class="b-image" data-image-field>
                <img src="{{ $value }}" alt="" data-image-thumb @if (! $value) hidden @endif>
                <input type="url" id="{{ $id }}" name="{{ $name }}" value="{{ $value }}" maxlength="1000" placeholder="https://cdn.shopify.com/…" data-image-url>
                <div class="b-image-actions">
                    <label class="b-btn">
                        <input type="file" accept="image/jpeg,image/png,image/gif,image/webp" data-image-upload="{{ app_route('app.uploads.image') }}" hidden>
                        Upload image
                    </label>
                    <button type="button" class="b-btn" data-image-clear @if (! $value) hidden @endif>Remove</button>
                    <span class="b-muted" data-image-status role="status"></span>
                </div>
            </div>
            @break

        @case('datetime')
            <label for="{{ $id }}">{{ $field['label'] }} <span class="b-muted">({{ $timezone }})</span></label>
            <input type="datetime-local" id="{{ $id }}" name="{{ $name }}" value="{{ $value ? \Illuminate\Support\Carbon::parse($value)->setTimezone($timezone)->format('Y-m-d\TH:i') : '' }}" data-tz-offset="{{ now($timezone)->format('P') }}">
            @break

        @case('products')
        @case('collections')
            <span class="b-label">{{ $field['label'] }}</span>
            <input type="hidden" name="{{ $name }}" value="{{ json_encode($value ?: []) }}" data-resource-input>
            <ul class="b-chips" data-resource-list></ul>
            <button type="button" class="b-btn" data-picker="{{ $field['type'] === 'products' ? 'product' : 'collection' }}" data-max="{{ $field['max_items'] ?? 20 }}" @if ($field['quantities'] ?? false) data-quantities @endif>
                {{ $field['type'] === 'products' ? 'Choose products' : 'Choose collections' }}
            </button>
            @if ($field['type'] === 'products')
                <p class="b-help">For products with options, tick the variants to offer in the picker; shoppers choose only from those.</p>
            @endif
            @break

        @case('list')
            <span class="b-label">{{ $field['label'] }}</span>
            <input type="hidden" name="{{ $name }}" value="{{ json_encode(array_values($value ?: [])) }}" data-list-input data-list-fields="{{ json_encode($field['fields']) }}" data-list-max="{{ $field['max_items'] ?? 10 }}">
            <div class="b-list" data-list></div>
            @foreach ($rowErrors as $message)
                <p class="b-error">{{ $message }}</p>
            @endforeach
            @break

        @default
            <label for="{{ $id }}">{{ $field['label'] }}</label>
            <input type="text" id="{{ $id }}" name="{{ $name }}" value="{{ $value }}" maxlength="{{ $field['max'] ?? 255 }}">
    @endswitch

    @if ($error)
        <p class="b-error" role="alert">{{ $error }}</p>
    @elseif (! empty($field['help']))
        <p class="b-help">{{ $field['help'] }}</p>
    @endif
</div>

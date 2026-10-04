{{-- Funnel name, window and up to 8 event steps (empty steps are ignored). --}}
@php
    $steps = array_values($funnel?->steps ?? []);
    $groups = ['Storefront' => \App\Services\Analytics\Events::STANDARD, 'OrderOrbit Space' => \App\Services\Analytics\Events::ORDERORBIT];
    unset($groups['Storefront']['session_started']);
@endphp
<form method="POST" action="{{ $action }}" class="an-funnel-form">
    <div class="oo-form-row">
        <label class="oo-field" style="flex:1;min-width:200px">Name<input type="text" name="name" value="{{ $funnel?->name }}" maxlength="80" required></label>
        <label class="oo-field">Steps must happen
            <select name="within">
                @foreach (\App\Services\Analytics\Funnels::WINDOWS as $key => $label)
                    <option value="{{ $key }}" @selected(($funnel?->within ?? '7d') === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
    </div>
    <ol class="an-steps-edit">
        @for ($i = 0; $i < 8; $i++)
            <li>
                <select name="steps[{{ $i }}][event]" aria-label="Step {{ $i + 1 }} event">
                    <option value="">{{ $i < 2 ? 'Choose an event' : '— (optional step)' }}</option>
                    @foreach ($groups as $group => $events)
                        <optgroup label="{{ $group }}">
                            @foreach ($events as $name => $label)
                                <option value="{{ $name }}" @selected(($steps[$i]['event'] ?? '') === $name)>{{ $label }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
                <select name="steps[{{ $i }}][experience]" aria-label="Step {{ $i + 1 }} experience">
                    <option value="">Any experience</option>
                    @foreach ($experiences as $handle => $e)
                        <option value="{{ $handle }}" @selected(($steps[$i]['experience'] ?? '') === $handle)>{{ $e->name }}</option>
                    @endforeach
                </select>
            </li>
        @endfor
    </ol>
    <p class="oo-muted oo-small">"Any experience" applies to OrderOrbit events; pick one to follow a single bundle, gift or upsell.</p>
    <s-button type="submit" variant="primary">{{ $funnel ? 'Save funnel' : 'Create funnel' }}</s-button>
</form>

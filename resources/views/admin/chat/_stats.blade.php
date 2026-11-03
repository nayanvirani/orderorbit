<div class="ad-kpis">
    @foreach ($stats as [$label, $value, $note])
        <div @class(['alert' => $label === 'Waiting now' && $value !== '0'])><small>{{ $label }}</small><b>{{ $value }}</b><span>{{ $note }}</span></div>
    @endforeach
</div>

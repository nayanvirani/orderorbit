<div class="metrics-panel reveal">
    <div class="top">
        <strong>{{ $title }} · last 30 days</strong>
        <span class="pill gray"><x-icon name="info" style="width:13px;height:13px"/> Illustrative preview — example data</span>
    </div>
    <div class="tiles">
        @foreach ($metrics as [$label, $value])
            <div class="tile"><small>{{ $label }}</small><b>{{ $value }}</b></div>
        @endforeach
    </div>
    <div class="chart">
        <svg viewBox="0 0 600 120" preserveAspectRatio="none" style="width:100%;height:120px" aria-hidden="true">
            <defs><linearGradient id="m-area-{{ $id }}" x1="0" x2="0" y1="0" y2="1"><stop offset="0" stop-color="#ff8a3d" stop-opacity=".3"/><stop offset="1" stop-color="#ff8a3d" stop-opacity="0"/></linearGradient></defs>
            @for ($y = 20; $y <= 100; $y += 40)<line x1="0" x2="600" y1="{{ $y }}" y2="{{ $y }}" stroke="#efedf7"/>@endfor
            <path d="M0 96 C 40 92, 70 84, 100 86 S 160 70, 200 72 S 260 58, 300 60 S 360 44, 400 48 S 460 30, 500 34 S 560 18, 600 16 L600 120 L0 120 Z" fill="url(#m-area-{{ $id }})"/>
            <path d="M0 96 C 40 92, 70 84, 100 86 S 160 70, 200 72 S 260 58, 300 60 S 360 44, 400 48 S 460 30, 500 34 S 560 18, 600 16" fill="none" stroke="#ff6a2e" stroke-width="3"/>
            <circle cx="600" cy="16" r="5" fill="#ffc45e"/>
        </svg>
    </div>
</div>

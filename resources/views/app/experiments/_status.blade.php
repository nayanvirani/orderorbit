@php
    $tone = ['running' => 'success', 'paused' => 'warning', 'draft' => 'neutral', 'completed' => 'info', 'stopped' => 'neutral'][$status] ?? 'neutral';
    $label = ['running' => 'Running', 'paused' => 'Paused', 'draft' => 'Draft', 'completed' => 'Completed', 'stopped' => 'Stopped early'][$status] ?? $status;
@endphp
<s-badge tone="{{ $tone }}">{{ $label }}</s-badge>

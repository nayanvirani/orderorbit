@switch($status)
    @case('completed')<s-badge tone="success">Completed</s-badge>@break
    @case('waiting')<s-badge tone="info">Waiting</s-badge>@break
    @case('failed')<s-badge tone="critical">Failed</s-badge>@break
    @case('running')<s-badge tone="warning">Running</s-badge>@break
    @case('ok')<s-badge tone="success">Done</s-badge>@break
    @case('skipped')<s-badge>Skipped</s-badge>@break
    @default<s-badge>{{ ucfirst($status) }}</s-badge>
@endswitch

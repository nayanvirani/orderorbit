@php($s = $experience->displayStatus())
@switch($s)
    @case('published')<s-badge tone="success">Published</s-badge>@break
    @case('scheduled')<s-badge tone="info">Scheduled</s-badge>@break
    @case('ended')<s-badge>Ended</s-badge>@break
    @case('paused')<s-badge tone="warning">Paused</s-badge>@break
    @case('archived')<s-badge>Archived</s-badge>@break
    @default<s-badge>Draft</s-badge>
@endswitch
@if ($experience->status === 'published' && $experience->placement_status === 'not_placed')
    <s-badge tone="critical">Not placed</s-badge>
@endif
@if ($experience->published_version_id && $experience->has_unpublished_changes && $experience->status !== 'archived')
    <s-badge tone="attention">Unpublished changes</s-badge>
@endif

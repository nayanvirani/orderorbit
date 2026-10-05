@extends('admin.layout')
@section('title', 'Templates')
@section('content')
<div class="ad-head"><div><h1>Templates</h1><p>Unpublished templates aren't offered for new experiences. Experiences already using them keep working.</p></div></div>
@foreach ($templates as $type => $list)
    <section class="ad-card">
        <h2>{{ \App\Experiences\Registry::type($type)['label'] }} <span class="ad-muted">{{ $type }}</span></h2>
        <table class="ad-table"><thead><tr><th>Template</th><th>Key</th><th>Version</th><th>Used by</th><th>Status</th><th></th></tr></thead><tbody>
            @foreach ($list as $t)
                <tr>
                    <td>{{ $t->name }}</td><td><code>{{ $t->key }}</code></td><td>v{{ $t->current_version }}</td><td>{{ $used[$type.':'.$t->key] ?? 0 }}</td>
                    <td>{!! $t->status === 'published' ? '<span class="ad-badge ok">Published</span>' : '<span class="ad-badge">Unpublished</span>' !!}</td>
                    <td><form method="POST" action="{{ route('admin.templates.toggle', $t->id) }}">@csrf<button class="ad-btn small" type="submit">{{ $t->status === 'published' ? 'Unpublish' : 'Publish' }}</button></form></td>
                </tr>
            @endforeach
        </tbody></table>
    </section>
@endforeach
@endsection

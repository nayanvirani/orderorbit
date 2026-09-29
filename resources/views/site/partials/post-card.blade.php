<article class="card post-card reveal">
    <div class="cover cover-{{ ($i % 5) + 1 }}"><x-icon :name="$post['feature'] ? \App\Support\Content::feature($post['feature'])['icon'] : 'book'"/></div>
    <div class="body">
        <div style="display:flex;gap:6px;flex-wrap:wrap"><span class="pill">{{ $post['category'] }}</span><span class="pill gray">Coming soon</span></div>
        <h3>{{ $post['title'] }}</h3>
        <p>{{ $post['excerpt'] }}</p>
        @if ($post['feature'])
            <a class="more" href="{{ route('site.feature', $post['feature']) }}" style="text-decoration:none;margin-top:auto">Try this in OrderOrbit <x-icon name="arrow"/></a>
        @endif
    </div>
</article>

<div class="flow {{ ($vertical ?? false) ? 'vertical' : '' }}" role="img" aria-label="{{ implode(' then ', $steps) }}">
    @foreach ($steps as $step)
        <span class="node {{ $loop->last ? 'last' : '' }}"><span class="n">{{ $loop->iteration }}</span>{{ $step }}</span>
        @unless ($loop->last)<x-icon name="arrow" class="arrow"/>@endunless
    @endforeach
</div>

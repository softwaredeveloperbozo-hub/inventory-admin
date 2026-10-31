@props(['title'])

<div class="page-head">
    <h1>{{ $title }}</h1>

    @if ($slot->isNotEmpty())
        <div class="head-actions">
            {{ $slot }}
        </div>
    @endif
</div>

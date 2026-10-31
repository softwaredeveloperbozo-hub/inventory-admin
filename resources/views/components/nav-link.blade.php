@props(['page', 'label'])

<a href="#{{ $page }}" data-page="{{ $page }}" {{ $attributes }}>
    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2">
        {{ $slot }}
    </svg>
    {{ $label }}
</a>

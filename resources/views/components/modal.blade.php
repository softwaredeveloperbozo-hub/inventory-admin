@props(['id', 'wide' => false])

<dialog id="{{ $id }}" class="{{ $wide ? 'wide' : '' }}">
    {{ $slot }}
</dialog>

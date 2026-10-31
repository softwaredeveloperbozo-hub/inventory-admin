@props(['info', 'prev', 'next'])

<div class="pager">
    <span id="{{ $info }}" class="muted"></span>
    <div>
        <button id="{{ $prev }}" class="btn">← Anterior</button>
        <button id="{{ $next }}" class="btn">Seguinte →</button>
    </div>
</div>

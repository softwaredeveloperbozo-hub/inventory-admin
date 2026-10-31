@props(['body', 'columns'])

<div class="card table-wrap">
    <table>
        <thead>
            <tr>
                @foreach ($columns as $column)
                    @php($label = is_array($column) ? $column[0] : $column)
                    @php($class = is_array($column) ? ($column[1] ?? '') : '')
                    <th class="{{ $class }}">{{ $label }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody id="{{ $body }}"></tbody>
    </table>
</div>

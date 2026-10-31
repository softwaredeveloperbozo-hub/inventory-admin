<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <title>Relatório de produtos</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #222; }
        h1 { font-size: 16px; margin: 0 0 4px; }
        .meta { color: #666; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #2d3748; color: #fff; text-align: left; padding: 6px; }
        td { padding: 5px 6px; border-bottom: 1px solid #ddd; }
        tr.even td { background: #f4f4f4; }
        .num { text-align: right; }
        .low { color: #b45309; font-weight: bold; }
        .out { color: #b91c1c; font-weight: bold; }
    </style>
</head>
<body>
    <h1>Relatório de produtos</h1>
    <div class="meta">
        Gerado em {{ $generatedAt->format('d/m/Y H:i') }}
        · {{ $products->count() }} produto(s)
        @if ($search) · Busca: "{{ $search }}" @endif
    </div>

    <table>
        <thead>
            <tr>
                <th>SKU</th>
                <th>Produto</th>
                <th>Categoria</th>
                <th class="num">Estoque</th>
                <th class="num">Custo</th>
                <th class="num">Preço</th>
                <th>Situação</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($products as $product)
                @php($label = $product->stockLabel())
                <tr class="{{ $loop->even ? 'even' : '' }}">
                    <td>{{ $product->sku }}</td>
                    <td>{{ $product->name }}</td>
                    <td>{{ $product->category?->name ?? '—' }}</td>
                    <td class="num">{{ rtrim(rtrim(number_format((float) ($product->stock?->quantity ?? 0), 3, ',', ' '), '0'), ',') }}</td>
                    <td class="num">{{ $product->currentPrice ? number_format((float) $product->currentPrice->cost_price, 2, ',', ' ') : '—' }}</td>
                    <td class="num">{{ $product->currentPrice ? number_format((float) $product->currentPrice->sale_price, 2, ',', ' ') : '—' }}</td>
                    <td class="{{ $label === 'Sem estoque' ? 'out' : ($label === 'Estoque baixo' ? 'low' : '') }}">{{ $label }}</td>
                </tr>
            @empty
                <tr><td colspan="7">Nenhum produto encontrado.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>

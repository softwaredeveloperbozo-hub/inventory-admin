<?php

namespace App\Exports;

use App\Models\Product;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Illuminate\Database\Eloquent\Builder;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ProductsExport implements
    FromQuery,
    WithHeadings,
    WithMapping,
    ShouldAutoSize,
    WithStyles,
    WithColumnFormatting
{
    public function __construct(
        private ?string $search = null,
        private ?int $categoryId = null,
    ) {}

    public function query(): Builder
    {
        return Product::query()
            ->with(['category', 'stock', 'currentPrice'])
            ->filter($this->search, $this->categoryId)
            ->orderBy('name');
    }

    public function headings(): array
    {
        return ['SKU', 'Produto', 'Categoria', 'Estoque', 'Mínimo', 'Custo', 'Preço de venda', 'Situação'];
    }

    public function map($product): array
    {
        return [
            $product->sku,
            $product->name,
            $product->category?->name ?? '—',
            (float) ($product->stock?->quantity ?? 0),
            (float) ($product->stock?->min_quantity ?? 0),
            $product->currentPrice ? (float) $product->currentPrice->cost_price : null,
            $product->currentPrice ? (float) $product->currentPrice->sale_price : null,
            $product->stockLabel(),
        ];
    }

    public function columnFormats(): array
    {
        return [
            'F' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,
            'G' => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}

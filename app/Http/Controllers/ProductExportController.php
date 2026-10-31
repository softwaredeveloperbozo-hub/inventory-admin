<?php

namespace App\Http\Controllers;

use App\Exports\ProductsExport;
use App\Models\Product;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ProductExportController extends Controller
{
    public function __invoke(Request $request)
    {
        $data = $request->validate([
            'format'      => ['required', 'in:xlsx,pdf'],
            'search'      => ['nullable', 'string', 'max:100'],
            'category_id' => ['nullable', 'integer'],
        ]);

        $search     = $data['search'] ?? null;
        $categoryId = isset($data['category_id']) ? (int) $data['category_id'] : null;
        $filename   = 'produtos-'.now()->format('Y-m-d_His');

        if ($data['format'] === 'xlsx') {
            return Excel::download(new ProductsExport($search, $categoryId), "{$filename}.xlsx");
        }

        $products = Product::query()
            ->with(['category', 'stock', 'currentPrice'])
            ->filter($search, $categoryId)
            ->orderBy('name')
            ->get();

        return Pdf::loadView('exports.products', [
                'products'    => $products,
                'generatedAt' => now(),
                'search'      => $search,
            ])
            ->setPaper('a4', 'landscape')
            ->download("{$filename}.pdf");
    }
}

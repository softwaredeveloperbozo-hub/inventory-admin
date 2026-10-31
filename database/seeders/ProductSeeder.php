<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        // [sku, nome, taxa de imposto]
        $catalog = [
            'Bebidas' => [
                ['AGU-500', 'Água 500ml', 14],
                ['REF-350', 'Refrigerante Cola 350ml', 14],
                ['SUM-1L',  'Sumo de Laranja 1L', 14],
                ['CER-330', 'Cerveja Lata 330ml', 14],
            ],
            'Alimentos' => [
                ['ARR-1KG', 'Arroz 1kg', 0],
                ['OLE-1L',  'Óleo Alimentar 1L', 14],
                ['ACU-1KG', 'Açúcar 1kg', 14],
                ['MAS-500', 'Massa Esparguete 500g', 14],
                ['FEI-1KG', 'Feijão 1kg', 0],
            ],
            'Higiene e Limpeza' => [
                ['SAB-90',  'Sabonete 90g', 14],
                ['DET-500', 'Detergente Loiça 500ml', 14],
                ['PAP-4R',  'Papel Higiénico 4 rolos', 14],
            ],
            'Papelaria' => [
                ['CAD-A4',  'Caderno A4', 14],
                ['CAN-AZ',  'Caneta Azul', 14],
            ],
        ];

        foreach ($catalog as $categoryName => $products) {
            $category = Category::where('name', $categoryName)->firstOrFail();

            foreach ($products as [$sku, $name, $taxRate]) {
                Product::firstOrCreate(
                    ['sku' => $sku],
                    [
                        'name'        => $name,
                        'category_id' => $category->id,
                        'tax_rate'    => $taxRate,
                        'is_active'   => true,
                    ]
                );
            }
        }
    }
}

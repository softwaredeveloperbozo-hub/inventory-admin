<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Services\SaleService;
use Illuminate\Database\Seeder;

class SaleSeeder extends Seeder
{
    public function run(): void
    {
        if (Sale::exists()) {
            return;
        }

        mt_srand(42);

        $service = app(SaleService::class);
        $admin   = User::where('email', 'admin@teste.com')->firstOrFail();
        $users   = User::whereIn('email', ['admin@teste.com', 'operador@teste.com'])->get()->values();

        // Só vende produtos com preço e estoque confortável
        $sellable = Product::query()
            ->whereHas('currentPrice')
            ->whereHas('stock', fn ($q) => $q->where('quantity', '>=', 50))
            ->orderBy('id')
            ->pluck('id')
            ->all();

        $methods   = ['cash', 'card', 'pix', 'transfer'];
        $customers = [null, null, null, 'Maria Santos', 'João Pereira', 'Ana Costa', 'Carlos Mendes'];

        $created = [];

        for ($day = now()->startOfMonth(); $day->lte(now()); $day->addDay()) {
            $salesToday = mt_rand(2, 4);

            for ($i = 0; $i < $salesToday; $i++) {
                $soldAt = $day->copy()->setTime(mt_rand(8, 19), mt_rand(0, 59));

                if ($soldAt->isFuture()) {
                    $soldAt = now()->subSeconds(mt_rand(1, 60));
                }

                $created[] = $service->create([
                    'payment_method' => $methods[array_rand($methods)],
                    'customer_name'  => $customers[array_rand($customers)],
                    'discount'       => mt_rand(1, 10) === 1 ? 50 : 0,
                    'sold_at'        => $soldAt,
                    'items'          => $this->pickItems($sellable),
                ], $users[mt_rand(0, $users->count() - 1)]->id);
            }
        }

        // Duas vendas canceladas, para a lista e o dashboard terem os dois estados
        foreach (array_slice($created, 5, 2) as $sale) {
            $service->cancel($sale, $admin->id);
        }
    }

    private function pickItems(array $ids): array
    {
        $howMany = min(mt_rand(1, 3), count($ids));
        $chosen  = [];

        while (count($chosen) < $howMany) {
            // O quadrado faz os primeiros produtos da lista saírem mais
            $index = min((int) floor(pow(mt_rand() / mt_getrandmax(), 2) * count($ids)), count($ids) - 1);

            $chosen[$ids[$index]] = [
                'product_id' => $ids[$index],
                'quantity'   => mt_rand(1, 6),
            ];
        }

        return array_values($chosen);
    }
}

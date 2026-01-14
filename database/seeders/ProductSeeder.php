<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Product;

class ProductSeeder extends Seeder
{
    public function run()
    {
        $products = [
            ['name' => 'Product A', 'stock' => 500],
            ['name' => 'Product B', 'stock' => 500],
            ['name' => 'Product C', 'stock' => 500],
        ];

        foreach ($products as $product) {
            Product::create($product);
        }
    }
}

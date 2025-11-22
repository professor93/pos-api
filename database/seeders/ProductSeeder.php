<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Product;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get branch IDs
        $mainBranch = Branch::where('ext_id', 'BR001')->first();
        $northBranch = Branch::where('ext_id', 'BR002')->first();
        $southBranch = Branch::where('ext_id', 'BR003')->first();

        $products = [
            [
                'ext_id' => 'PROD-001',
                'branch_id' => $mainBranch?->id,
                'name' => 'Coca Cola 500ml',
                'barcode' => '1234567890123',
                'description' => 'Refreshing cola drink',
                'price' => 2.50,
                'discount_price' => 2.00,
                'unit' => 'pcs',
                'category' => 'Beverages',
                'is_active' => true,
            ],
            [
                'ext_id' => 'PROD-002',
                'branch_id' => $mainBranch?->id,
                'name' => 'Pepsi 500ml',
                'barcode' => '1234567890124',
                'description' => 'Classic cola beverage',
                'price' => 2.50,
                'discount_price' => null,
                'unit' => 'pcs',
                'category' => 'Beverages',
                'is_active' => true,
            ],
            [
                'ext_id' => 'PROD-003',
                'branch_id' => $northBranch?->id,
                'name' => 'Mineral Water 1L',
                'barcode' => '1234567890125',
                'description' => 'Pure mineral water',
                'price' => 1.50,
                'discount_price' => null,
                'unit' => 'pcs',
                'category' => 'Beverages',
                'is_active' => true,
            ],
            [
                'ext_id' => 'PROD-004',
                'branch_id' => $northBranch?->id,
                'name' => 'Chips 100g',
                'barcode' => '1234567890126',
                'description' => 'Crispy potato chips',
                'price' => 3.00,
                'discount_price' => 2.50,
                'unit' => 'pcs',
                'category' => 'Snacks',
                'is_active' => true,
            ],
            [
                'ext_id' => 'PROD-005',
                'branch_id' => $southBranch?->id,
                'name' => 'Chocolate Bar 50g',
                'barcode' => '1234567890127',
                'description' => 'Milk chocolate bar',
                'price' => 2.00,
                'discount_price' => null,
                'unit' => 'pcs',
                'category' => 'Confectionery',
                'is_active' => true,
            ],
        ];

        foreach ($products as $product) {
            Product::updateOrCreate(
                ['ext_id' => $product['ext_id']],
                $product
            );
        }
    }
}

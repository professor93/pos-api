<?php

namespace Database\Seeders;

use App\Models\Branch;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class BranchSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $branches = [
            [
                'name' => 'Main Branch',
                'ext_id' => 'BR001',
                'address' => '123 Main Street, Downtown',
                'phone' => '+1-555-0101',
                'is_active' => true,
            ],
            [
                'name' => 'North Branch',
                'ext_id' => 'BR002',
                'address' => '456 North Avenue, Uptown',
                'phone' => '+1-555-0102',
                'is_active' => true,
            ],
            [
                'name' => 'South Branch',
                'ext_id' => 'BR003',
                'address' => '789 South Boulevard, Southside',
                'phone' => '+1-555-0103',
                'is_active' => true,
            ],
        ];

        foreach ($branches as $branch) {
            Branch::updateOrCreate(
                ['ext_id' => $branch['ext_id']],
                $branch
            );
        }
    }
}

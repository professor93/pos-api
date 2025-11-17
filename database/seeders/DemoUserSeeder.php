<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create demo user
        $user = User::firstOrCreate(
            ['email' => 'demo@pos-api.local'],
            [
                'name' => 'Demo User',
                'password' => Hash::make('password'),
            ]
        );

        // Delete existing tokens for this user
        $user->tokens()->delete();

        // Create a new API token with unlimited expiration
        $token = $user->createToken('demo-api-token', ['*'], null);

        // Create demo branches
        $branch1 = Branch::firstOrCreate(
            ['code' => 'BR001'],
            [
                'name' => 'Main Branch',
                'address' => '123 Main Street',
                'phone' => '+1234567890',
                'is_active' => true,
            ]
        );

        $branch2 = Branch::firstOrCreate(
            ['code' => 'BR002'],
            [
                'name' => 'Secondary Branch',
                'address' => '456 Second Avenue',
                'phone' => '+0987654321',
                'is_active' => true,
            ]
        );

        // Output the token
        $this->command->newLine();
        $this->command->info('==============================================');
        $this->command->info('Demo User Created Successfully!');
        $this->command->info('==============================================');
        $this->command->newLine();
        $this->command->info('Email: demo@pos-api.local');
        $this->command->info('Password: password');
        $this->command->newLine();
        $this->command->info('API Token (use in Authorization: Bearer header):');
        $this->command->warn($token->plainTextToken);
        $this->command->newLine();
        $this->command->info('Demo Branches Created:');
        $this->command->info("  - Branch ID: {$branch1->id}, Code: {$branch1->code}, Name: {$branch1->name}");
        $this->command->info("  - Branch ID: {$branch2->id}, Code: {$branch2->code}, Name: {$branch2->name}");
        $this->command->newLine();
        $this->command->info('Token Expiration: Unlimited');
        $this->command->info('==============================================');
        $this->command->newLine();
    }
}

<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Standard Employee (Can only create requests)
        User::create([
            'name' => 'Darren Beltham',
            'email' => 'darren@company.com',
            'password' => Hash::make('password123'),
            'department' => 'Engineering',
            'role' => 'employee',
        ]);

        // 2. HR Manager (Can approve HR requests)
        User::create([
            'name' => 'Sarah Jenkins',
            'email' => 'sarah.hr@company.com',
            'password' => Hash::make('password123'),
            'department' => 'HR',
            'role' => 'manager',
        ]);

        // 3. Finance (Can see approved requests and pay them)
        User::create([
            'name' => 'Marcus Finance',
            'email' => 'marcus@company.com',
            'password' => Hash::make('password123'),
            'department' => 'Finance',
            'role' => 'finance',
        ]);

        // 4. System Admin (Can see everything)
        User::create([
            'name' => 'Admin Superuser',
            'email' => 'admin@company.com',
            'password' => Hash::make('password123'),
            'department' => 'IT',
            'role' => 'admin',
        ]);
    }
}
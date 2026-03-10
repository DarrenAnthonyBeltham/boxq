<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Requisition;
use App\Models\Product;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run()
    {
        User::truncate();
        Requisition::truncate();
        Product::truncate();

        $defaultPassword = Hash::make('password123');
        $defaultPreferences = [
            'email_on_status' => true,
            'email_on_new' => true
        ];

        $admin = User::create([
            'name' => 'System Admin',
            'email' => 'admin@boxq.com',
            'password' => $defaultPassword,
            'department' => 'IT',
            'role' => 'admin',
            'preferences' => $defaultPreferences,
        ]);

        $finance = User::create([
            'name' => 'Marcus Finance',
            'email' => 'marcus@boxq.com',
            'password' => $defaultPassword,
            'department' => 'Finance',
            'role' => 'finance',
            'preferences' => $defaultPreferences,
        ]);

        $hrManager = User::create([
            'name' => 'Sarah Jenkins',
            'email' => 'sarah@boxq.com',
            'password' => $defaultPassword,
            'department' => 'HR',
            'role' => 'manager',
            'preferences' => $defaultPreferences,
        ]);

        $hrStaff = User::create([
            'name' => 'Esdeekid',
            'email' => 'esdeekid@boxq.com',
            'password' => $defaultPassword,
            'department' => 'HR',
            'role' => 'employee',
            'preferences' => $defaultPreferences,
        ]);

        $engManager = User::create([
            'name' => 'Albert Leonardi',
            'email' => 'albert@boxq.com',
            'password' => $defaultPassword,
            'department' => 'Engineering',
            'role' => 'manager',
            'preferences' => $defaultPreferences,
        ]);

        $engStaff1 = User::create([
            'name' => 'Darren Beltham',
            'email' => 'darren@boxq.com',
            'password' => $defaultPassword,
            'department' => 'Engineering',
            'role' => 'employee',
            'preferences' => $defaultPreferences,
        ]);

        $engStaff2 = User::create([
            'name' => 'Alex Chen',
            'email' => 'alex@boxq.com',
            'password' => $defaultPassword,
            'department' => 'Engineering',
            'role' => 'employee',
            'preferences' => $defaultPreferences,
        ]);

        Product::create([
            'name' => 'Figma Enterprise License',
            'sku' => 'SW-FIGMA-ENT',
            'description' => 'Annual enterprise license for UI/UX design and prototyping.',
            'category' => 'Software',
            'price' => 540.00,
            'is_active' => true,
        ]);

        Product::create([
            'name' => 'AWS EC2 Instance (Monthly)',
            'sku' => 'CLOUD-AWS-EC2',
            'description' => 'Standard cloud compute capacity for application staging.',
            'category' => 'Infrastructure',
            'price' => 280.00,
            'is_active' => true,
        ]);

        Product::create([
            'name' => 'NVIDIA RTX 4090 GPU',
            'sku' => 'HW-GPU-4090',
            'description' => 'Dedicated hardware for local machine learning model training.',
            'category' => 'Hardware',
            'price' => 1800.00,
            'is_active' => true,
        ]);

        Product::create([
            'name' => 'Steel Lateral File Cabinet',
            'sku' => 'OFF-CAB-STL',
            'description' => 'Secure physical storage for department documentation.',
            'category' => 'Office Supplies',
            'price' => 250.00,
            'is_active' => true,
        ]);

        Product::create([
            'name' => 'Ergonomic Office Chair',
            'sku' => 'OFF-CHR-ERG',
            'description' => 'Standard issue ergonomic seating for employee workstations.',
            'category' => 'Office Supplies',
            'price' => 350.00,
            'is_active' => true,
        ]);

        Requisition::create([
            'user_id' => $engStaff2->id,
            'requester' => $engStaff2->name,
            'department' => $engStaff2->department,
            'justification' => 'Annual subscription for UI/UX wireframing tools needed for the new CoreDigital client interfaces.',
            'items' => [
                ['name' => 'Figma Enterprise License', 'price' => 540, 'qty' => 1]
            ],
            'total_price' => 540,
            'status' => 'Pending',
        ]);

        Requisition::create([
            'user_id' => $engStaff1->id,
            'requester' => $engStaff1->name,
            'department' => $engStaff1->department,
            'justification' => 'Cloud server infrastructure setup to begin staging and testing the CoreDigital software architecture.',
            'items' => [
                ['name' => 'AWS EC2 Instances (Quarterly)', 'price' => 850, 'qty' => 1],
                ['name' => 'Domain Registrations', 'price' => 15, 'qty' => 3]
            ],
            'total_price' => 895,
            'status' => 'Approved',
            'reason' => 'Approved. Keep an eye on the bandwidth usage this quarter.',
        ]);

        Requisition::create([
            'user_id' => $hrStaff->id,
            'requester' => $hrStaff->name,
            'department' => $hrStaff->department,
            'justification' => 'New filing cabinets required to store physical employee onboarding documents securely.',
            'items' => [
                ['name' => 'Steel Lateral File Cabinet', 'price' => 250, 'qty' => 2]
            ],
            'total_price' => 500,
            'status' => 'Paid',
            'reason' => 'Approved and processed.',
        ]);
        
        echo "Database successfully seeded with accounts, products, and requisitions!\n";
    }
}
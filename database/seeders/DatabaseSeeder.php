<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Support\Str;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        DB::table('orders')->insert([
            [
                'user_id' => 2,
                'vendor_type_id' => 1,
                'product_id' => 3,
                'user_name' => 'John Doe',
                'phone_number' => '1234567890',
                'address' => '123 Street, City',
                'description' => 'Sample Order',
                'total_amount' => 500,
                'order_token' => Str::random(10),
                'status' => 'pending',
                'tracking_number' => Str::random(15),
                'shipping_address' => '456 Avenue, City',
                'cancellation_reason' => null,
                'cancellation_status' => 'not_requested',
                'cancellation_requested_at' => null,
                'cancellation_approved_at' => null,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ]);

        // Order Items Table
        DB::table('order_items')->insert([
            [
                'order_id' => 2,
                'vendor_id' => 1,
                'product_id' => 3,
                'quantity' => 2,
                'price' => 250,
                'total_price' => 500,
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ]);

        // Payments Table
        DB::table('payments')->insert([
            [
                'user_id' => 2,
                'vendor_id' => 1,
                'order_id' => 1,
                'amount' => 500,
                'descripiton' => 'Payment for Order #1',
                'status' => 'pending',
                'payment_method' => 'Credit Card',
                'transaction_id' => Str::random(12),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ]);
    }
}

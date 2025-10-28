<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        \App\Models\User::factory(10)->create();

        \App\Models\User::factory()->create([
            'name' => 'Alan Test',
            'email' => 'Alancarabali@gmail.com',
            'password' => bcrypt('17964290'),
            'phone' => '1234567890',
            'address' => '123 Test St, Test City',
        ]);

        \App\Models\Client::factory(50)->create();

        \App\Models\Client::factory()->create([
            'user_id' => 1,
            'name' => 'Diana Cegarra',
            'email' => 'dianaamaamiranda@gnail.com',
            'phone' => '0987654321',
            'document' => '123456789',
            'address' => '456 Example Ave, Sample Town',
            'notes' => 'Cliente importante',
        ]);
    }
}

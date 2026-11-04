<?php

namespace Tests\Feature;

use App\Models\Product;
use Database\Seeders\DatabaseSeeder;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    public function test_database_seeder_does_not_create_demo_data_outside_local_and_testing_environments(): void
    {
        // Ensure the schema is migrated while the test environment is still active.
        $this->assertDatabaseCount('users', 0);

        $originalEnvironment = $this->app['env'];

        $this->app['env'] = 'production';

        try {
            (new DatabaseSeeder)->setContainer($this->app)->run();
        } finally {
            $this->app['env'] = $originalEnvironment;
        }

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('recipes', 0);
        $this->assertDatabaseCount('announcements', 0);
        $this->assertDatabaseCount('activity_logs', 0);

        // Categories are catalogue data, not demo data, and remain provisioned.
        $this->assertDatabaseCount('categories', 8);
        $this->assertDatabaseHas('categories', ['slug' => 'vegetables']);
    }

    public function test_database_seeder_still_creates_demo_data_in_the_testing_environment(): void
    {
        (new DatabaseSeeder)->setContainer($this->app)->run();

        $this->assertDatabaseHas('users', [
            'email' => 'admin@farmreach.ph',
            'role' => 'admin',
        ]);
        $this->assertTrue(Product::query()->exists());
        $this->assertDatabaseCount('categories', 8);
    }
}

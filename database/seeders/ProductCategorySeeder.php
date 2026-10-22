<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductCategorySeeder extends Seeder
{
    /**
     * @var array<int, array{name: string, slug: string, icon: string, description: string}>
     */
    private const CATEGORIES = [
        [
            'name' => 'Eggs & Dairy',
            'slug' => 'eggs-dairy',
            'icon' => 'bi-egg-fried',
            'description' => 'Farm-fresh chicken and duck eggs, fresh milk, and other local dairy products.',
        ],
        [
            'name' => 'Snacks & Processed Farm Products',
            'slug' => 'snacks-processed-farm-products',
            'icon' => 'bi-cookie',
            'description' => 'Locally made snacks and processed farm products, including banana chips, cassava chips, dried mangoes, roasted peanuts, puto, kutsinta, suman, and other kakanin.',
        ],
        [
            'name' => 'Plants & Seedlings',
            'slug' => 'plants-seedlings',
            'icon' => 'bi-tree',
            'description' => 'Vegetable, fruit, and flower seedlings, ornamental plants, and potted plants for home gardens and farms.',
        ],
    ];

    public function run(): void
    {
        DB::transaction(function (): void {
            foreach (self::CATEGORIES as $category) {
                $alreadyExists = Category::query()
                    ->where('slug', $category['slug'])
                    ->orWhere('name', $category['name'])
                    ->exists();

                if ($alreadyExists) {
                    continue;
                }

                Category::create($category);
            }
        });
    }
}

<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductCategorySeeder extends Seeder
{
    /**
     * Idempotently ensure the full product category catalogue exists.
     *
     * Safe to run by itself in production:
     * php artisan db:seed --class=ProductCategorySeeder
     *
     * @var array<int, array{name: string, slug: string, icon: string, description: string}>
     */
    private const CATEGORIES = [
        [
            'name' => 'Vegetables',
            'slug' => 'vegetables',
            'icon' => 'bi-flower1',
            'description' => 'Crisp, garden-fresh highland and lowland vegetables directly harvested from local farms.',
        ],
        [
            'name' => 'Fruits',
            'slug' => 'fruits',
            'icon' => 'bi-apple',
            'description' => 'Sweet, juicy tropical fruits harvested at peak ripeness.',
        ],
        [
            'name' => 'Grains & Rice',
            'slug' => 'grains-rice',
            'icon' => 'bi-layers',
            'description' => 'Locally milled premium Dinorado, Sinandomeng, and upland heirloom grains.',
        ],
        [
            'name' => 'Root Crops',
            'slug' => 'root-crops',
            'icon' => 'bi-box-seam',
            'description' => 'Nutrient-rich tubers, sweet potatoes, and highland potatoes.',
        ],
        [
            'name' => 'Herbs & Spices',
            'slug' => 'herbs-spices',
            'icon' => 'bi-fire',
            'description' => 'Fresh native garlic, ginger, chili, and culinary aromatics.',
        ],
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
                if (self::findMatchingCategory($category['slug'], $category['name']) !== null) {
                    continue;
                }

                Category::create($category);
            }
        });
    }

    /**
     * Resolve an existing category using the seeder's identity rule.
     *
     * A canonical slug match wins; otherwise the display name is matched. This
     * keeps seeding idempotent when an operator has renamed a slug, and lets
     * other seeders resolve the same row instead of assuming the default slug.
     */
    public static function findMatchingCategory(string $slug, string $name): ?Category
    {
        $bySlug = Category::query()->where('slug', $slug)->first();

        if ($bySlug instanceof Category) {
            return $bySlug;
        }

        return Category::query()->where('name', $name)->first();
    }
}

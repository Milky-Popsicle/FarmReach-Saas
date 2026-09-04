<?php

namespace Database\Seeders;

use App\Models\ActivityLog;
use App\Models\Announcement;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Create Administrator (only 1 can exist per specs)
        $admin = User::create([
            'name' => 'System Administrator',
            'email' => 'admin@farmreach.ph',
            'phone_number' => '09123456789',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'gender' => 'Male',
            'birthday' => '1990-05-15',
            'address' => 'Provincial Capitol Complex, Surigao City',
            'bio' => 'FarmReach platform administrator supervising market trade operations and user compliance.',
        ]);

        // 2. Create Farmers
        $farmerJustin = User::create([
            'name' => 'Justin Nabunturan',
            'email' => 'justin@nabunturanfarm.ph',
            'phone_number' => '09876543210',
            'password' => Hash::make('password'),
            'role' => 'farmer',
            'gender' => 'Male',
            'birthday' => '1985-08-20',
            'address' => 'Brgy. Cagdianao, Surigao del Norte',
            'bio' => 'Highland organic crop producer specializing in fresh Sayote, crisp cabbage, and quality root crops.',
        ]);

        $farmerMaria = User::create([
            'name' => 'Maria Santos',
            'email' => 'maria@surigaogreens.ph',
            'phone_number' => '09771234567',
            'password' => Hash::make('password'),
            'role' => 'farmer',
            'gender' => 'Female',
            'birthday' => '1992-03-12',
            'address' => 'Brgy. San Juan, Surigao City',
            'bio' => 'Passionate grower providing fresh kitchen aromatics, native onions, tomatoes, and organic ginger.',
        ]);

        $farmerPedro = User::create([
            'name' => 'Pedro Reyes',
            'email' => 'pedro@caragaorganic.ph',
            'phone_number' => '09669876543',
            'password' => Hash::make('password'),
            'role' => 'farmer',
            'gender' => 'Male',
            'birthday' => '1980-11-04',
            'address' => 'Brgy. Luna, Surigao City',
            'bio' => 'Heritage Dinorado rice producer and certified orchardist cultivating Carabao mangoes.',
        ]);

        // 3. Create Buyers
        $buyerShery = User::create([
            'name' => 'Shery Jade Garcia',
            'email' => 'shery@buyer.ph',
            'phone_number' => '09151112233',
            'password' => Hash::make('password'),
            'role' => 'buyer',
            'gender' => 'Female',
            'birthday' => '1998-07-25',
            'address' => 'Nueva Extension, Surigao City',
            'bio' => 'Regular household buyer looking for fresh, directly-sourced farm produce.',
        ]);

        $buyerRynard = User::create([
            'name' => 'Rynard Loayon',
            'email' => 'rynard@caragabistro.ph',
            'phone_number' => '09172223344',
            'password' => Hash::make('password'),
            'role' => 'buyer',
            'gender' => 'Male',
            'birthday' => '1997-01-18',
            'address' => 'Borromeo Street, Downtown Surigao City',
            'bio' => 'Head Chef & Procurement Manager at Caraga Bistro. Placing regular wholesale bulk orders.',
        ]);

        $buyerAna = User::create([
            'name' => 'Ana Rivera',
            'email' => 'ana@buyer.ph',
            'phone_number' => '09203334455',
            'password' => Hash::make('password'),
            'role' => 'buyer',
            'gender' => 'Female',
            'birthday' => '1995-10-30',
            'address' => 'Rizal Street, Surigao City',
            'bio' => 'Culinary enthusiast searching for local recipe ingredients and weekly fresh harvest.',
        ]);

        // 4. Create Categories
        $catVegetables = Category::create([
            'name' => 'Vegetables',
            'slug' => 'vegetables',
            'icon' => 'bi-flower1',
            'description' => 'Crisp, garden-fresh highland and lowland vegetables directly harvested from local farms.',
        ]);

        $catFruits = Category::create([
            'name' => 'Fruits',
            'slug' => 'fruits',
            'icon' => 'bi-apple',
            'description' => 'Sweet, juicy tropical fruits harvested at peak ripeness.',
        ]);

        $catGrains = Category::create([
            'name' => 'Grains & Rice',
            'slug' => 'grains-rice',
            'icon' => 'bi-layers',
            'description' => 'Locally milled premium Dinorado, Sinandomeng, and upland heirloom grains.',
        ]);

        $catRootCrops = Category::create([
            'name' => 'Root Crops',
            'slug' => 'root-crops',
            'icon' => 'bi-box-seam',
            'description' => 'Nutrient-rich tubers, sweet potatoes, and highland potatoes.',
        ]);

        $catHerbs = Category::create([
            'name' => 'Herbs & Spices',
            'slug' => 'herbs-spices',
            'icon' => 'bi-fire',
            'description' => 'Fresh native garlic, ginger, chili, and culinary aromatics.',
        ]);

        // 5. Create Products
        $pSayote = Product::create([
            'user_id' => $farmerJustin->id,
            'category_id' => $catVegetables->id,
            'name' => 'Chayote (Sayote)',
            'description' => 'Freshly picked crisp green chayote from the cool hills of Cagdianao. Perfect for chicken tinola and chopsuey.',
            'price' => 35.00,
            'quantity' => 85,
            'kilograms' => 1.00,
            'unit' => 'kg',
            'image_path' => 'https://images.unsplash.com/photo-1598170845058-32b9d6a5da37?w=600&auto=format&fit=crop&q=80',
            'is_active' => true,
        ]);

        $pPotato = Product::create([
            'user_id' => $farmerJustin->id,
            'category_id' => $catRootCrops->id,
            'name' => 'Benguet Potato',
            'description' => 'Firm, golden highland potatoes ideal for frying, stews, adobo, and hearty soups.',
            'price' => 65.00,
            'quantity' => 120,
            'kilograms' => 1.00,
            'unit' => 'kg',
            'image_path' => 'https://images.unsplash.com/photo-1518977676601-b53f82aba655?w=600&auto=format&fit=crop&q=80',
            'is_active' => true,
        ]);

        $pCabbage = Product::create([
            'user_id' => $farmerJustin->id,
            'category_id' => $catVegetables->id,
            'name' => 'Cabbage (Repolyo)',
            'description' => 'Crisp, dense green round cabbage heads picked fresh at sunrise.',
            'price' => 55.00,
            'quantity' => 60,
            'kilograms' => 1.00,
            'unit' => 'kg',
            'image_path' => 'https://images.unsplash.com/photo-1594282486552-05b4d80fbb9f?w=600&auto=format&fit=crop&q=80',
            'is_active' => true,
        ]);

        $pMango = Product::create([
            'user_id' => $farmerPedro->id,
            'category_id' => $catFruits->id,
            'name' => 'Carabao Mango',
            'description' => 'Guaranteed sweet, fragrant golden Philippine mangoes naturally tree-ripened in Luna orchards.',
            'price' => 120.00,
            'quantity' => 45,
            'kilograms' => 1.00,
            'unit' => 'kg',
            'image_path' => 'https://images.unsplash.com/photo-1553279768-865429fa0078?w=600&auto=format&fit=crop&q=80',
            'is_active' => true,
        ]);

        $pGarlic = Product::create([
            'user_id' => $farmerMaria->id,
            'category_id' => $catHerbs->id,
            'name' => 'Native Garlic (Bawang)',
            'description' => 'Intensely aromatic small-clove native garlic. Unmatched aroma and flavor for Philippine cooking.',
            'price' => 110.00,
            'quantity' => 90,
            'kilograms' => 1.00,
            'unit' => 'kg',
            'image_path' => 'https://images.unsplash.com/photo-1615477550927-6ec7e38f3750?w=600&auto=format&fit=crop&q=80',
            'is_active' => true,
        ]);

        $pOnion = Product::create([
            'user_id' => $farmerMaria->id,
            'category_id' => $catHerbs->id,
            'name' => 'Red Onion (Sibuyas Tagalog)',
            'description' => 'Firm, pungent red onions harvested directly from San Juan farm patches.',
            'price' => 95.00,
            'quantity' => 80,
            'kilograms' => 1.00,
            'unit' => 'kg',
            'image_path' => 'https://images.unsplash.com/photo-1618512496248-a07fe83aa8cb?w=600&auto=format&fit=crop&q=80',
            'is_active' => true,
        ]);

        $pTomato = Product::create([
            'user_id' => $farmerMaria->id,
            'category_id' => $catVegetables->id,
            'name' => 'Native Tomato (Kamatis)',
            'description' => 'Plump, ripe red tomatoes packed with natural umami, ideal for sinigang and sautéing.',
            'price' => 45.00,
            'quantity' => 70,
            'kilograms' => 1.00,
            'unit' => 'kg',
            'image_path' => 'https://images.unsplash.com/photo-1592924357228-91a4daadcfea?w=600&auto=format&fit=crop&q=80',
            'is_active' => true,
        ]);

        $pEggplant = Product::create([
            'user_id' => $farmerMaria->id,
            'category_id' => $catVegetables->id,
            'name' => 'Fresh Eggplant (Talong)',
            'description' => 'Tender long purple eggplants, glossy and pesticide-free, great for tortang talong and pinakbet.',
            'price' => 40.00,
            'quantity' => 50,
            'kilograms' => 1.00,
            'unit' => 'kg',
            'image_path' => 'https://images.unsplash.com/photo-1621841957884-1210fe19d66d?w=600&auto=format&fit=crop&q=80',
            'is_active' => true,
        ]);

        $pRice = Product::create([
            'user_id' => $farmerPedro->id,
            'category_id' => $catGrains->id,
            'name' => 'Dinorado Rice (50kg sack)',
            'description' => 'Fragrant, soft-cooking pinkish white Dinorado rice freshly harvested and well-milled.',
            'price' => 2600.00,
            'quantity' => 40,
            'kilograms' => 50.00,
            'unit' => 'sack',
            'image_path' => 'https://images.unsplash.com/photo-1586201375761-83865001e31c?w=600&auto=format&fit=crop&q=80',
            'is_active' => true,
        ]);

        $pCalamansi = Product::create([
            'user_id' => $farmerPedro->id,
            'category_id' => $catFruits->id,
            'name' => 'Fresh Calamansi',
            'description' => 'Tart, juicy Philippine lime rich in Vitamin C, indispensable for sawsawan, marinades, and juices.',
            'price' => 60.00,
            'quantity' => 65,
            'kilograms' => 1.00,
            'unit' => 'kg',
            'image_path' => 'https://images.unsplash.com/photo-1534432182912-63863115e106?w=600&auto=format&fit=crop&q=80',
            'is_active' => true,
        ]);

        $pGinger = Product::create([
            'user_id' => $farmerJustin->id,
            'category_id' => $catHerbs->id,
            'name' => 'Organic Ginger (Luya)',
            'description' => 'Spicy aromatic root ginger dug up fresh upon ordering.',
            'price' => 85.00,
            'quantity' => 40,
            'kilograms' => 1.00,
            'unit' => 'kg',
            'image_path' => 'https://images.unsplash.com/photo-1615485290382-441e4d049cb5?w=600&auto=format&fit=crop&q=80',
            'is_active' => true,
        ]);

        // Alternative farmer product for price comparison!
        $pSayoteMaria = Product::create([
            'user_id' => $farmerMaria->id,
            'category_id' => $catVegetables->id,
            'name' => 'Chayote (Sayote) - Lowland Patch',
            'description' => 'Fresh sayote harvested from San Juan greenhouses.',
            'price' => 32.00, // cheaper comparison
            'quantity' => 40,
            'kilograms' => 1.00,
            'unit' => 'kg',
            'image_path' => 'https://images.unsplash.com/photo-1598170845058-32b9d6a5da37?w=600&auto=format&fit=crop&q=80',
            'is_active' => true,
        ]);

        // 6. Create Recipes & Ingredients
        $recAdobo = Recipe::create([
            'title' => 'Classic Pork Adobo',
            'slug' => 'classic-pork-adobo',
            'description' => 'The quintessential Filipino comfort dish simmered in vinegar, soy sauce, garlic, and cracked black pepper until tender.',
            'instructions' => "1. Marinate pork with crushed native garlic, soy sauce, and black pepper for 30 minutes.\n2. Sear the meat in a pot until lightly browned on all sides.\n3. Pour in vinegar and simmer gently without stirring for 5 minutes.\n4. Add potatoes and water, then cover and simmer for 35 minutes until fork tender.\n5. Reduce sauce until glossy and serve hot with steamed Dinorado rice.",
            'prep_time' => '45 mins',
            'image_path' => 'https://images.unsplash.com/photo-1544025162-d76694265947?w=600&auto=format&fit=crop&q=80',
        ]);

        RecipeIngredient::create(['recipe_id' => $recAdobo->id, 'name' => 'Native Garlic', 'quantity_description' => '1 head, crushed', 'category_id' => $catHerbs->id]);
        RecipeIngredient::create(['recipe_id' => $recAdobo->id, 'name' => 'Benguet Potato', 'quantity_description' => '3 medium pieces, cubed', 'category_id' => $catRootCrops->id]);
        RecipeIngredient::create(['recipe_id' => $recAdobo->id, 'name' => 'Red Onion', 'quantity_description' => '1 medium, sliced', 'category_id' => $catHerbs->id]);
        RecipeIngredient::create(['recipe_id' => $recAdobo->id, 'name' => 'Soy Sauce', 'quantity_description' => '1/2 cup', 'category_id' => null]); // unavailable
        RecipeIngredient::create(['recipe_id' => $recAdobo->id, 'name' => 'Cane Vinegar', 'quantity_description' => '1/3 cup', 'category_id' => null]); // unavailable
        RecipeIngredient::create(['recipe_id' => $recAdobo->id, 'name' => 'Black Peppercorn', 'quantity_description' => '1 tbsp whole cracked', 'category_id' => null]); // unavailable

        $recPinakbet = Recipe::create([
            'title' => 'Pinakbet Ilocano',
            'slug' => 'pinakbet-ilocano',
            'description' => 'A hearty vegetable stew of eggplant, tomatoes, sayote, and ginger seasoned with fermented fish paste.',
            'instructions' => "1. Sauté native garlic, red onion, and ginger in a pan.\n2. Add sliced native tomatoes and cook until soft and pulpy.\n3. Add chayote and eggplant slices; toss gently to combine.\n4. Cover and cook on medium-low heat until vegetables are tender yet crisp.",
            'prep_time' => '30 mins',
            'image_path' => 'https://images.unsplash.com/photo-1540420773420-3366772f4999?w=600&auto=format&fit=crop&q=80',
        ]);

        RecipeIngredient::create(['recipe_id' => $recPinakbet->id, 'name' => 'Fresh Eggplant', 'quantity_description' => '3 pieces, sliced diagonally', 'category_id' => $catVegetables->id]);
        RecipeIngredient::create(['recipe_id' => $recPinakbet->id, 'name' => 'Native Tomato', 'quantity_description' => '4 medium, quartered', 'category_id' => $catVegetables->id]);
        RecipeIngredient::create(['recipe_id' => $recPinakbet->id, 'name' => 'Chayote (Sayote)', 'quantity_description' => '2 medium pieces, sliced', 'category_id' => $catVegetables->id]);
        RecipeIngredient::create(['recipe_id' => $recPinakbet->id, 'name' => 'Organic Ginger', 'quantity_description' => '1 knob, julienned', 'category_id' => $catHerbs->id]);
        RecipeIngredient::create(['recipe_id' => $recPinakbet->id, 'name' => 'Bitter Melon (Ampalaya)', 'quantity_description' => '1 medium piece', 'category_id' => null]); // unavailable

        $recSinigang = Recipe::create([
            'title' => 'Sinigang na Baboy with Calamansi',
            'slug' => 'sinigang-na-baboy-calamansi',
            'description' => 'Tangy, savory sour soup enriched with fresh tomatoes, calamansi, and fresh local vegetables.',
            'instructions' => "1. Boil pork ribs with quartered native tomatoes and red onions until tender.\n2. Add chayote and cook for 6 minutes.\n3. Squeeze in fresh calamansi juice to taste.\n4. Season with salt and serve piping hot.",
            'prep_time' => '50 mins',
            'image_path' => 'https://images.unsplash.com/photo-1547592180-85f173990554?w=600&auto=format&fit=crop&q=80',
        ]);

        RecipeIngredient::create(['recipe_id' => $recSinigang->id, 'name' => 'Native Tomato', 'quantity_description' => '5 ripe tomatoes', 'category_id' => $catVegetables->id]);
        RecipeIngredient::create(['recipe_id' => $recSinigang->id, 'name' => 'Red Onion', 'quantity_description' => '2 medium onions, quartered', 'category_id' => $catHerbs->id]);
        RecipeIngredient::create(['recipe_id' => $recSinigang->id, 'name' => 'Fresh Calamansi', 'quantity_description' => '8 pieces, juiced', 'category_id' => $catFruits->id]);
        RecipeIngredient::create(['recipe_id' => $recSinigang->id, 'name' => 'Chayote (Sayote)', 'quantity_description' => '2 pieces, sliced', 'category_id' => $catVegetables->id]);
        RecipeIngredient::create(['recipe_id' => $recSinigang->id, 'name' => 'Water Spinach (Kangkong)', 'quantity_description' => '1 bunch fresh leaves', 'category_id' => null]); // unavailable

        $recChopsuey = Recipe::create([
            'title' => 'Crisp Garden Chopsuey',
            'slug' => 'crisp-garden-chopsuey',
            'description' => 'A colorful stir-fry of highland cabbage, potatoes, sayote, and aromatic garlic in savory sauce.',
            'instructions' => "1. Sauté garlic and onions in high heat.\n2. Add potatoes and sayote with a splash of broth; cover for 3 minutes.\n3. Stir in fresh cabbage and cook until vibrant green and crunchy.",
            'prep_time' => '25 mins',
            'image_path' => 'https://images.unsplash.com/photo-1512621776951-a57141f2eefd?w=600&auto=format&fit=crop&q=80',
        ]);

        RecipeIngredient::create(['recipe_id' => $recChopsuey->id, 'name' => 'Cabbage (Repolyo)', 'quantity_description' => '1/2 medium head, cut into wedges', 'category_id' => $catVegetables->id]);
        RecipeIngredient::create(['recipe_id' => $recChopsuey->id, 'name' => 'Benguet Potato', 'quantity_description' => '2 medium potatoes, sliced', 'category_id' => $catRootCrops->id]);
        RecipeIngredient::create(['recipe_id' => $recChopsuey->id, 'name' => 'Chayote (Sayote)', 'quantity_description' => '1 medium sayote, julienned', 'category_id' => $catVegetables->id]);
        RecipeIngredient::create(['recipe_id' => $recChopsuey->id, 'name' => 'Native Garlic', 'quantity_description' => '4 cloves minced', 'category_id' => $catHerbs->id]);
        RecipeIngredient::create(['recipe_id' => $recChopsuey->id, 'name' => 'Red Onion', 'quantity_description' => '1 sliced', 'category_id' => $catHerbs->id]);

        // 7. Create Announcements
        Announcement::create([
            'user_id' => $admin->id,
            'title' => 'Surigao Agri-Trade Fair & Harvest Festival',
            'location' => 'Surigao City Provincial Gymnasium',
            'event_date' => 'October 15, 2026',
            'description' => 'Join all registered farmers and food buyers for the annual Harvest Showcase. Farmers can bring extra stock for on-site display and direct sales booths.',
            'is_active' => true,
        ]);

        Announcement::create([
            'user_id' => $admin->id,
            'title' => 'Organic Farming & Soil Health Workshop',
            'location' => 'DA Research Station, Brgy. Rizal',
            'event_date' => 'November 10, 2026',
            'description' => 'Free hands-on seminar for registered farmers regarding biological pest control, organic composting, and weather resilient crop varieties.',
            'is_active' => true,
        ]);

        Announcement::create([
            'user_id' => $admin->id,
            'title' => 'Financial & Digital Literacy Training for Agri-Cooperatives',
            'location' => 'SNSU CCIS Auditorium, Surigao City',
            'event_date' => 'December 02, 2026',
            'description' => 'Learn how to maximize online sales tracking, digital payments (GCash, Bank), and bulk contract delivery through the FarmReach Cloud platform.',
            'is_active' => true,
        ]);

        // 8. Create Orders & Order Items
        // Order 1: Standard Order from Shery to Farmer Justin
        $order1 = Order::create([
            'order_number' => 'ORD-20260904-001',
            'buyer_id' => $buyerShery->id,
            'farmer_id' => $farmerJustin->id,
            'order_type' => 'standard',
            'total_amount' => 200.00,
            'delivery_method' => 'deliver',
            'delivery_address' => 'Nueva Extension, Surigao City',
            'shipping_fee' => 60.00,
            'status' => 'confirmed',
            'notes' => 'Please deliver in the afternoon.',
        ]);

        OrderItem::create([
            'order_id' => $order1->id,
            'product_id' => $pSayote->id,
            'quantity' => 4,
            'unit_price' => 35.00,
            'subtotal' => 140.00,
        ]);

        Payment::create([
            'order_id' => $order1->id,
            'payment_method' => 'gcash',
            'payment_status' => 'paid',
            'reference_number' => 'GCASH-98210344',
            'amount' => 200.00,
            'payment_date' => now()->subDay(),
        ]);

        // Order 2: Bulk Order from Rynard (Restaurant) to Farmer Pedro
        $order2 = Order::create([
            'order_number' => 'BLK-20260904-002',
            'buyer_id' => $buyerRynard->id,
            'farmer_id' => $farmerPedro->id,
            'order_type' => 'bulk',
            'total_amount' => 5200.00,
            'delivery_method' => 'deliver',
            'delivery_address' => 'Borromeo Street Commercial Kitchen, Surigao City',
            'shipping_fee' => 150.00,
            'status' => 'pending',
            'notes' => 'Bulk supply for weekend restaurant banquet. Need 2 sacks of Dinorado.',
        ]);

        OrderItem::create([
            'order_id' => $order2->id,
            'product_id' => $pRice->id,
            'quantity' => 2,
            'unit_price' => 2600.00,
            'subtotal' => 5200.00,
        ]);

        Payment::create([
            'order_id' => $order2->id,
            'payment_method' => 'bank_transfer',
            'payment_status' => 'pending',
            'reference_number' => 'BDO-TRX-449102',
            'amount' => 5350.00,
            'payment_date' => now(),
        ]);

        // Order 3: Delivered Order from Ana to Farmer Maria
        $order3 = Order::create([
            'order_number' => 'ORD-20260904-003',
            'buyer_id' => $buyerAna->id,
            'farmer_id' => $farmerMaria->id,
            'order_type' => 'standard',
            'total_amount' => 250.00,
            'delivery_method' => 'pickup',
            'delivery_address' => 'Pick up at Brgy. San Juan Farm Stand',
            'shipping_fee' => 0.00,
            'status' => 'delivered',
            'notes' => 'Will pick up before 5 PM.',
        ]);

        OrderItem::create([
            'order_id' => $order3->id,
            'product_id' => $pTomato->id,
            'quantity' => 2,
            'unit_price' => 45.00,
            'subtotal' => 90.00,
        ]);
        OrderItem::create([
            'order_id' => $order3->id,
            'product_id' => $pGarlic->id,
            'quantity' => 1,
            'unit_price' => 110.00,
            'subtotal' => 110.00,
        ]);
        OrderItem::create([
            'order_id' => $order3->id,
            'product_id' => $pEggplant->id,
            'quantity' => 1,
            'unit_price' => 40.00,
            'subtotal' => 40.00,
        ]);

        Payment::create([
            'order_id' => $order3->id,
            'payment_method' => 'cod',
            'payment_status' => 'paid',
            'reference_number' => 'COD-CASH-SURIGAO',
            'amount' => 240.00,
            'payment_date' => now()->subDays(2),
        ]);

        // 9. Activity Logs
        ActivityLog::log($admin->id, 'System Initialized', 'FarmReach Cloud database seeded with standard roles, demo accounts, and agricultural inventory.');
        ActivityLog::log($farmerJustin->id, 'Product Created', 'Farmer Justin listed Chayote (Sayote) at ₱35/kg.');
        ActivityLog::log($farmerPedro->id, 'Product Created', 'Farmer Pedro listed Dinorado Rice (50kg sack) at ₱2,600/sack.');
        ActivityLog::log($buyerShery->id, 'Order Placed', 'Buyer Shery placed Order #ORD-20260904-001 for Chayote.');
        ActivityLog::log($buyerRynard->id, 'Bulk Order Placed', 'Buyer Rynard placed Bulk Order #BLK-20260904-002 for 100kg Dinorado Rice.');
        ActivityLog::log($farmerJustin->id, 'Order Confirmed', 'Farmer Justin confirmed Order #ORD-20260904-001.');
    }
}

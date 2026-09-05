<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Announcement;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AdminController extends Controller
{
    public function dashboard()
    {
        $totalFarmers = User::where('role', 'farmer')->count();
        $totalBuyers = User::where('role', 'buyer')->count();
        $totalSales = Order::where('status', 'delivered')->sum('total_amount');
        $pendingOrders = Order::where('status', 'pending')->count();

        // 30 Days Sales Data for Chart.js
        $salesData = Order::select(
            DB::raw('DATE(created_at) as order_date'),
            DB::raw('SUM(total_amount) as daily_total')
        )
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('order_date')
            ->orderBy('order_date', 'asc')
            ->get();

        // Top Selling Products Report
        $topProducts = OrderItem::select(
            'product_id',
            DB::raw('SUM(quantity) as total_qty_sold'),
            DB::raw('SUM(subtotal) as total_revenue')
        )
            ->with('product.farmer')
            ->groupBy('product_id')
            ->orderByDesc('total_qty_sold')
            ->take(5)
            ->get();

        $recentAnnouncements = Announcement::latest()->take(5)->get();
        $recentActivities = ActivityLog::with('user')->latest()->take(10)->get();

        return view('admin.dashboard', compact(
            'totalFarmers',
            'totalBuyers',
            'totalSales',
            'pendingOrders',
            'salesData',
            'topProducts',
            'recentAnnouncements',
            'recentActivities'
        ));
    }

    public function users(Request $request)
    {
        $query = User::whereIn('role', ['farmer', 'buyer']);

        if ($request->filled('role') && in_array($request->role, ['farmer', 'buyer'])) {
            $query->where('role', $request->role);
        }

        if ($request->filled('search')) {
            $s = trim($request->search);
            $query->where(function ($q) use ($s) {
                $q->where('name', 'LIKE', "%{$s}%")
                    ->orWhere('email', 'LIKE', "%{$s}%")
                    ->orWhere('phone_number', 'LIKE', "%{$s}%");
            });
        }

        $users = $query->latest()->paginate(15)->withQueryString();

        return view('admin.users', compact('users'));
    }

    public function deleteUser(User $user)
    {
        if ($user->isAdmin()) {
            return back()->with('error', 'Administrator account cannot be deleted.');
        }

        $userName = $user->name;
        $userRole = $user->role;
        $user->delete();

        ActivityLog::log(Auth::id(), 'User Deleted', "Admin deleted {$userRole} account: {$userName}.");

        return back()->with('success', "User '{$userName}' has been deleted.");
    }

    public function announcements()
    {
        $announcements = Announcement::with('author')->latest()->paginate(10);

        return view('admin.announcements', compact('announcements'));
    }

    public function storeAnnouncement(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:190',
            'location' => 'nullable|string|max:190',
            'event_date' => 'nullable|string|max:100',
            'description' => 'required|string|max:2000',
        ]);

        $announcement = Announcement::create([
            'user_id' => Auth::id(),
            'title' => $validated['title'],
            'location' => $validated['location'] ?? null,
            'event_date' => $validated['event_date'] ?? null,
            'description' => $validated['description'],
            'is_active' => true,
        ]);

        ActivityLog::log(Auth::id(), 'Announcement Created', "Admin posted: {$announcement->title}.");

        return back()->with('success', 'Announcement published successfully!');
    }

    public function deleteAnnouncement(Announcement $announcement)
    {
        $title = $announcement->title;
        $announcement->delete();

        ActivityLog::log(Auth::id(), 'Announcement Deleted', "Admin deleted announcement: {$title}.");

        return back()->with('success', 'Announcement deleted successfully.');
    }

    public function updateAnnouncement(Request $request, Announcement $announcement)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:190',
            'location' => 'nullable|string|max:190',
            'event_date' => 'nullable|string|max:100',
            'description' => 'required|string|max:2000',
        ]);

        $announcement->update([
            'title' => $validated['title'],
            'location' => $validated['location'] ?? null,
            'event_date' => $validated['event_date'] ?? null,
            'description' => $validated['description'],
        ]);

        ActivityLog::log(Auth::id(), 'Announcement Updated', "Admin updated announcement: {$announcement->title}.");

        return back()->with('success', 'Announcement updated successfully!');
    }

    public function recipes()
    {
        $recipes = Recipe::with(['ingredients.category'])->latest()->paginate(10);
        $categories = Category::all();

        return view('admin.recipes', compact('recipes', 'categories'));
    }

    public function storeRecipe(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:190',
            'prep_time' => 'nullable|string|max:50',
            'description' => 'nullable|string|max:1000',
            'instructions' => 'nullable|string|max:5000',
            'image' => 'nullable|image|max:2048',
            'ingredients' => 'required|array|min:1',
            'ingredients.*.name' => 'required|string|max:190',
            'ingredients.*.quantity_description' => 'nullable|string|max:100',
            'ingredients.*.category_id' => 'nullable|exists:categories,id',
        ]);

        $imagePath = 'https://images.unsplash.com/photo-1540420773420-3366772f4999?w=600&auto=format&fit=crop&q=80';
        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('recipes', 'public');
            $imagePath = '/storage/'.$path;
        }

        $recipe = Recipe::create([
            'title' => $validated['title'],
            'slug' => Str::slug($validated['title']).'-'.rand(100, 999),
            'prep_time' => $validated['prep_time'] ?? null,
            'description' => $validated['description'] ?? null,
            'instructions' => $validated['instructions'] ?? null,
            'image_path' => $imagePath,
        ]);

        foreach ($validated['ingredients'] as $ing) {
            RecipeIngredient::create([
                'recipe_id' => $recipe->id,
                'name' => $ing['name'],
                'quantity_description' => $ing['quantity_description'] ?? null,
                'category_id' => $ing['category_id'] ?? null,
            ]);
        }

        ActivityLog::log(Auth::id(), 'Recipe Created', "Admin created recipe: {$recipe->title}.");

        return back()->with('success', "Recipe '{$recipe->title}' created with ingredient mappings!");
    }

    public function deleteRecipe(Recipe $recipe)
    {
        $title = $recipe->title;
        $recipe->delete();

        ActivityLog::log(Auth::id(), 'Recipe Deleted', "Admin deleted recipe: {$title}.");

        return back()->with('success', "Recipe '{$title}' deleted.");
    }
}

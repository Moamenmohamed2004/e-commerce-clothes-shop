<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\Product;
use App\Models\Customer;
use App\Models\User;

class DashboardController extends Controller
{
    public function index()
    {
        $totalRevenue = Order::whereNotIn('status', ['Cancelled', 'Refunded'])->sum('total');
        $totalOrders = Order::count();
        $totalProducts = Product::count();
        $activeCustomers = Customer::count();
        
        $recentOrders = Order::orderBy('created_at', 'desc')->take(5)->get();

        return response()->json([
            'stats' => [
                'totalRevenue' => $totalRevenue,
                'totalOrders' => $totalOrders,
                'totalProducts' => $totalProducts,
                'activeUsers' => $activeCustomers,
            ],
            'recentOrders' => $recentOrders
        ]);
    }
}

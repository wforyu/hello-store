<?php

namespace App\Http\Controllers;

use App\Models\Review;

class AccountController extends Controller
{
    public function dashboard()
    {
        $user = auth()->user();

        $ordersCount = $user->orders()->count();
        $recentOrders = $user->orders()->with('items')->latest()->take(5)->get();
        $addressesCount = $user->addresses()->count();
        $reviewsCount = Review::where('user_id', $user->id)->count();

        return view('account.dashboard', compact(
            'user', 'ordersCount', 'recentOrders', 'addressesCount', 'reviewsCount'
        ));
    }
}

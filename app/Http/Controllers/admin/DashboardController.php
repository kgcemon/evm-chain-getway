<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\Transactions;
use App\Models\User;

class DashboardController extends Controller
{
    public function index(){
        $totalTrx = Transactions::all()->count();
        $dashboardData = [
            'totalCustomer' => User::all()->count(),
            'totalTrx' => $totalTrx,
        ];
        return view('admin.dashboard', compact('dashboardData'));
    }
}

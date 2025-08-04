<?php

namespace App\Http\Controllers\api\Client;

use App\Http\Controllers\Controller;
use App\Models\Transactions;
use Illuminate\Http\Request;

class ClientTransactionController extends Controller
{
    public function index(Request $request){
        $user = $request->user();
        $transactions = Transactions::where('user_id', $user->id)->orderBy('created_at', 'desc')->orderBy('id','desc')->paginate(20);
        if(!$transactions->count() > 0){
            return response()->json([
                'status' => false,
                'message' => 'No transactions found',
                'data' => [],
            ]);
        }
        return response()->json([
            'status' => true,
            'message' => 'Success',
            'data' => $transactions->items(),
            'total' => $transactions->total(),
            'last_page' => $transactions->lastPage(),
            'current_page' => $transactions->currentPage(),
            'from' => $transactions->firstItem(),
            'to' => $transactions->lastItem(),
            'per_page' => $transactions->perPage(),
        ]);
    }
}

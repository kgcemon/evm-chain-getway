<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\Transactions;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    // 🟢 Show all transactions
    public function index()
    {
        $transactions = Transactions::with('user')->latest()->paginate(10);
        return view('admin.pages.transactions.index', compact('transactions'));
    }

    // 🟢 Update transaction
    public function update(Request $request, $id)
    {
        $transaction = Transactions::findOrFail($id);

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0',
            'status' => 'required|string',
            'trx_hash' => 'nullable|string|max:255',
            'token_name' => 'required|string|max:255',
            'type' => 'required|string|max:255',
        ]);

        $transaction->update($validated);

        return response()->json(['success' => true]);
    }
}
